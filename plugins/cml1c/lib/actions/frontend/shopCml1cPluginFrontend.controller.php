<?php
class shopCml1cPluginFrontendController extends waController
{

    /**
     *
     * @return shopCml1cPlugin
     */
    private function plugin()
    {
        static $plugin;
        if (!$plugin) {
            $plugin = wa()->getPlugin('cml1c');
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
        static $runner;
        if (!$runner || $force) {
            if ($runner) {
                unset($runner);
            }
            $runner = new shopCml1cPluginBackendRunController();
        }
        return $runner;
    }

    public function execute()
    {
        @set_time_limit(0);
        wa()->setLocale('ru_RU');

        /**
         * @var shopCml1cPlugin $plugin
         */
        $plugin = wa()->getPlugin('cml1c');
        $uuid = $plugin->uuid();
        if (empty($uuid) || (waRequest::param('hash') != $uuid)) {
            throw new waRightsException('1C');
        }

        try {

            switch (waRequest::get('type')) {
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
                            $files = $this->uploadFile();
                            if (is_array($files)) {
                                $files = array_map('basename', $files);
                                $count = count($files);
                                $files = array_slice($files, 0, min(3, count($files) - 1));
                                $message = sprintf('%d Files uploaded (%s, ...)', $count, implode(', ', $files));
                            } else {
                                $message = sprintf('File %s uploaded', basename($files));
                                if (preg_match('/\.zip/', $files)) {
                                    $this->getStorage()->set('filename', $files);
                                }
                            }
                            $this->response('success', $message);
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

                        case 'query':
                            /* C. Получение файла обмена с сайта*/

                            $_POST['direction'] = 'export';
                            $_POST['export'] = array(
                                'order'     => true,
                                'new_order' => false,
                            );
                            ob_start();
                            $this->runner()->run();
                            $this->getStorage()->set('processId', $_POST['processId'] = $this->runner()->processId);
                            sleep(3);
                            clearstatcache();
                            sleep(3);
                            $this->runner(true)->run();
                            ob_get_clean();
                            sleep(2);
                            clearstatcache();
                            sleep(3);
                            ob_start();
                            $this->runner(true)->run();
                            if ($r = ob_get_clean()) {
                                $this->response('failure', $r);
                            }
                            break;

                        case 'success':
                            /* C. Подтверждение получения файла обмена с сайта*/
                            /*@todo update timestamp for exchange*/
                            $s = $this->getStorage();
                            $s->set('success', true);
                            if ($id = $s->get('processId')) {
                                $_POST['processId'] = $id;
                                $_POST['direction'] = 'export';
                                $_POST['cleanup'] = true;
                                $this->runner()->run();
                                $this->response('success', 'OK');
                            } else {
                                $this->response('success', 'already deleted');
                            }

                            break;

                        case "init":
                            /* B. Уточнение параметров сеанса*/
                            $this->initFile();
                            break;

                        case 'file':
                            /* D. Отправка файла обмена на сайт*/

                            $this->importSale();
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
            $this->response("failure", $ex->getMessage());
        }
    }

    private function checkAuth()
    {
        $s = $this->getStorage();
        $s->open();
        $options = $s->getOptions();

        $this->response("success", ifset($options['session_name'], session_name()), ifset($options['session_id'], session_id()));
    }

    private function initFile($clean = false)
    {
        $this->getStorage()->del('processId');
        if (!ini_get('file_uploads')) {
            /*@todo check rights*/
            $this->response("failure");
        } else {
            if ($clean) {
                try {
                    waFiles::delete($this->plugin()->path(false), true);
                } catch (waException $ex) {
                    ;
                }
            }

            $size = ini_get('upload_max_filesize');
            if (preg_match('/(\d+)\s*([KMG]?)/', $size, $matches)) {
                $m = array(
                    'K' => 1024,
                    'M' => 1048576,
                    'G' => 1073741824,
                );
                $size = intval($matches[1]) * ifset($m[$matches[2]], 1);
            }

            $this->response(sprintf("zip=%s", function_exists('zip_open') ? "yes" : "no"), sprintf("file_limit=%d", 0.8 * $size));
        }
    }

    private function uploadFile()
    {
        if (isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
            $data = !empty($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : null;
        } else {
            $data = implode("\r\n", file('php://input'));
        }

        if ($data !== false) {
            $filename = $this->plugin()->path(waRequest::get('filename', 'upload'));
            if ($fp = fopen($filename, "ab")) {
                $result = fwrite($fp, $data);
                fclose($fp);
                if ($result !== mb_strlen($data, 'latin1')) {
                    throw new waException("Error while write file");
                }
            } else {
                throw new waException("Error while open file");
            }
        } else {
            throw new waException("Error while read POST file");
        }
        return $filename;
    }

    private function importCatalog($filename)
    {
        $s = $this->getStorage();
        $_POST['processId'] = $s->get('processId'.$filename);
        $_POST['direction'] = 'import';
        $_POST['filename'] = $filename;
        $_POST['zipfile'] = $s->get('filename');
        ob_start();
        if ($_POST['processId']) {
            sleep(2);
            clearstatcache();
            sleep(2);
        }
        $this->runner()->run();
        $r = ob_get_clean();
        if (!$_POST['processId']) {
            $s->set('processId'.$filename, $this->runner()->processId);
            $this->response('progress', $r);

        } else {
            if (strpos($r, 'success' === 0)) {
                ob_start();
                if ($_POST['processId']) {
                    sleep(2);
                    clearstatcache();
                    sleep(2);
                }
                $this->runner(true)->run();
                $r = ob_get_clean();
            }
            $this->response($r);
        }
    }

    private function response($response = "success")
    {
        $response = func_get_args();
        if (!$response) {
            $response = array('success');
        }
        $this->getResponse()->addHeader('Content-type', 'text/plain');
        $this->getResponse()->sendHeaders();
        print(implode("\r\n", $response));
    }

    private function importSale()
    {
        //return $this->response('success', 'ignore orders from 1C');
        $filename = $this->uploadFile();
        $this->response('success', basename($filename));
    }
}
