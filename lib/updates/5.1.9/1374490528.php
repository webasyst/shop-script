<?php
$sql = "ALTER TABLE  `shop_feature` CHANGE  `count`  `count` INT( 10 ) UNSIGNED NOT NULL DEFAULT  '0'";
$model = new waModel();
$model->query($sql);