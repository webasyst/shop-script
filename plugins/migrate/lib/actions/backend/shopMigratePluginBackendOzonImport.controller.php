<?php

class shopMigratePluginBackendOzonImportController extends waJsonController
{
    public function execute()
    {
        try {
            $settings = new shopMigrateOzonSettings();
            $snapshot_id = $settings->getCurrentSnapshotId();
            if (!$snapshot_id) {
                throw new waException(_wp('No snapshot available for import.'));
            }

            $repository = new shopMigrateOzonSnapshotRepository();
            $type_mapper = new shopMigrateOzonTypeMapper($repository, $settings);
            $category_mapper = new shopMigrateOzonCategoryMapper($repository, $settings);
            $stock_mapper = new shopMigrateOzonStockMapper($repository, $settings);
            $feature_mapper = new shopMigrateOzonFeatureMapper($repository, $settings);

            $importer = new shopMigrateOzonImporter(
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
