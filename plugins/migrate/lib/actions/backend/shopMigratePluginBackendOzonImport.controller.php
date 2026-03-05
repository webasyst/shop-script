<?php

class shopMigratePluginBackendOzonImportController extends waJsonController
{
    public function execute()
    {
        try {
            $settings = new shopMigratePluginOzonSettings();
            $snapshot_id = $settings->getCurrentSnapshotId();
            if (!$snapshot_id) {
                throw new waException(_wp('No snapshot available for import.'));
            }

            $repository = new shopMigratePluginOzonSnapshotRepository();
            $type_mapper = new shopMigratePluginOzonTypeMapper($repository, $settings);
            $category_mapper = new shopMigratePluginOzonCategoryMapper($repository, $settings);
            $stock_mapper = new shopMigratePluginOzonStockMapper($repository, $settings);
            $feature_mapper = new shopMigratePluginOzonFeatureMapper($repository, $settings);

            $importer = new shopMigratePluginOzonImporter(
                $repository,
                $settings,
                $type_mapper,
                $category_mapper,
                $stock_mapper,
                $feature_mapper
            );

            $result = $importer->import($snapshot_id);
            $this->response = $result;
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }
}
