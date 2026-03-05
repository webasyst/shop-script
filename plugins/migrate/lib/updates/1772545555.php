<?php
$plugin_root = dirname(__FILE__) . '/../../';
$files = [
    'lib/shopMigrateOzon/Api/shopMigrateOzonApiClient.class.php',
    'lib/shopMigrateOzon/Import/shopMigrateOzonImporter.class.php',
    'lib/shopMigrateOzon/Mapping/shopMigrateOzonCategoryMapper.class.php',
    'lib/shopMigrateOzon/Mapping/shopMigrateOzonFeatureMapper.class.php',
    'lib/shopMigrateOzon/Mapping/shopMigrateOzonStockMapper.class.php',
    'lib/shopMigrateOzon/Mapping/shopMigrateOzonTypeMapper.class.php',
    'lib/shopMigrateOzon/Model/shopMigrateOzonAttributeValuesModel.class.php',
    'lib/shopMigrateOzon/Model/shopMigrateOzonAttributesModel.class.php',
    'lib/shopMigrateOzon/Model/shopMigrateOzonCategoriesModel.class.php',
    'lib/shopMigrateOzon/Model/shopMigrateOzonCategoryMapModel.class.php',
    'lib/shopMigrateOzon/Model/shopMigrateOzonFeatureMapModel.class.php',
    'lib/shopMigrateOzon/Model/shopMigrateOzonModel.class.php',
    'lib/shopMigrateOzon/Model/shopMigrateOzonProductMapModel.class.php',
    'lib/shopMigrateOzon/Model/shopMigrateOzonProductsModel.class.php',
    'lib/shopMigrateOzon/Model/shopMigrateOzonSnapshotsModel.class.php',
    'lib/shopMigrateOzon/Model/shopMigrateOzonStockMapModel.class.php',
    'lib/shopMigrateOzon/Model/shopMigrateOzonStocksModel.class.php',
    'lib/shopMigrateOzon/Model/shopMigrateOzonTypeMapModel.class.php',
    'lib/shopMigrateOzon/Model/shopMigrateOzonWarehousesModel.class.php',
    'lib/shopMigrateOzon/Repository/shopMigrateOzonSnapshotRepository.class.php',
    'lib/shopMigrateOzon/Service/shopMigrateOzonHelper.class.php',
    'lib/shopMigrateOzon/Service/shopMigrateOzonLogger.class.php',
    'lib/shopMigrateOzon/Service/shopMigrateOzonSettings.class.php',
    'lib/shopMigrateOzon/Service/shopMigrateOzonSnapshotBuilder.class.php',
    'lib/transport/shopMigrateOzonTransport.class.php',
];

foreach ($files as $file) {
    try {
        if (file_exists($plugin_root.$file)) {
            waFiles::delete($plugin_root.$file, true);
        }
    } catch (Throwable $e) {
    }
}
wa()->getConfig()->clearCache();