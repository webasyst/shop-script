<?php

$_fm = new shopFeatureModel();

// ADD GTIN FEATURE AND FILL FEATURE-TYPE RELATIONS
try {

    $_feature_id = $_fm->insert([
        'code' => 'gtin',
        'status' => shopFeatureModel::STATUS_PRIVATE,
        'name' => 'GTIN',
        'type' => shopFeatureModel::TYPE_VARCHAR,
        'selectable' => 0,
        'multiple' => 0,
        'count' => 0,
        'available_for_sku' => 1
    ]);

    $_tm = new shopTypeModel();
    $_type_ids = $_tm->select('id')->fetchAll(null, true);

    $_type_ids[] = 0;
    $_type_ids = array_unique($_type_ids);

    $_tfm = new shopTypeFeaturesModel();

    $_max_sorts = $_tfm->query("SELECT type_id, MAX(sort) FROM `shop_type_features` GROUP BY type_id")->fetchAll('type_id', true);

    $_type_features = [];
    foreach ($_type_ids as $_type_id) {
        $_sort = 0;
        if (isset($_max_sorts[$_type_id])) {
            $_sort = $_max_sorts[$_type_id] + 1;
        }
        $_type_features[] = [
            'type_id' => $_type_id,
            'feature_id' => $_feature_id,
            'sort' => $_sort
        ];
    }

    foreach ($_type_features as $_item) {
        $_tfm->insert($_item, 2);
    }

} catch (waDbException $e) {

}

