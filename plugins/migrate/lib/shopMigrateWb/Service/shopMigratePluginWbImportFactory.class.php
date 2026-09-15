<?php

class shopMigratePluginWbImportFactory
{
    public static function create(shopMigratePluginWbSettings $settings = null)
    {
        $settings = $settings ?: new shopMigratePluginWbSettings();
        $repository = new shopMigratePluginWbSnapshotRepository();
        $logger = new shopMigratePluginWbLogger($settings->getLogMode());
        return new shopMigratePluginWbImporter(
            $repository,
            $settings,
            new shopMigratePluginWbTypeMapper($repository),
            new shopMigratePluginWbCategoryMapper($repository),
            new shopMigratePluginWbStockMapper($repository),
            new shopMigratePluginWbFeatureMapper($repository),
            $logger
        );
    }
}
