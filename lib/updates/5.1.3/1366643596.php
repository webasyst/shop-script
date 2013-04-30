<?php

$sql = "
CREATE TABLE IF NOT EXISTS `shop_product_params` (
  `product_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`product_id`,`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
";

$model = new waModel();
$model->query($sql);