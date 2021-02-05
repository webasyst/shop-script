<?php

class shopFrontendBuybuttonsAction extends waViewAction
{
    /**
     * @var shopProduct
     */
    private $product;

    public function postExecute()
    {
        $route = $this->getCurrentRoute();
        $checkout_version = ifset($route, 'checkout_version', 1);

        $post = wa()->getRequest()->post('buybutton');
        unset($_POST['buybutton']);
        $post['id'] = (int)$post['id'];

        $goto = $post['goto'];

        if (!$this->addToCart($post)) {
            // if sku is not available, not allowed goto checkout
            $goto = 'product';
        }

        if ($goto === 'checkout' || $goto === 'cart') {
            if ($checkout_version == 2) {
                $goto_url = (new shopCheckoutViewHelper())->url(true);
            } else if ($goto === 'checkout') {
                $goto_url = wa()->getRouteUrl("shop/frontend/checkout", array(), true, $route['domain'], $route['url']);
            } else {
                $goto_url = wa()->getRouteUrl("shop/frontend/cart", array(), true, $route['domain'], $route['url']);
            }
        } else {
            $product = $this->getProduct($post['id']);
            $goto_url = wa()->getRouteUrl("shop/frontend/cart", array('product_url' => $product->url), true, $route['domain'], $route['url']);
        }

        wa()->getStorage()->set('shop_order_buybutton', $post['id']);
        $this->redirect($goto_url);
    }

    protected function getCurrentRoute()
    {
        if (wa()->getRequest()->post()) {
            $post = wa()->getRequest()->post('buybutton');
            $storefront = ifset($post['storefront'], '');
        } else {
            $storefront = (string) wa()->getRequest()->get('storefront');
        }
        $current_domain = null;
        $current_route = null;
        foreach (wa()->getRouting()->getByApp('shop') as $domain => $domain_routes) {
            $current_domain = $current_domain !== null ? $current_domain : $domain;
            foreach ($domain_routes as $route) {
                $current_route = $current_route !== null ? $current_route : $route['url'];
                $controller_url = wa()->getRouteUrl("shop/frontend/buybuttons", array(), true, $domain, $route['url']);
                if ($controller_url === $storefront) {
                    $current_domain = $domain;
                    $current_route = $route;
                }
                $root_url = rtrim($domain . '/' . $route['url'], '/*') . '/';
                $storefronts[] = array(
                    'root_url' => $root_url,
                    'controller_url' => $controller_url
                );
            }
        }
        $current_route['domain'] = $current_domain;
        return $current_route;
    }

    protected function addToCart($post)
    {
        $product_id = (int)$post['id'];
        $product = $this->getProduct($product_id);

        $available = true;

        $_POST['product_id'] = $post['id'];

        if ($product->sku_type == shopProductModel::SKU_TYPE_SELECTABLE) {
            $res = $this->featuresSelectableWorkup($product);
            $skus = $res['sku_features_selectable'];
            $features = ifset($post['features'], array());
            $key = '';
            foreach ($features as $feature_id => $feature_value_id) {
                $key .= $feature_id . ':' . $feature_value_id . ';';
            }
            if (!isset($skus[$key]) || !$skus[$key]['available'] || !$skus[$key]['status']) {
                $available = false;
            }
            $_POST['features'] = $features;
        } else if (count($product->skus) > 1) {
            $sku_id = (int) ifset($post['sku_id']);
            $skus = $product->skus;
            if (!isset($skus[$sku_id]) || !$skus[$sku_id]['available'] || !$skus[$sku_id]['status']) {
                $available = false;
            }
            $_POST['sku_id'] = $sku_id;
        }

        wa()->getRequest()->setParam('noredirect', 1);
        $action = new shopFrontendCartAddController();
        $action->execute();

        if (!empty($action->errors)) {
            // Err if product is not in the cart
            $cart_items = waUtils::getFieldValues($action->cart->items(), 'product_id', 'product_id');
            if (empty($cart_items[$product_id])) {
                $available = false;
            }
        }

        return $available;
    }

