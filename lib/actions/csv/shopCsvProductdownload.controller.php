<?php

class shopCsvProductdownloadController extends waController
{
    public function execute()
    {
        $name = 'export.csv';
        $file = wa()->getDataPath('temp/csv/download/'.$name);
        waFiles::readFile($file, $name);
    }
}
