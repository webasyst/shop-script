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

            $result = $importer->import($snapshot_id, array(
                'batch'      => (bool) waRequest::post('batch', 0, waRequest::TYPE_INT),
                'batch_size' => waRequest::post('batch_size', shopMigratePluginOzonImporter::DEFAULT_BATCH_SIZE, waRequest::TYPE_INT),
                'cursor'     => waRequest::post('cursor', 0, waRequest::TYPE_INT),
                'image_continuation' => (bool) waRequest::post('image_continuation', 0, waRequest::TYPE_INT),
                'image_offset'       => waRequest::post('image_offset', 0, waRequest::TYPE_INT),
                'image_host'         => waRequest::post('image_host', '', waRequest::TYPE_STRING_TRIM),
            ));
            $this->response = $result;
        } catch (Throwable $e) {
            waLog::log(
                sprintf(
                    '[OzonImporter] AJAX batch failed: %s: %s at %s:%d',
                    get_class($e),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ),
                shopMigratePluginOzonLogger::LOG_FILE
            );
            $this->setError($e->getMessage());
        }
    }
}
