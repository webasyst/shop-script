<?php

$model = new waModel();
$model->exec("UPDATE shop_order SET paid_month = MONTH(paid_date), paid_quarter = floor((MONTH(paid_date) - 1) / 3) + 1 WHERE paid_date IS NOT NULL");
