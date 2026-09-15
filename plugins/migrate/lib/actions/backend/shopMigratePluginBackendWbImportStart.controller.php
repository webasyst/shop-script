<?php

class shopMigratePluginBackendWbImportStartController extends waJsonController
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
            $settings = new shopMigratePluginWbSettings();
            if (!$settings->hasToken()) {
                throw new waException(_wp('Save a Wildberries API token first.'));
            }
            $snapshot_id = $settings->getCurrentSnapshotId();
            if (!$snapshot_id) {
                throw new waException(_wp('Get a Wildberries snapshot before starting import.'));
            }
            $posted_snapshot_id = waRequest::post('snapshot_id', 0, waRequest::TYPE_INT);
            if ($posted_snapshot_id <= 0 || $posted_snapshot_id !== $snapshot_id) {
                throw new waException(_wp('The Wildberries snapshot has changed. Reload the page and try again.'));
            }
            $posted_options = array();
            $option_fields = array(
                'mode'               => waRequest::TYPE_STRING_TRIM,
                'log_mode'           => waRequest::TYPE_STRING_TRIM,
                'feature_mode'       => waRequest::TYPE_STRING_TRIM,
                'feature_force_text' => waRequest::TYPE_INT,
                'image_mode'         => waRequest::TYPE_STRING_TRIM,
            );
            foreach ($option_fields as $field => $type) {
                if (waRequest::issetPost($field)) {
                    $posted_options[$field] = waRequest::post($field, null, $type);
                }
            }
            if ($posted_options) {
                $settings->saveImportOptions($posted_options);
            }
            $kind = waRequest::post('kind', shopMigratePluginWbImporter::KIND_PRODUCTS, waRequest::TYPE_STRING_TRIM);
            $this->response = shopMigratePluginWbImportFactory::create($settings)->start(
                $snapshot_id,
                $kind,
                $settings->getImportOptions()
            );
        } catch (Throwable $e) {
            $this->setError($e->getMessage());
        }
    }
}
