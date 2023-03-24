<?php

class shopProductBuybuttonsAction extends waViewAction
{
    public function execute()
    {
        $id = (int) $this->getRequest()->get('id');
        $product = new shopProduct($id);
        $images = $product->getImages('thumb', true);
        $image = reset($images);
        if ($image) {
            $image_url = preg_replace('/http(s?):\/\//is', '//', $image['url_thumb']);
        } else {
            $image_url = '';
        }
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
        $categories = null;
        $category_routes = [];
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

                $params = ['product_url' => $product['url']];
                if (!empty($route['url_type']) && $route['url_type'] == 2) {
                    if ($categories === null) {
                        $category_model = new shopCategoryModel();
                        $categories = $category_model->getFullTree('id, name, depth, url, full_url, parent_id', true);
                        if ($product->categories) {
                            $category_routes_model = new shopCategoryRoutesModel();
                            $category_routes = $category_routes_model->getRoutes(array_keys($product->categories));
                        }
                    }
                    $category_exists = $product['category_id'] && isset($categories[$product['category_id']]);
                    if ($category_exists) {
                        if (empty($category_routes[$product['category_id']])) {
                            $category_available = true;
                        } else {
                            $category_available = in_array($domain.'/'.$route['url'], $category_routes[$product['category_id']]);
                        }
                        if ($category_available) {
                            $params['category_url'] = $categories[$product['category_id']]['full_url'];
                        }
                    }
                }
                $product_url = wa()->getRouteUrl("shop/frontend/product", $params, true, $domain, $route['url']);

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
