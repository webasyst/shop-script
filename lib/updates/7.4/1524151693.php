<?php
$model = new shopSalesModel();
$log = 'shop/updates.log';
$date_start = '2018-04-16 00:00:00';
$sql = <<<SQL
SELECT o.id, o.rate, o.currency
FROM shop_order o
  LEFT JOIN shop_order_params p ON p.order_id = o.id AND p.name = 'storefront'
WHERE
  (
  `create_datetime` > s:date_start
  OR 
  `update_datetime` > s:date_start
  )
  AND
  (p.value = '' OR p.value IS NULL)
SQL;

$order_data = $model->query($sql, compact('date_start'))->fetchAll('id');
if ($order_data) {
    $message = sprintf('Attempt to restore `shop_order_items.purchase_price` for %d orders', count($order_data));
    waLog::log($message, $log);
    foreach ($order_data as $id => $data) {
        $sql = <<<SQL
UPDATE shop_order_items i
JOIN shop_product_skus s ON s.id=i.sku_id
SET i.purchase_price = s.purchase_price * s.primary_price/s.price * f:rate

WHERE
i.order_id=i:id
AND
i.type='product'
AND
i.purchase_price > 0
AND
s.price > 0
SQL;
        $count = $model->query($sql, $data)->affectedRows();

        $message = sprintf("Fixed %d `shop_order_items.purchase_price` records for order # %d\n", $count, $id);
        waLog::log($message, $log);
    }
}

$model->deletePeriod($date_start);
