<?php

class shopImportexportAddController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->isAdmin('shop') && !wa()->getUser()->getRights('shop', 'type.%')) {
            throw new waRightsException('Access denied');
        }
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
