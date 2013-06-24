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
        if ($name = tempnam($path, 'cml1c')) {
            unlink($name);
            if (($ext = pathinfo($original_name, PATHINFO_EXTENSION)) && preg_match('/^\w+$/', $ext)) {
                $name .= '.' . $ext;
            }
            $file->moveTo($name);
        } else {
            throw new waException(_w('Error file upload'));
        }

        return array(
            'filename' => htmlentities(basename($name), ENT_QUOTES, 'utf-8'),
            'original_name' => htmlentities(basename($original_name), ENT_QUOTES, 'utf-8'),
            'size' => waFiles::formatSize($file->size),
        );
    }
}
