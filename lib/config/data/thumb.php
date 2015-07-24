<?php
/**
 * @todo check allowed sizes
 * @todo use resize options (quiality and filters)
 * @todo use error handlers to display error while resize
 */

$path = realpath(dirname(__FILE__)."/../../../../../");
$config_path = $path."/wa-config/SystemConfig.class.php";
if (!file_exists($config_path)) {
    header("Location: ../../../wa-apps/shop/img/image-not-found.png");
    exit;
}

require_once($config_path);
$config = new SystemConfig();
waSystem::getInstance(null, $config);
/**
 * @var shopConfig $app_config
 */
$app_config = wa('shop')->getConfig();
$request_file = $app_config->getRequestUrl(true, true);
$request_file = preg_replace("@^thumb.php(/products)?/?@", '', $request_file);
$protected_path = wa()->getDataPath('products/', false, 'shop');
$public_path = wa()->getDataPath('products/', true, 'shop');

$main_thumb_file = false;
$file = false;
$size = false;
$enable_2x = false;
if (preg_match('#((?:\d{2}/){2}([0-9]+)/images/)([0-9]+)/([a-zA-Z0-9_\.-]+)\.(\d+(?:x\d+)?)(@2x)?\.([a-z]{3,4})#i', $request_file, $matches)) {
    if ($matches[3] === $matches[4]) {
        $n = $matches[3];
    } else {
        $n = $matches[3].'.'.$matches[4];
    }
    $file = $matches[1].$n.'.'.$matches[7];
    $size = $matches[5];
    $gen_thumbs = $app_config->getOption('image_thumbs_on_demand');

    if ($file && !$gen_thumbs) {
        $thumbnail_sizes = $app_config->getImageSizes();
        if (in_array($size, $thumbnail_sizes) === false) {
            $file = false;
        }
    }
    if ($matches[6] && $app_config->getOption('enable_2x')) {
        $enable_2x = true;
        $size = explode('x', $size);
        foreach ($size as &$s) {
            $s *= 2;
        }
        unset($s);
        $size = implode('x', $size);
    }
}
wa()->getStorage()->close();

$original_path = $protected_path.$file;
$thumb_path = $public_path.$request_file;
if ($file && file_exists($original_path) && !file_exists($thumb_path)) {
    $thumbs_dir = dirname($thumb_path);
    if (!file_exists($thumbs_dir)) {
        waFiles::create($thumbs_dir);
    }
    $max_size = $app_config->getOption('image_max_size');
    if ($max_size && $enable_2x) {
        $max_size *= 2;
    }
    $image = shopImage::generateThumb($original_path, $size, $max_size);
    if ($image) {
        $image->save($thumb_path, $app_config->getSaveQuality($enable_2x));
        clearstatcache();
    }
}

if ($file && file_exists($thumb_path)) {
    waFiles::readFile($thumb_path);
} else {
    header("HTTP/1.0 404 Not Found");
    exit;
}
