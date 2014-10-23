<?php

class shopCml1cPluginBackendDownloadController extends waController
{
    public function execute()
    {
        $name = basename(waRequest::get('file', 'export.xml'));
        /**
         * @var shopCml1cPlugin $plugin
         */
        $plugin = wa()->getPlugin('cml1c');
        waFiles::readFile($plugin->path($name), (waRequest::get('mode') == 'view') ? null : $name);
    }
}
