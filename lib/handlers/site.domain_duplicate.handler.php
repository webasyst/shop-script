<?php
/**
 * When user dupliates a whole site in Site Premium, Shop should
 * detach checkout config of duplicate settlements.
 */
class shopSiteDomain_duplicateHandler extends waEventHandler
{
    public function execute(&$params)
    {
        $dest_domain = $params['dest_domain'];
        $source_domain = $params['source_domain'];
        $checkout_config_path = wa('shop')->getConfig()->getConfigPath('checkout2.php', true, 'shop');
        if (!file_exists($checkout_config_path) || !is_writable($checkout_config_path)) {
            return; // nothing to do
        }
        $checkout_config = include($checkout_config_path);

        $routing_config_path = wa()->getConfig()->getPath('config', 'routing');
        if (!file_exists($routing_config_path) || !is_writable($routing_config_path)) {
            return; // sould never happen
        }
        $all_domains_routes = include($routing_config_path);
        $old_domain_routes = ifset($all_domains_routes, $source_domain['name'], []);
        $domain_routes = ifset($all_domains_routes, $dest_domain['name'], []);
        if (!$domain_routes || !is_array($domain_routes) || !$old_domain_routes || !is_array($old_domain_routes)) {
            return; // sould never happen
        }

        $old_checkout_storefront_ids = [];
        foreach ($old_domain_routes as $route_index => $route) {
            $app = ifset($route, 'app', null);
            $checkout_storefront_id = ifset($route, 'checkout_storefront_id', null);
            if ($app != 'shop' || !$checkout_storefront_id) {
                continue;
            }
            $old_checkout_storefront_ids[$checkout_storefront_id] = $checkout_storefront_id;
        }

        $something_changed = false;
        foreach($domain_routes as $route_index => $route) {
            $app = ifset($route, 'app', null);
            $checkout_version = ifset($route, 'checkout_version', null);
            $checkout_storefront_id = ifset($route, 'checkout_storefront_id', null);
            if ($app != 'shop' || $checkout_version != 2 || !$checkout_storefront_id || !isset($old_checkout_storefront_ids[$checkout_storefront_id])) {
                continue;
            }

            $something_changed = true;
            $new_checkout_storefront_id = shopCheckoutConfig::generateStorefrontId($dest_domain['name'], ifset($route, 'url', ''));

            if (!empty($checkout_config[$checkout_storefront_id])) {
                $checkout_config[$new_checkout_storefront_id] = $checkout_config[$checkout_storefront_id];
            }
            $all_domains_routes[$dest_domain['name']][$route_index]['checkout_storefront_id'] = $new_checkout_storefront_id;
        }

        if ($something_changed) {
            waUtils::varExportToFile($all_domains_routes, $routing_config_path);
            waUtils::varExportToFile($checkout_config, $checkout_config_path);
        }
    }
}