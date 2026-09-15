<?php

class shopMigratePluginBackendWbImportCancelController extends waJsonController
{
    public function execute()
    {
        try {
            if (!$this->getUser()->getRights('shop', 'importexport')) {
                throw new waRightsException(_wp('Access denied.'));
            }
            if (waRequest::method() !== 'post') {
                throw new waRightsException(_wp('Access denied.'));
            }
            $run_id = waRequest::post('run_id', 0, waRequest::TYPE_INT);
            if (!$run_id) {
                throw new waException(_wp('Wildberries import run is missing.'));
            }
            $this->response = shopMigratePluginWbImportFactory::create()->requestCancel($run_id);
        } catch (Throwable $e) {
            $this->setError($e->getMessage());
        }
    }
}
