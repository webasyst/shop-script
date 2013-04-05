<?php

$model = new waModel();
$model->exec("CREATE TABLE IF NOT EXISTS `shop_product_features_selectable` (
  `product_id` int(11) NOT NULL,
  `feature_id` int(11) NOT NULL,
  `value_id` int(11) NOT NULL,
  PRIMARY KEY (`product_id`,`feature_id`,`value_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
