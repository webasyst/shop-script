<?php

class shopMigratePluginBackendWbImportAdvanceController extends waJsonController
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
            $importer = shopMigratePluginWbImportFactory::create();
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            $this->response = $importer->advance($run_id);
        } catch (Throwable $e) {
            waLog::log(
                sprintf('[WbImporter] AJAX batch failed: %s: %s at %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()),
                'shop/plugins/migrate/migrate_wb.log'
            );
            $this->setError($e->getMessage());
        }
    }
}
