<?php

$model = new waModel();
$service_variants_model = new shopServiceVariantsModel();
$product_services_model = new shopProductServicesModel();

// one service - at least one variant
$sql = "SELECT s.* FROM `shop_service` s
            LEFT JOIN `shop_service_variants` sv ON s.id = sv.service_id
        WHERE sv.id IS NULL";
foreach ($model->query($sql) as $service) {
    $id = $service_variants_model->insert(array(
        'service_id' => $service['id'],
        'name' => '',
        'price' => $service['price'],
        'primary_price' => $service['primary_price']
    ));
}
// default variant_id
$model->exec("
    UPDATE `shop_service` s JOIN `shop_service_variants` sv ON s.id = sv.service_id
    SET s.variant_id = sv.id
    WHERE s.variant_id IS NULL
");

// one service - at least one variant, so correct shop_product_services
$model->exec("
    UPDATE `shop_product_services` ps JOIN `shop_service` s ON s.id = ps.service_id
    SET ps.service_variant_id = s.variant_id
    WHERE ps.service_variant_id IS NULL
");

foreach ($product_services_model->query($sql) as $item) {
    $product_services_model->updateById($item['id'], array('status' => (int)($item['status'] > 0)));
}

$model->exec("
    ALTER TABLE `shop_product_services` CHANGE service_variant_id service_variant_id INT (11) NOT NULL
");
$model->exec("
    ALTER TABLE `shop_service` CHANGE variant_id variant_id INT(11) NOT NULL
");
$model->exec("
    ALTER TABLE `shop_service_variants` CHANGE price price decimal(15,4) NOT NULL DEFAULT 0
");
$model->exec("
    ALTER TABLE `shop_service_variants` CHANGE primary_price primary_price decimal(15,4) NOT NULL DEFAULT 0
");

try {
    $sql = "UPDATE `shop_service` SET price = primary_price";
    $model->exec($sql);
    $model->exec("
        ALTER TABLE `shop_service` DROP primary_price
    ");
} catch (waDbException $e) {

}

$sql = "UPDATE `shop_service` s
        JOIN `shop_service_variants` sv ON s.id = sv.service_id AND s.variant_id = sv.id
        SET s.price = sv.primary_price
        WHERE s.price != sv.primary_price";
$model->exec($sql);

$sql = "UPDATE `shop_order_items` oi
        JOIN `shop_service` s ON oi.service_id = s.id
        SET oi.service_variant_id = s.variant_id
        WHERE oi.type = 'service' AND service_variant_id IS NULL";
$model->exec($sql);