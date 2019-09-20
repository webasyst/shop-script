<?php

class shopSiteRoute_saveBeforeHandler extends waEventHandler
{
    /**
     * @param array $params array('domain' => string, 'route' => array)
     * @see waEventHandler::execute()
     * @return void
     */
    public function execute(&$params)
    {
        $our_app = isset($params['route']['app']) && $params['route']['app'] == 'shop';
        if ($our_app && empty($params['route']['checkout_storefront_id'])) {
            wa('shop');
            $domain = $params['domain'];
            $url = !empty($params['route']['url']) ? $params['route']['url'] : '*';
            $params['route']['checkout_storefront_id'] = shopCheckoutConfig::generateStorefrontId($domain, $url);
        }
    }
}