<?php

/**
 * /shipping/list
 */
class shopFrontendApiShippingListController extends shopFrontApiJsonController
{
    public function get()
    {
        $shipping_ids = wa()->getRequest()::param('shipping_id');
        $shipping_methods = shopHelper::getShippingMethods();
        if ($shipping_ids) {
            $shipping_methods = array_intersect_key($shipping_methods, array_fill_keys($shipping_ids, 0));
        }

        usort($shipping_methods, function ($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });

        $shipping_formatter = new shopFrontApiShippingFormatter();
        foreach ($shipping_methods as &$shipping) {
            $shipping['plugin_type'] = ifset($m, 'info', 'type', null);
            $shipping['logo'] = (empty($shipping['logo']) ? null : wa()->getConfig()->getRootUrl(true).ltrim($shipping['logo'], '/'));
            $shipping = $shipping_formatter->format($shipping);
        }

        $this->response = $shipping_methods;
    }
}
