<?php

$model = new waModel();
try {
    $model->exec('ALTER TABLE shop_order ADD INDEX contact_id (contact_id)');
} catch (waDbException $e) {
}

try {
    $model->exec('ALTER TABLE shop_order ADD INDEX state_id (state_id)');
} catch (waDbException $e) {
}
