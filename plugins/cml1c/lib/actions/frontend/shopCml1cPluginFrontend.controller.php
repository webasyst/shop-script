<?php

/**
 * @see http://v8.1c.ru/edi/edi_stnd/131/
 * Class shopCml1cPluginFrontendController
 */
class shopCml1cPluginFrontendController extends waController
{
    private $use_reflection = false;

    /**
     *
     * @return shopCml1cPlugin
     * @throws waException
     */
    private function plugin()
    {
        static $plugin;
        if (!$plugin) {
            /** @var shopCml1cPlugin $plugin */
            $plugin = wa('shop')->getPlugin('cml1c');
        }
        return $plugin;
    }

    /**
     *
     * @param bool $force
     * @return shopCml1cPluginBackendRunController
     */
    private function runner($force = false)
    {
        /** @var shopCml1cPluginBackendRunController $runner */
        static $runner;
        if (!$runner || $force) {
            if ($runner) {
                unset($runner);
                $runner = null;
            }

            $runner = new shopCml1cPluginBackendRunController();
        }
        return $runner;
    }

    public function preExecute()
    {
        parent::preExecute();
        $this->use_reflection = version_compare(PHP_VERSION, '5.3.2', '>=');
    }

    public function execute()
    {
        @set_time_limit(0);
        wa()->setLocale('ru_RU');
        ignore_user_abort(true);

        /** @var shopCml1cPlugin $plugin */
        $plugin = wa()->getPlugin('cml1c');
        $uuid = $plugin->uuid();
        if (empty($uuid) || (waRequest::param('hash') != $uuid)) {
            throw new waRightsException('1C');
        }
        $type = waRequest::get('type');

        try {
            switch ($type) {
                case 'catalog':
                    /*Выгрузка каталогов продукции*/
                    switch (waRequest::get('mode')) {

                        case 'checkauth':
                            /*A. Начало сеанса*/
                            $this->checkAuth();
                            break;

                        case "init":
                            /* B. Запрос параметров от сайта*/
                            $this->initFile(true);
                            break;

                        case "file":
                            /* C. Выгрузка на сайт файлов обмена*/
                            $this->uploadCatalog();
                            break;

                        case "import":
                            /* D. Пошаговая загрузка каталога*/
                            $this->importCatalog(waRequest::get('filename'));
                            break;

                        default:
                            /* unknown exchange mode */

                    }
                    break;
                case 'sale':
                    /*Обмен информацией о заказах*/
                    switch (waRequest::get('mode')) {

                        case 'checkauth':
                            /* A. Начало сеанса*/
                            $this->checkAuth();
                            break;

                        case "init":
                            /* B. Уточнение параметров сеанса*/
                            $this->initFile();
                            break;

                        case 'query':
                            /* C. Получение файла обмена с сайта*/
                            $this->exportSale();
                            break;

                        case 'file':
                            /* D. Отправка файла обмена на сайт*/
                            $this->importSale();
                            break;

                        case 'success':
                            /* C. Подтверждение получения файла обмена с сайта*/
                            $this->plugin()->exportTime(true);
                            $s = $this->getStorage();
                            $s->set('success', true);
                            if ($id = $s->get('processId')) {
                                $_POST['processId'] = $id;
                                $_POST['direction'] = 'export';
                                $_POST['cleanup'] = true;
                                ob_start();
                                $this->runner()->run();
                                ob_get_clean();
                                $this->response('success', "OK");
                            } else {
                                $this->response('success', 'already deleted');
                            }

                            break;

                        default:
                            /* unknown exchange mode */
                    }
                    break;
                default:
                    /* unknown exchange type */
                    break;
            }
        } catch (waException $ex) {
            $message = $ex->getMessage();
            $trace = $ex->getTraceAsString();
            $ip = waRequest::getIp();
            waLog::log(var_export(compact('message', 'type', 'trace', 'ip'), true), 'shop/plugins/cml1c.log');
            $this->response("failure", $message);
        }
    }

    private function checkAuth()
    {
        $s = $this->getStorage();
        $s->open();
        $options = $s->getOptions();

        $this->response("success", ifset($options['session_name'], session_name()), ifset($options['session_id'], session_id()));
    }

    private function checkIP()
    {
        $ip = waRequest::getIp(true);
        $masks = array();
        $valid = false;
        foreach ($masks as $mask) {
            $mask = trim($mask);
            if (strpos($mask, '-')) { # interval
                $interval = array_map('trim', explode('-', $mask, 2));
                $interval = array_map('ip2long', $interval);
                if (($ip <= max($interval)) && ($ip >= min($interval))) {
                    $valid = true;
                }
            } elseif (strpos($mask, '/')) { #mask
                list($range, $net_mask) = explode('/', $mask, 2);

                $ip_decimal = ip2long($ip);
                $range_decimal = ip2long($range);

                $wildcard_decimal = (1 << (32 - $net_mask));
                $net_mask_decimal = ~($wildcard_decimal - 1);

                $valid = (($ip_decimal & $net_mask_decimal) == $range_decimal);

            } else {
                $valid = (ip2long($mask) == $ip);
            }
            if ($valid) {
                break;
            }
        }
    }

