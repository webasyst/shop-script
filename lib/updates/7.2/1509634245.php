<?php

$target_path = wa()->getDataPath('promos/', true, 'shop');
$source_path = wa()->getAppPath('lib/config/data/', 'shop');

$target = $target_path.'thumb.php';
if (!file_exists($target)) {
    $file = <<<PHP
<?php
\$file = dirname(__FILE__)."/../../../../"."wa-apps/shop/lib/config/data/promos.thumb.php";

if (file_exists(\$file)) {
    include(\$file);
} else {
    header("HTTP/1.0 404 Not Found");
}

PHP;

    waFiles::write($target, $file);
}


$target = $target_path.'.htaccess';
if (!file_exists($target)) {
    waFiles::copy($source_path.'.htaccess', $target);
}
