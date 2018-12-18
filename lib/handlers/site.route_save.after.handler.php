<?php

class shopSiteRoute_saveAfterHandler extends waEventHandler
{
    /**
     * @param array $params array('domain' => string, 'route' => array)
     * @see waEventHandler::execute()
     * @return void
     */
    public function execute(&$params)
    {
        $our_app = isset($params['route']['app']) && $params['route']['app'] == 'shop';
        if ($our_app && !empty($params['route']['checkout_storefront_id']) && ifset($params['route']['checkout_version'], 1) == 2) {
            $theme = new waTheme('shop:'.$params['route']['theme']);
            $order_file = $theme->getFile('order.html');
            if (empty($order_file)) {
                wa('shop', 1);
                try {
                    wa()->setLocale(ifset($params['route']['locale']));
                    // Use default templates for new checkout
                    $config = new shopCheckoutConfig($params['route']['checkout_storefront_id']);
                    if ($config->isDefault()) {
                        $config->setData(['design' => ['custom' => true]]);
                        $config->commit();
                    }
                } catch (waException $e) {
                    waLog::dump('Failed to install default templates for new checkout.', $params, $e->getMessage(), 'error.log');
                }
                wa('site', 1);
            }
        }
    }
}