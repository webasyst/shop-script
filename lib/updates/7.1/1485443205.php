<?php
$sql = <<<SQL
SELECT DISTINCT `o`.`contact_id`
FROM `shop_order` `o`
  JOIN `shop_order_log` `l` ON `l`.`order_id` = `o`.`id`
WHERE `l`.`action_id` = 'refund'
SQL;
$model = new shopCustomerModel();
$contacts = $model->query($sql)->fetchAll();

foreach ($contacts as $contact) {
    $model->recalcTotalSpent($contact['contact_id']);
}
