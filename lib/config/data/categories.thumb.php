<?php

$notFound = function() {
    http_response_code(404);
    exit;
};

$path = realpath(dirname(__FILE__)."/../../../../../");
$config_path = $path."/wa-config/SystemConfig.class.php";
// Maybe __FILE__ is resolved symlink - try process this case
if (!file_exists($config_path)) {
    $script_dir_name = dirname($_SERVER['SCRIPT_FILENAME']); // wa-data/public/shop/categories
    $system_path = realpath($script_dir_name . '/../../../../');
    $config_path = $system_path."/wa-config/SystemConfig.class.php";
}
if (!file_exists($config_path)) {
    $notFound();
}

require_once($config_path);
waSystem::getInstance(null, new SystemConfig());
/** @var shopConfig $app_config */
$app_config = wa('shop')->getConfig();

$request_file = $app_config->getRequestUrl(true, true);
$request_file = preg_replace("@^thumb\.php/?@", '', $request_file);

$protected_path = wa()->getDataPath('categories/', false, 'shop');
$public_path = wa()->getDataPath('categories/', true, 'shop');
wa()->getStorage()->close();

// /wa-data/public/shop/categories/73/08/873/images/1/1.96x96.jpg
$image_pattern = '#^((?:\d{2}/){2}([0-9]+)/images/([0-9]+)/)([a-zA-Z0-9_\.-]+)\.(\d+(?:x\d+)?)(@2x)?\.([a-z]{3,4})$#i';
if (preg_match($image_pattern, $request_file, $matches)) {

    $info = array(
        'category_id'   => $matches[2],
        'image_id'  => $matches[3],
        'path' => $matches[1],
        'prefix' => $matches[4],
        'size' => $matches[5],
        'is_2x'   => !empty($matches[6]),
        'ext'   => $matches[7],
    );
    if ($info['prefix'] !== $info['image_id']) {
        $notFound(); // maybe allow original filename in future
    }

    $size = $info['size'];
    $allowed_sizes = $app_config->getOption('category_image_sizes');
    if (!empty($allowed_sizes) && !in_array($size, $allowed_sizes)) {
        $notFound();
    }
    $enable_2x = $info['is_2x'] && $app_config->getOption('enable_2x');
    if ($enable_2x) {
        $size = explode('x', $size);
        foreach ($size as &$s) {
            $s *= 2;
        }
        unset($s);
        $size = implode('x', $size);
    }

    $source = $protected_path.$info['path'].sprintf('%s.%s', $info['image_id'], $info['ext']);
    $destination = $public_path.$request_file;

    if (file_exists($source) && !file_exists($destination)) {
        $max_size = $app_config->getOption('image_max_size');
        if ($max_size && $enable_2x) {
            $max_size *= 2;
        }

        $image = shopImage::generateThumb($source, $size, $max_size);
        if ($image) {
            if (method_exists($image, 'fixImageOrientation')) {
                $image->fixImageOrientation();
            }
            waFiles::create($destination);
            $quality = $app_config->getSaveQuality();
            if (!file_exists($destination)) {
                $image->save($destination, $quality);
                clearstatcache();
            }
        }
    }
}

if (file_exists($destination)) {
    waFiles::readFile($destination);
} else {
    $notFound();
}
