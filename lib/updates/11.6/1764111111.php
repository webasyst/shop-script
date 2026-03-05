<?php
//
// In all frontend checkout settings, if Payment step is altogether disabled,
// uncheck "Instant payment after checkout" checkbox (previously named "Auto-redirect to payment").
// Without this update, customers would start seeing payment selection on checkout/success page.
//

$path = wa()->getConfig()->getConfigPath('checkout2.php', true, 'shop');
if (!file_exists($path)) {
    return;
}
$full_config = include($path);
$something_changed = false;

foreach (wa()->getRouting()->getByApp('shop') as $domain => $routes) {
    foreach ($routes as $r) {
        if (empty($r['checkout_storefront_id'])) {
            // ignore storefronts with step-by-step checkout
            continue;
        }
        $checkout_config = new shopCheckoutConfig($r['checkout_storefront_id']);
        if (empty($checkout_config['payment']['used'])) {
            $checkout_config->setData([
                'confirmation' => ['auto_submit' => false],
            ]);
            $full_config[$checkout_config->getStorefront()] = $checkout_config->getStorefrontConfigStorage();
            $something_changed = true;
        }
    }
}
if ($something_changed) {
    waUtils::varExportToFile($full_config, $path);
}
