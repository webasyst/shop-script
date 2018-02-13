<?php

$spm = new shopProductModel();
$meta_data = $spm->getMetadata();

try {
    if ($meta_data['badge']['type'] == 'varchar') {
        $spm->exec("ALTER TABLE `shop_product` MODIFY `badge` text CHARACTER SET utf8 COLLATE utf8_general_ci");
    }
} catch (Exception $e) {

}
