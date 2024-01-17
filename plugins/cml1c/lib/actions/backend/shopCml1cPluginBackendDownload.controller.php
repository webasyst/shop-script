<?php

class shopCml1cPluginBackendDownloadController extends waController
{
    public function execute()
    {
        $name = basename(waRequest::get('file', 'export.xml'));

        /** @var shopCml1cPlugin $plugin */
        $plugin = wa()->getPlugin('cml1c');
        if ($name === 'cml1c_import_files.zip') {
            $file = $plugin->path($name);
            file_exists($file) && unlink($file);
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                $tmp_dir  = wa()->getDataPath('plugins', false, 'shop').DIRECTORY_SEPARATOR;
                $tmp_dir .= $plugin->getConfigParam('archive_dir').DIRECTORY_SEPARATOR;
                if ($zip->open($file, ZipArchive::CREATE) === true) {
                    foreach (waFiles::listdir($tmp_dir, true) as $filename) {
                        $zip->addFile($tmp_dir.$filename, $filename);
                    }
                    $zip->close();
                }
            }
            if (file_exists($file)) {
                waFiles::readFile($file, $name);
            }

            header('HTTP/1.0 404 Not Found');
            exit('Нет файлов, доступных для скачивания.');
        }

        waFiles::readFile($plugin->path($name), (waRequest::get('mode') == 'view') ? null : $name);
    }
}
