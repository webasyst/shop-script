<?php
$all_channels = require(__DIR__.'/sales_channels.all.php');
return array_intersect_key($all_channels, [
    'telegram' => 1,
    'pos' => 1,
    'widget' => 1,
    'qr' => 1,
]);