<?php

$model = new waModel();
$model->exec("ALTER TABLE shop_order_log CHANGE text text TEXT NULL");