<?php
$_installer = new shopInstaller();
$_installer->createTable('shop_product_reviews_images');

try {
    $model = new shopProductReviewsModel();
    $model->exec('select images_count from shop_product_reviews');
} catch (Exception $e) {
    $model->exec("alter table `shop_product_reviews` modify `status` enum('approved', 'deleted', 'moderation') default 'approved' not null");
    $model->exec('alter table `shop_product_reviews` add `images_count` int default 0 null after `name`');
}
