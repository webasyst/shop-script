<?php

class shopSettingsCheckoutAbstractAction extends waViewAction
{
    protected $storefronts;
    public function preExecute()
    {
        $view = wa('shop')->getView();
        $this->storefronts = $this->getStorefrontsByCheckoutVersion();
        $view->assign(array(
            'storefronts' => $this->storefronts,
        ));
        $sidebar_template = wa()->getAppPath('templates/actions/settings/SettingsCheckoutSidebar.html', 'shop');
        $checkout_sidebar = $view->fetch($sidebar_template);

        $this->view->assign('checkout_sidebar', $checkout_sidebar);
    }

    // get storefronts by checkout version

    protected function getStorefrontsByCheckoutVersion()
    {
        $storefronts = array(
            1 => array(),
            2 => array(),
        );

        $shop_routes = wa()->getRouting()->getByApp('shop');
        foreach ($shop_routes as $domain => $routes ) {
            foreach ($routes as $route_id => $route) {
                $route['checkout_version'] = ifset($route, 'checkout_version', 1);
                $route['domain'] = $domain;
                $route['id'] = $route_id;
                $storefronts[$route['checkout_version']][] = $route;
            }
        }

        return $storefronts;
    }
}