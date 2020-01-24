<?php

class shopProductBuybuttonsAction extends waViewAction
{
    public function execute()
    {
        $id = (int) $this->getRequest()->get('id');
        $product = new shopProduct($id);
        $images = $product->getImages('thumb', false);
        $image = reset($images);
        $image_url = $image ? $image['url_thumb'] : '';
        $this->view->assign(array(
            'id' => $id,
            'name' => $product['name'],
            'storefronts' => $this->getStorefronts($product),
            'img_url' => $image_url,
            'locales' => waLocale::getAll(true),
            'app_static_url' => wa()->getAppStaticUrl('shop', true),
            'app_storefront' => wa()->getRouteUrl("shop/frontend/buybuttons", array(), true),
            'preview_controller' => wa()->getConfig()->getRootUrl() . wa()->getConfig()->getBackendUrl() .
                '/shop/?module=frontend&action=buybuttons&preview=1'
        ));
    }

    public function getStorefronts($product)
    {
        $storefronts = array();
        foreach (wa()->getRouting()->getByApp('shop') as $domain => $domain_routes) {

            $cdn = '';
            $domain_config_path = wa()->getConfig()->getConfigPath('domains/' . $domain . '.php', true, 'site');
            if (file_exists($domain_config_path)) {
                $domain_config = include($domain_config_path);
                if (!empty($domain_config['cdn'])) {
                    $cdn = rtrim($domain_config['cdn'], '/');
                }
            }

            foreach ($domain_routes as $route) {
                $controller_url = wa()->getRouteUrl("shop/frontend/buybuttons", array(), true, $domain, $route['url']);
                $product_url = wa()->getRouteUrl("shop/frontend/product", array('product_url' => $product['url']), true, $domain, $route['url']);
                $root_url = rtrim($domain.'/'.$route['url'], '/*').'/';
                $static_url = '/wa-apps/shop/';
                if ($cdn) {
                    $static_url = rtrim($cdn, '/') . $static_url;
                } else {
                    $protocol = 'http://';
                    if (waRequest::isHttps()) {
                        $protocol = 'https://';
                    }
                    $static_url = $protocol . trim($domain, '/') . $static_url;
                }
                $storefronts[] = array(
                    'static_url'       => $static_url,
                    'root_url'         => $root_url,
                    'root_url_decoded' => waIdna::dec($root_url),
                    'domain'           => $domain,
                    'product_url'      => $product_url,
                    'controller_url'   => $controller_url
                );
            }
        }
        return array_reverse($storefronts);
    }
}
