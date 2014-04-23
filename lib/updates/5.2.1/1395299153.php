<?php

$model = new waModel();
$model->exec("ALTER TABLE shop_currency CHANGE rate rate DECIMAL( 18, 10 ) NOT NULL DEFAULT '1.0000000000'");