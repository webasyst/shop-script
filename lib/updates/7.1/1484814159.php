<?php
$m = new waModel();

try {
    $m->query("SELECT `api_token` FROM `shop_push_client` WHERE 0");
} catch (waDbException $e) {
    $m->exec("ALTER TABLE shop_push_client
                ADD `api_token` varchar(32) DEFAULT NULL,
                ADD `create_datetime` datetime DEFAULT NULL");
}
