<?php

class shopFrontendBuybuttonsAction extends waViewAction
{
    public function postExecute()
    {
        $id = (int) wa()->getRequest()->post('id');

        $route = $this->getCurrentRoute();

        $this->addToCart($id);

        $goto = wa()->getRequest()->post('goto');
        if ($goto === 'checkout') {
            $goto_url = wa()->getRouteUrl("shop/frontend/checkout", array(), true, $route['domain'], $route['url']);
        } else {
            $goto_url = wa()->getRouteUrl("shop/frontend/cart", array(), true, $route['domain'], $route['url']);
        }

        wa()->getStorage()->set('shop_order_buybutton', $id);
        $this->redirect($goto_url);
    }

    protected function getCurrentRoute()
    {
        $storefront = wa()->getRequest()->request('storefront');
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
                $root_url = rtrim($domain.'/'.$route['url'], '/*').'/';
                $storefronts[] = array(
                    'root_url' => $root_url,
                    'controller_url' => $controller_url
                );
            }
        }
        $current_route['domain'] = $current_domain;
        return $current_route;
    }

    protected function addToCart($id)
    {
        $_POST['product_id'] = $id;
        wa()->getRequest()->setParam('noredirect', 1);
        $action = new shopFrontendCartAddController();
        $action->execute();
    }

    public function getExecute()
    {
        $id = (int) wa()->getRequest()->get('id');
        $product = new shopProduct($id);

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

        $product_url = wa()->getRouteUrl("shop/frontend/product", array(
            'product_url' => $product['url']
        ), true, $route['domain'], $route['url']);

        $this->view->assign(array(
            'id' => $id,
            'product' => $product,
            'img_url' => $image_url,
            'params' => $params,
            'container_id' => $container_id,
            'product_url' => $product_url
        ));

    }

    public function execute()
    {
        if (wa()->getRequest()->post()) {
            $this->postExecute();
        } else {
            $this->getExecute();
        }
    }
}