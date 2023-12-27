<?php
$m = new waModel();
try {
    $m->exec("ALTER TABLE `shop_order` ADD KEY `paid_date` (`paid_date`)");
} catch (waDbException $e) {
}
