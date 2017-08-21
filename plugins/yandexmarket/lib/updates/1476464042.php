<?php
$model = new waModel();
$sql = <<<SQL
CREATE TABLE IF NOT EXISTS shop_yandexmarket_campaigns
(
    id int(11) DEFAULT '0' NOT NULL,
    name varchar(64) DEFAULT '' NOT NULL,
    value text,
    PRIMARY KEY (id, name)
) ENGINE=MyISAM DEFAULT CHARSET=utf8
SQL;

$model->exec($sql);
