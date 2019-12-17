<?php
class shopImportexportDeleteController extends waJsonController
{
    public function execute()
    {
        $profiles = new shopImportexportHelper(waRequest::post('plugin'));
        $this->response = $profiles->deleteConfig(waRequest::post('profile'));
    }
}