    private function initFile($clean = false)
    {
        $this->getStorage()->del('processId');
        if (!ini_get('file_uploads')) {
            /*@todo check rights*/
            $this->response("failure", 'Check php.ini setting "file_upload"');
        } else {
            if ($clean) {
                try {
                    waFiles::delete($this->plugin()->path(false), true);
                } catch (waException $ex) {}
            }

            $sizes = array(
                ini_get('upload_max_filesize'),
                ini_get('post_max_size'),
                ini_get('memory_limit'),
                '100M',
            );

            foreach ($sizes as &$size) {

                if (preg_match('/(\d+)\s*([KMG]?)/i', $size, $matches)) {
                    $m = array(
                        'K' => 1024,
                        'M' => 1048576,
                        'G' => 1073741824,
                    );
                    $size = intval($matches[1]) * ifset($m[strtoupper($matches[2])], 1);
                }
                unset($size);
            }

            $sizes = array_filter($sizes);

            $size = max(1024 * 1024, min($sizes));

            $this->response(
                sprintf("zip=%s", function_exists('zip_open') || class_exists('ZipArchive') ? "yes" : "no"),
                sprintf("file_limit=%d", 0.8 * $size)
            );
        }
    }

    private function uploadFile()
    {
        if ($sp = fopen('php://input', 'rb')) {
            $filename = $this->plugin()->path(waRequest::get('filename', 'upload'));
            if ($fp = fopen($filename, "ab")) {
                $result = stream_copy_to_stream($sp, $fp);
            } else {
                throw new waException("Error while open target file");
            }
        } elseif (@isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
            if (!@empty($GLOBALS['HTTP_RAW_POST_DATA'])) {
                $filename = $this->plugin()->path(waRequest::get('filename', 'upload'));
                $result = waFiles::write($filename, @$GLOBALS['HTTP_RAW_POST_DATA']);
            } else {
                throw new waException("Error while read POST file");
            }
        } else {
            throw new waException("Error while read POST file");
        }

        if ($result === false) {
            throw new waException("Error while save target file");
        } else {
            $expected_size = waRequest::server('content_length', 0, waRequest::TYPE_INT);
            if ($expected_size > $result) {
                $message = "Error while save target file (expected %d bytes, but get %d)";
                throw new waException(sprintf($message, $expected_size, $result));
            }
        }
        $save_import_files = $this->plugin()->getSettings('save_import_files');
        $archive_dir = wa()->getDataPath('plugins/', false, 'shop').$this->plugin()->getConfigParam('archive_dir').DIRECTORY_SEPARATOR;
        if ($save_import_files) {
            $file_archive = $archive_dir.date('Ymd_His').'.'.pathinfo($filename,  PATHINFO_EXTENSION);
            waFiles::copy($filename, $file_archive);
        } else {
            waFiles::delete($archive_dir, true);
        }

        return $filename;
    }

    private function uploadCatalog()
    {
        $files = $this->uploadFile();
        if (is_array($files)) {
            $files = array_map('basename', $files);
            $count = count($files);
            $files = array_slice($files, 0, min(3, count($files) - 1));
            $message = sprintf('%d Files uploaded (%s, ...)', $count, implode(', ', $files));
        } else {
            $message = sprintf('File %s uploaded', basename($files));
            if (preg_match('/\.zip/i', $files)) {
                $this->getStorage()->set('filename', $files);
            }
        }
        $this->response('success', $message);
    }

    private function importCatalog($filename)
    {
        $sync = waRequest::param('sync');
        $s = $this->getStorage();
        if (!empty($sync)) {
            $this->response('success', 'Имитация обмена. Фактического обмена данными не произошло', 'Imitation completed. There no data exchange');
        } else {

            $key = 'processId'.$filename;

            #init required POST fields
            $_POST['processId'] = $s->get($key);
            $_POST['direction'] = 'import';
            $_POST['filename'] = $filename;
            $_POST['zipfile'] = $zipfile = $s->get('filename');

            if (empty($_POST['processId'])) {
                ob_start();
                $this->runner()->run();
                $s->set($key, $this->runner()->processId);
                $out = ob_get_clean();
                $this->response('progress', $out);
            } else {
                ob_start();
                $s->close();
                $this->runner()->run();
                $out = ob_get_clean();
                if (strpos($out, 'success') === 0) {
                    $_POST['cleanup'] = true;
                    ob_start();
                    $this->sleep();
                    $this->runner()->run();
                    $details = compact('filename', 'zipfile');
                    $this->runner()->exchangeReport(array_filter($details));
                    $out = ob_get_clean();
                }
                $this->response($out);
            }
        }
    }

