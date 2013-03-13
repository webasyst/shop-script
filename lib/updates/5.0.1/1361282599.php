<?php

// remove old file
waFiles::delete($this->getAppPath('lib/actions/settings/shopSettingsNotifications.action.php'), true);

// create new tables for notifications
$model = new waModel();
$model->exec("CREATE TABLE IF NOT EXISTS `shop_notification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `event` varchar(64) NOT NULL,
  `transport` enum('email','sms','http') NOT NULL DEFAULT 'email',
  PRIMARY KEY (`id`),
  KEY `event` (`event`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8");

$model->exec("CREATE TABLE IF NOT EXISTS `shop_notification_params` (
  `notification_id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`notification_id`,`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");