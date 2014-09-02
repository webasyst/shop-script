<?php
class shopImportexportDeleteController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->isAdmin('shop') && !wa()->getUser()->getRights('shop', 'type.%')) {
            throw new waRightsException('Access denied');
        }
        $profiles = new shopImportexportHelper(waRequest::post('plugin'));
        $this->response = $profiles->deleteConfig(waRequest::post('profile'));
    }
}
