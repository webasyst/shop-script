<?php
try {
    $m = new waModel();
    $sql = "CREATE TABLE IF NOT EXISTS `shop_push_client` (
              `contact_id` int(11) NOT NULL,
              `client_id` varchar(64) NOT NULL,
              `shop_url` varchar(255) NOT NULL,
              UNIQUE `client` (`client_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8";
    $m->exec($sql);
} catch (waDbException $e) {
}