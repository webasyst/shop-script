<?php

class shopOrderCouriersMethod extends shopApiMethod
{
    protected $method = 'GET';
    protected $courier_allowed = false;

    public function execute()
    {
        $order_id = @(int)$this->get('id', true);

        // Make sure order exists
        $order_model = new shopOrderModel();
        $order = $order_model->getById($order_id);
        if (!$order) {
            throw new waAPIException('invalid_param', 'Order not found', 404);
        }

        // Which storefront does the order belong to
        $order_params_model = new shopOrderParamsModel();
        $params = $order_params_model->get($order_id);
        $storefront = ifset($params['storefront'], '');
        if ($storefront) {
            $storefront = rtrim($storefront, '/*');
            if (false !== strpos($storefront, '/')) {
                $storefront .= '/';
            }
        }

        // List of couriers that can be assigned to the order
        $courier_model = new shopApiCourierModel();
        $couriers = $courier_model->getEnabled();
        if ($storefront) {
            $couriers = $courier_model->getByStorefront($storefront, $couriers);
        }

        // Format data
        $selected_courier_id = ifset($params['courier_id']);
        foreach($couriers as &$c) {
            foreach ($c as $field => $value) {
                if (strpos($field, 'api_') === 0) {
                    unset($c[$field]);
                }
            }
            $c['selected'] = $c['id'] == $selected_courier_id;
            if ($c['contact_id'] && $c['photo']) {
                $c['photo_url'] = wa()->getConfig()->getHostUrl().waContact::getPhotoUrl($c['contact_id'], $c['photo']);
            } else {
                $c['photo_url'] = wa()->getConfig()->getHostUrl().waContact::getPhotoUrl(0, 0);
            }
        }
        unset($c);

        $this->response = array_values($couriers);
    }
}
