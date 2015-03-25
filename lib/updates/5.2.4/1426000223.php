<?php

$model = new waModel();
// ON to Full Access
$model->exec("UPDATE wa_contact_rights SET value = 2 WHERE `app_id` = 'shop' AND `name` LIKE 'type.%' AND value = 1");