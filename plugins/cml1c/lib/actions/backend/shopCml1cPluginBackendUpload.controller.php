<?php

class shopCml1cPluginBackendUploadController extends shopUploadController
{

    protected function save(waRequestFile $file)
    {
        /**
         * @var shopCml1cPlugin $plugin
         */
        $plugin = wa()->getPlugin('cml1c');
        $path = $plugin->path('');
        waFiles::create($path);
        $original_name = $file->name;
        $files = array();
        if ($name = tempnam($path, 'cml1c')) {
            unlink($name);
            $ext = pathinfo($original_name, PATHINFO_EXTENSION);
            if ($ext && preg_match('/^\w+$/', $ext)) {
                $name .= '.'.$ext;
            }
            $file->moveTo($name);
            switch ($ext) {
                case 'zip':
                    if (!function_exists('zip_open')) {
                        throw new waException("Для чтения zip файлов требуется включения поддержки zip архивов со стороны PHP");
                    }
                    if (($zip = zip_open($name)) && is_resource($zip)) {
                        while ($entry = zip_read($zip)) {
                            $filename = zip_entry_name($entry);
                            $filename = iconv('CP866', 'UTF-8', $filename);

                            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION) == 'xml')) {
                                $files[] = array(
                                    'name'  => $filename,
                                    'size'  => waFiles::formatSize(zip_entry_filesize($entry)),
                                );
                            }
                        }
                        zip_close($zip);
                        if (empty($files)) {
                            throw new waException("В zip архиве не найдено ни одного XML файла");
                        }
                    } else {
                        throw new waException("Ошибка чтения zip файла");
                    }
                    break;
                case 'xml':
                    break;
                default:
                    throw new waException(sprintf("Неподдерживаемый тип файлов: %s", $ext));
                    break;
            }

        } else {
            throw new waException(_w('Error file upload'));
        }

        return array(
            'files'         => $files,
            'filename'      => htmlentities(basename($name), ENT_QUOTES, 'utf-8'),
            'original_name' => htmlentities(basename($original_name), ENT_QUOTES, 'utf-8'),
            'size'          => waFiles::formatSize($file->size),
        );
    }
}
