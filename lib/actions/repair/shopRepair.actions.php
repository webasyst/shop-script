<?php

class shopRepairActions extends waActions
{

    public function __construct()
    {
        if (!$this->getUser()->isAdmin('shop')) {
            throw new waRightsException(_ws('Access denied'));
        }
    }

    public function categoriesAction()
    {
        $model = new shopCategoryModel();
        $model->repair();
        echo "OK";
    }
    
    public function featuresSelectableAction() 
    {
        $model = new waModel();


        $product_features_selectable_model = new shopProductFeaturesSelectableModel();

        // delete unbinded old values in shop_product_features_selectable
        $sql = "SELECT DISTINCT ps.product_id, pf.feature_id FROM `shop_product_skus` ps
                JOIN `shop_product_features` pf ON ps.product_id = pf.product_id AND ps.id = pf.sku_id
                LEFT JOIN `shop_product_features_selectable` fs ON fs.product_id = pf.product_id AND fs.feature_id = pf.feature_id AND fs.value_id = pf.feature_value_id
                WHERE ps.virtual = 1 AND fs.value_id IS NULL";

        foreach ($model->query($sql)->fetchAll() as $key) {
            $product_features_selectable_model->deleteByField($key);
        }

        // insert new actual values in shop_product_features_selectable
        $sql = "SELECT DISTINCT ps.product_id, pf.feature_id, pf.feature_value_id AS value_id FROM `shop_product_skus` ps
                JOIN `shop_product_features` pf ON ps.product_id = pf.product_id AND ps.id = pf.sku_id
                LEFT JOIN `shop_product_features_selectable` fs ON fs.product_id = pf.product_id AND fs.feature_id = pf.feature_id AND fs.value_id = pf.feature_value_id
                WHERE ps.virtual = 1 AND fs.value_id IS NULL";

        foreach ($model->query($sql)->fetchAll() as $item) {
            $product_features_selectable_model->insert($item);
        }
        
        echo "OK";
    }
}