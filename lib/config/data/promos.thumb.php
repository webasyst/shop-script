<?php

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

$file = null;

$request_file = $app_config->getRequestUrl(true, true);
$request_file = preg_replace("@^thumb\.php/?@", '', $request_file);

// /wa-data/public/shop/promos/194.96x96.jpg
// /wa-data/public/shop/promos/01/00/1/promo_5ddd20618dc48316776691.96x96.jpg
$image_pattern = '~^([0-9a-zа-яё\/\-\_\%]+)\.((\d+)(?:x(\d+))?)(@2x)?\.([a-z]{3,4})$~ui';

if (preg_match($image_pattern, $request_file, $matches)) {

    $info = array(
        'id'   => $matches[1],
        'ext'  => $matches[6],
        'size' => $matches[2],
        '2x'   => !empty($matches[5]),
    );

    $name = sprintf('%s.%s', $info['id'], $info['ext']);

    $path = wa()->getDataPath('promos/', true, 'shop');

    $source = $path.$name;
    if (file_exists($source)) {
        $file = $path.sprintf('%s.%s.%s', $info['id'], $info['size'], $info['ext']);

        $options = array(
            'thumbs_on_demand' => $app_config->getOption('image_thumbs_on_demand'),
            'max_size'         => $app_config->getOption('image_max_size'),
        );

        if (!file_exists($file)) {
            if ($options['thumbs_on_demand']) {
                $image = shopImage::generateThumb($source, $info['size'], $options['max_size']);
                if ($image) {
                    if (method_exists($image, 'fixImageOrientation')) {
                        $image->fixImageOrientation();
                    }
                    $quality = $app_config->getSaveQuality();
                    $image->save($file, $quality);
                    clearstatcache();
                } else {
                    $file = $source;
                }
            } else {
                $file = $source;
            }
        }
    }
}


if ($file && file_exists($file)) {
    waFiles::readFile($file);
} else {
    header("HTTP/1.0 404 Not Found");
    exit;
}
