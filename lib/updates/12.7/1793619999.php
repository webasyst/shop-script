<?php
$installer = new shopInstaller();
$installer->addColumns('shop_order', 'fee');
$installer->addColumns('shop_sales', 'fee');
$installer->createTable('shop_external_entity');
$installer->createTable('shop_order_fees');
