<?php

$model = new waModel();
try {
    $model->query("SELECT badge FROM shop_product WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_product` ADD  `badge` VARCHAR( 255 ) NULL DEFAULT NULL");
}

try {
    $model->query("SELECT badge_type, badge_code FROM shop_product_images WHERE 0");
    $badges = array(
        0 => 'new',
        1 => 'bestseller',
        2 => 'lowprice'
    );
    foreach ($badges as $t => $c) {
        $sql = "UPDATE shop_product p JOIN shop_product_images i ON p.image_id = i.id
                SET p.badge = '".$t."'
                WHERE i.badge_type = ".$c;
        $model->exec($sql);
    }
    $sql = "UPDATE shop_product p JOIN shop_product_images i ON p.image_id = i.id
            SET p.badge = i.badge_code
            WHERE i.badge_type = 100";
    $model->exec("ALTER TABLE shop_product_images DROP badge_type");
    $model->exec("ALTER TABLE shop_product_images DROP badge_code");
} catch (waDbException $e) {

}

waFiles::delete($this->getAppPath('lib/actions/product/shopProductImageSetBadge.controller.php'), true);
waFiles::delete($this->getAppPath('lib/actions/product/shopProductImageDeleteBadge.controller.php'), true);