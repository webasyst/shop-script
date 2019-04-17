<?php

$path = wa()->getConfig()->getConfigPath('checkout2.php', true, 'shop');
if (!file_exists($path)) {
    return;
}

$full_config = include($path);

if (!is_array($full_config)) {
    return;
}

foreach ($full_config as $storefront_id => $config) {

    // Mode order settings to shipping block
    if (!array_key_exists('order', $config) || !is_array($config['order']) || empty($config['order']['mode'])) {
        continue;
    }

    $config['shipping']['mode'] = $config['order']['mode'];

    if (!empty($config['order']['fixed_delivery_area'])) {
        $config['shipping']['fixed_delivery_area'] = $config['order']['fixed_delivery_area'];
    }

    if (!empty($config['order']['show_pickuppoint_map'])) {
        $config['shipping']['show_pickuppoint_map'] = $config['order']['show_pickuppoint_map'];
    }

    if (!empty($config['order']['locations_list'])) {
        $config['shipping']['locations_list'] = $config['order']['locations_list'];
    }

    unset(
        $config['order']['mode'],
        $config['order']['fixed_delivery_area'],
        $config['order']['show_pickuppoint_map'],
        $config['order']['locations_list']
    );

    // Update shipping.show_pickuppoint_map setting
    if ($config['shipping']['mode'] == shopCheckoutConfig::SHIPPING_MODE_TYPE_MINIMUM) {
        $config['shipping']['show_pickuppoint_map'] = shopCheckoutConfig::PICKUPPOINT_MAP_TYPE_NEVER;
    }

    $full_config[$storefront_id] = $config;
}

waUtils::varExportToFile($full_config, $path);