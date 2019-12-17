<?php

class shopImportexportAddController extends waJsonController
{
    public function execute()
    {
        $profiles = new shopImportexportHelper(waRequest::post('plugin'));
        $config = array();
        $info = array();
        if ($hash = waRequest::post('hash')) {
            $info = shopImportexportHelper::getCollectionHash($hash);
            $config['hash'] = $info['hash'];
        }
        $this->response = $profiles->addConfig(ifset($info['name']), null, $config);
    }
}
