<?php

$model = new waAppSettingsModel();
$fields = $model->get('shop.cml1c', 'update_product_fields', '[]');
$fields = @json_decode($fields, true);
if (!empty($fields['features'])) {
    $fields['weight'] = 'weight';
    $model->set('shop.cml1c', 'update_product_fields', json_encode($fields));
}
