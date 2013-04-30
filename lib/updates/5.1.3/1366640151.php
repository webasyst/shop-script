<?php

$model = new waModel();
$model->exec("DELETE FROM wa_contact_settings WHERE app_id = 'shop' AND name = 'all:sort'");