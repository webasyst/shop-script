<?php

$model = new waModel();
$model->exec("UPDATE `shop_set` SET count = 0 WHERE count < 0");