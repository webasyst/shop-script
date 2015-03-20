<?php

$order_model = new shopOrderModel();
$sql = "SELECT * FROM shop_order WHERE state_id = 'deleted' AND paid_date IS NOT NULL";
foreach ($order_model->query($sql) as $order) {
    $order_id = $order['id'];
    shopCustomer::recalculateTotalSpent($order['contact_id']);
    $order_model->updateById($order_id, array(
        'paid_date' => null,
        'paid_year' => null,
        'paid_month' => null,
        'paid_quarter' => null,
    ));
    $order_model->returnProductsToStocks($order_id);
    shopAffiliate::cancelBonus($order_id);
    $order_model->recalculateProductsTotalSales($order_id);
}