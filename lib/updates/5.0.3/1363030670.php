<?php
$model = new waModel();
$model->query("UPDATE `shop_order_params` t1 LEFT JOIN `shop_order_params` t2
  ON t1.order_id = t2.order_id AND t2.name = REPLACE(t1.name, 'payment_address','billing_address')
  SET t1.`name` = REPLACE(t1.`name`,'payment_address','billing_address')
  WHERE t1.`name` LIKE 'payment_address%' AND t2.name IS NULL");

$model->query("UPDATE `shop_order_params` t1 LEFT JOIN `shop_order_params` t2
  ON t1.order_id = t2.order_id AND t2.name = REPLACE(t1.name, '.address','.street')
  SET t1.`name` = REPLACE(t1.`name`,'.address','.street')
  WHERE t1.`name` LIKE '%.address' AND t2.name IS NULL");

