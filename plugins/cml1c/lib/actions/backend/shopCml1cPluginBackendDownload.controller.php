<?php

class shopCml1cPluginBackendDownloadController extends waController
{
    public function execute()
    {
        $name = basename(waRequest::get('file', 'export.xml'));
        /**
         * @var shopCml1cPlugin $plugin
         */
        $plugin =wa()->getPlugin('cml1c');
        $file = $plugin->path($name);
        waFiles::readFile($file, (waRequest::get('mode') == 'view') ? null : $name);
    }
}
