<?php
$installer = new shopInstaller();
$installer->addColumns('shop_category', 'thumb_ext');
$installer->ensureThumbPhp();