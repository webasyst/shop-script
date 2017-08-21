<?php
$model = new waModel();
$sql = <<<SQL
SELECT
  DISTINCT o.contact_id
FROM shop_order_params p
  LEFT JOIN shop_order o
    ON p.order_id = o.id
WHERE
  p.name = 'yandexmarket.id'
SQL;
$contacts = $model->query($sql)->fetchAll('contact_id');
if ($contacts) {
    $contacts = array_keys($contacts);
    $sql = <<<SQL
SELECT
  o.contact_id contact_id,
  MAX(o.id)    last_order_id,
  COUNT(o.id)  number_of_orders
FROM shop_order o
WHERE o.contact_id IN (i:contacts)
GROUP BY o.contact_id
SQL;

    $contacts_data = $model->query($sql, compact('contacts'))->fetchAll('contact_id');
    if ($contacts_data) {
        foreach ($contacts_data as &$data) {
            $data = sprintf('(%d, %d, %d)', $data['contact_id'], $data['last_order_id'], $data['number_of_orders']);
        }
        unset($data);

        $data_chunks = array_chunk($contacts_data, 100);
        foreach ($data_chunks as $data_chunk) {
            $values = implode(', ', $data_chunk);

            $sql = <<<SQL
INSERT into shop_customer (contact_id, last_order_id, number_of_orders)
VALUES {$values}
ON DUPLICATE KEY UPDATE last_order_id = VALUES (last_order_id), number_of_orders = VALUES (number_of_orders);
SQL;
            $model->query($sql);
        }
    }
}