    private function response($response = "success")
    {
        $encoding = 'Windows-1251';
        $response = func_get_args();
        if (!$response) {
            $response = array('success');
        }
        $this->getResponse()->addHeader('Content-type', 'text/plain');
        if ($encoding != 'UTF-8') {
            $this->getResponse()->addHeader('Encoding', $encoding);
        }
        $this->getResponse()->sendHeaders();
        if ($encoding != 'UTF-8') {
            print(iconv('UTF-8', $encoding, implode("\r\n", $response)));
        } else {
            print(implode("\r\n", $response));
        }
    }

    private function importSale()
    {
        if (true) {
            $this->response('success', 'Заказы из 1С на сайте игнорируются', 'Orders from 1C are ignored');
        } else {
            //once it will be enabled
            $filename = $this->uploadFile();
            $this->response('success', basename($filename));
        }
    }

    private function exportSale()
    {
        $_POST['direction'] = 'export';
        $sync = waRequest::param('sync');
        if (!empty($sync)) {
            $_POST['export'] = array(
                'virtual_product' => true,
            );
        } else {
            $_POST['export'] = array(
                'order' => true,
            );


            switch ($this->plugin()->getSettings('export_orders')) {
                case 'all':
                    break;
                case 'changed':
                    $_POST['export']['new_order'] = true;
                    break;
            }
        }

        $out = null;
        $this->use_reflection = class_exists('ReflectionClass');

        $ready = $this->step($out);

        if ($ready) {
            $this->runner()->exchangeReport();

            $this->runner()->sendFile();
            $this->cleanup();
        } else {
            waLog::log(sprintf('Error while export sales (not enough iterations) %s', ifempty($out)), 'shop/plugins/cml1c.log');
            $this->response('failure', "\n\n".$out);
        }
    }

    /**
     * @var ReflectionClass
     */
    private $reflection;

    private function step(&$out)
    {
        if ($this->use_reflection) {
            ob_start();
            $controller = $this->runner();

            $this->reflection = new ReflectionClass('shopCml1cPluginBackendRunController');
            #set method public for test purpose
            $init = $this->reflection->getMethod('init');
            $init->setAccessible(true);
            $step = $this->reflection->getMethod('step');
            $step->setAccessible(true);
            $restore = $this->reflection->getMethod('restore');
            $restore->setAccessible(true);
            $done = $this->reflection->getMethod('isDone');
            $done->setAccessible(true);
            $finish = $this->reflection->getMethod('finish');
            $finish->setAccessible(true);

            $init->invoke($controller);
            if (empty($_POST['processId'])) {
                $_POST['processId'] = $controller->processId;
                $this->getStorage()->set('processId', $_POST['processId']);
            }
            $restore->invoke($controller);
            $is_done = $done->invoke($controller);
            while (!$is_done) {
                $step->invoke($controller);
                $is_done = $done->invoke($controller);
            };

            $restore->invoke($controller);
            $out = ob_get_clean();
            if ($out) {
                waLog::log(sprintf('Error while export sales', $out, 'shop/plugins/cml1c.error.log'));
            }
            return $is_done;
        } elseif (true) {

            $limit = 100;
            do {
                ob_start();
                $this->runner()->run();
                if (empty($_POST['processId'])) {
                    $_POST['processId'] = $this->runner()->processId;
                    $this->getStorage()->set('processId', $_POST['processId']);
                }

                $out = ob_get_clean();
                $ready = strpos(substr($out, 0, 8), 'success') === 0;
                $this->sleep();
            } while (--$limit && !$ready);
            return $ready;
        } else {
            $this->runner()->fastExecute();
        }
    }

    private function cleanup()
    {
        $_POST['cleanup'] = true;
        if ($this->use_reflection) {
            $controller = $this->runner();

            $done = $this->reflection->getMethod('isDone');
            $done->setAccessible(true);

            $step = $this->reflection->getMethod('step');
            $step->setAccessible(true);


            $is_done = $done->invoke($controller);

            $continue = true;
            while ($continue && !$is_done) {
                $continue = $step->invoke($controller) || true;
                $is_done = $done->invoke($controller);
            }


            $restore = $this->reflection->getMethod('restore');
            $restore->setAccessible(true);

            $restore->invoke($controller);
        } else {
            ob_start();
            #cleanup code & store exchange report
            $this->sleep();
            $this->runner(true)->run();
            ob_get_clean();
        }
    }


    private function sleep($time = 2)
    {
        sleep($time / 2);
        clearstatcache();
        sleep($time / 2);
    }
}
