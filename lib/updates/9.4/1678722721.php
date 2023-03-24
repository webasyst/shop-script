<?php

$rights_model = new waContactRightsModel();
$rights_model->updateByField([
    'app_id' => 'shop',
    'name' => 'orders',
    'value' => 1
], ['value' => shopRightConfig::RIGHT_ORDERS_FULL]);