    public function getExecute()
    {
        $id = (int)wa()->getRequest()->get('id');
        $product = $this->getProduct($id);
        $params = wa()->getRequest()->get();

        $locale = ifempty($params['locale'], 'en_US');
        if ($locale && $locale != wa()->getLocale()) {
            waLocale::loadByDomain('shop', $locale);
            wa()->setLocale($locale);
        }

        if (!empty($params["width"]) && $params["width"] > 200) {
            $images = $product->getImages('750x0', true);
            $image = reset($images);
            $image_url = $image ? $image['url_750x0'] : '';
        } else {
            $images = $product->getImages('thumb', true);
            $image = reset($images);
            $image_url = $image ? $image['url_thumb'] : '';
        }

        $container_id = wa()->getRequest()->get('html_id');
        $container_id = $container_id ? $container_id : uniqid('webasyst-shop-script-product-');

        $route = $this->getCurrentRoute();

        $url_params = array(
            'product_url' => $product['url']
        );

        $category_url = $product->getCategoryUrl($route);
        if ($category_url) {
            $url_params['category_url'] = $category_url;
        }

        $product_url = wa()->getRouteUrl("shop/frontend/product", $url_params, true, $route['domain'], $route['url']);

        $this->featuresSelectableAssigns($product);

        $this->view->assign(array(
            'id' => $id,
            'product' => $product,
            'img_url' => $image_url,
            'params' => $params,
            'container_id' => $container_id,
            'product_url' => $product_url
        ));

    }

    public function getProduct($id)
    {
        if ($this->product === null) {
            $product = new shopProduct($id, true);
            $skus = $product->skus;
            foreach ($skus as &$sku) {
                $sku['price_html'] = shop_currency_html($sku['price'], $product['currency']);
                $sku['orig_available'] = $sku['available'];
                $sku['available'] = $this->isProductSkuAvailable($product, $sku);
            }
            unset($sku);
            $product->skus = $skus;
            $this->product = $product;
        }
        return $this->product;
    }

    private function featuresSelectableWorkup($product)
    {
        if (!$this->isPreview()) {
            $features_selectable = $product->features_selectable;
        } else {
            // in preview we call controller directly not using storefront and routing
            $fsm = new shopProductFeaturesSelectableModel();
            $features_selectable = $fsm->getDataByProduct($product, array('env' => 'frontend'));
        }

        $product_features_model = new shopProductFeaturesModel();
        $sku_features = $product_features_model->getSkuFeatures($product->id);

        $sku_selectable = array();
        foreach ($sku_features as $sku_id => $sf) {
            if (!isset($product->skus[$sku_id])) {
                continue;
            }
            $sku_f = "";
            foreach ($features_selectable as $f_id => $f) {
                if (isset($sf[$f_id])) {
                    $sku_f .= $f_id . ":" . $sf[$f_id] . ";";
                }
            }
            $sku = $product->skus[$sku_id];
            $sku_selectable[$sku_f] = array(
                'id' => $sku_id,
                'price' => (float)shop_currency($sku['price'], $product['currency'], null, false),
                'price_html' => shop_currency_html($sku['price']),
                'orig_available' => $sku['available'],
                'available' => $this->isProductSkuAvailable($product, $sku),
                'image_id' => (int)$sku['image_id']
            );
            if ($sku['compare_price']) {
                $sku_selectable[$sku_f]['compare_price'] = (float)shop_currency($sku['compare_price'], $product['currency'], null, false);
            }
        }
        $product['sku_features'] = ifset($sku_features[$product->sku_id], array());

        return array(
            'features_selectable' => $features_selectable,
            'sku_features_selectable' => $sku_selectable
        );
    }

    private function featuresSelectableAssigns($product)
    {
        if ($product->sku_type == shopProductModel::SKU_TYPE_SELECTABLE) {
            $res = $this->featuresSelectableWorkup($product);
            $this->view->assign($res);
        }
    }

    public function execute()
    {
        if (wa()->getRequest()->post()) {
            $this->postExecute();
        } else {
            $this->getExecute();
        }
    }

    private function isProductSkuAvailable($product, $sku)
    {
        return $product->status && $sku['available'] && $sku['status'] &&
            ($this->getConfig()->getGeneralSettings('ignore_stock_count') || $sku['count'] === null || $sku['count'] > 0);
    }

    public function isPreview()
    {
        return wa()->getRequest()->request('preview');
    }
}
