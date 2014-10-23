<?php

$model = new waModel();
$rows = $model->query("SELECT o.id, o.create_datetime, MIN(l.datetime) as datetime FROM shop_order o
               JOIN shop_order_log l ON o.id = l.order_id
               WHERE o.paid_year = 1970 AND (l.action_id = 'pay' OR l.action_id = 'complete')
               GROUP BY o.id");

$on_created = $this->getOption('order_paid_date') == 'create';

$order_model = new shopOrderModel();
foreach ($rows as $row) {
    if ($on_created) {
        $time = strtotime($row['create_datetime']);
    } else {
        $time = strtotime($row['datetime']);
    }
    $order_model->updateById($row['id'], array(
        'paid_year' => date('Y', $time),
        'paid_quarter' => floor((date('n', $time) - 1) / 3) + 1,
        'paid_month' => date('n', $time),
        'paid_date' => date('Y-m-d', $time),
    ));
}