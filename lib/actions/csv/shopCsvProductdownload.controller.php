<?php

class shopCsvProductdownloadController extends waController
{
    public function execute()
    {
        $name = basename(waRequest::get('file', 'export.csv'));
        $profile = waRequest::get('profile', 0, waRequest::TYPE_INT);

        $file = wa()->getTempPath('csv/download/'.$profile.'/'.$name);
        waFiles::readFile($file, $name);
    }
}
