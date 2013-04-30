<?php

class shopCml1cPluginBackendDownloadController extends waController
{
    public function execute()
    {
        $name = basename(waRequest::get('file', 'export.xml'));
        $file = wa()->getPlugin('cml1c')->path($name);
        waFiles::readFile($file, (waRequest::get('mode') == 'view') ? null : $name);
    }
}
