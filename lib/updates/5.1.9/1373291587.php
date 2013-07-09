<?php
$model = new waModel();
$sqls = array();
$sqls[] = "ALTER TABLE  `shop_type_features` ADD INDEX `feature_id` (`feature_id` )";
$sqls[] = <<<SQL
CREATE TABLE IF NOT EXISTS `shop_feature_values_range` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `feature_id` INT(11) NOT NULL,
  `sort` INT(11) NOT NULL DEFAULT '0',
  `begin` DOUBLE NULL, #Null for
  `end` DOUBLE NULL,
  `unit` VARCHAR(255) NOT NULL,
  `type` VARCHAR(16) NOT NULL,
  `begin_base_unit` DOUBLE NULL,
  `end_base_unit` DOUBLE NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `feature_id` (`feature_id`,`begin`, `end`,`unit`,`type`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
SQL;
foreach ($sqls as $sql) {
    try {
        $model->exec($sql);
    } catch (waDbException $e) {

    }
}

