<?php

$model = new waModel();

try {
    $model->exec("ALTER TABLE shop_category DROP INDEX left_key");
    $model->exec("ALTER TABLE shop_category DROP INDEX right_key");
    $model->exec("ALTER TABLE shop_category ADD INDEX ns_keys (left_key, right_key)");
} catch (waDbException $e) {
}

try {
    $model->exec("ALTER TABLE shop_category DROP INDEX parent_id");
} catch (waDbException $e) {

}