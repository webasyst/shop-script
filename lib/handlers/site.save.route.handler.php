<?php

class shopSiteSaveRouteHandler extends waEventHandler
{
    /**
     * @param array $params array('old' => string, 'new' => string)
     * @see waEventHandler::execute()
     * @return void
     */
    public function execute(&$params)
    {
        $our_app = isset($params['route']['app']) && $params['route']['app'] == 'shop';
        if ($our_app && empty($params['route']['checkout_storefront_id'])) {
            $domain = $params['domain'];
            $url = !empty($params['route']['url']) ? $params['route']['url'] : '*';
            $params['route']['checkout_storefront_id'] = $this->generateStorefrontId($domain, $url);
        }
    }

    protected function generateStorefrontId($domain, $url)
    {
        while (true) {
            $id = md5(uniqid().$domain . $url.uniqid());
            if (!$this->duplicateStorefrontId($id)) {
                return $id;
            }
        }
    }

    protected function duplicateStorefrontId($id)
    {
        static $shop_routes;
        if ($shop_routes === null) {
            $shop_routes = wa()->getRouting()->getByApp('shop');
        }

        foreach ($shop_routes as $domain => $routes) {
            foreach ($routes as $route) {
                if (!empty($route['checkout_storefront_id']) && $route['checkout_storefront_id'] === $id) {
                    return true;
                }
            }
        }

        return false;
    }
}