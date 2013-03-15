<?php

$model = new waModel();
// remove paid_date for refunded orders
$model->exec("UPDATE shop_order
SET paid_year = NULL, paid_month = NULL, paid_quarter = NULL, paid_date = NULL
WHERE state_id = 'refunded'");