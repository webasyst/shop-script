<?php

class shopCsvProductdownloadController extends waController
{
    public function execute()
    {
        $name = basename(waRequest::get('file', 'export.csv'));
        $file = wa()->getTempPath('csv/download/'.$name);
        waFiles::readFile($file, $name);
    }
}
