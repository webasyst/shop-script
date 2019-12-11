<?php

class shopWorkflowEditshippingdetailsAction extends shopWorkflowAction
{
    public function getDefaultOptions()
    {
        $options = parent::getDefaultOptions();
        $options['html'] = true;
        return $options;
    }

    public function execute($params = null)
    {
        $order_id = $params;

        $text = array();
        $params = array();
        $update = array();

        // Shipping date and time
        $shipping_date = waRequest::post('shipping_date', 'string_trim', '');
        $shipping_time_from = waRequest::post('shipping_time_from', 'string_trim', '');
        $shipping_time_to = waRequest::post('shipping_time_to', 'string_trim', '');
        if ($shipping_date && $shipping_time_from && $shipping_time_to) {
            $ts = @strtotime($shipping_date);
            if ($ts && preg_match('~^\d\d:\d\d$~', $shipping_time_from) && preg_match('~^\d\d:\d\d$~', $shipping_time_to)) {
                $text[] = _w('Shipping').': '.date('Y-m-d', $ts).' '.$shipping_time_from.'-'.$shipping_time_to;
                $params['shipping_start_datetime'] = date('Y-m-d', $ts).' '.$shipping_time_from.':00';
                $params['shipping_end_datetime'] = date('Y-m-d', $ts).' '.$shipping_time_to.':00';
                $update['shipping_datetime'] = $params['shipping_end_datetime'];
            }
        }

        // Tracking number
        $tracking = waRequest::post('tracking_number', null, waRequest::TYPE_STRING_TRIM);
        if ($tracking !== null) {
            if (strlen($tracking) > 0) {
                $text[] = _w('Tracking number').': '.htmlspecialchars($tracking);
                $params['tracking_number'] = $tracking;
            } elseif (wa_is_int($order_id) && $order_id > 0) {
                $params['tracking_number'] = null;
            }
        }

        // Courier
        if (null !== ( $courier_id = waRequest::post('courier_id', null, 'int'))) {
            if ($courier_id) {
                $courier_model = new shopApiCourierModel();
                $courier = $courier_model->getById($courier_id);
                if ($courier) {
                    $text[] = _w('Courier').': '.htmlspecialchars(ifempty($courier['name'], '('.$courier_id.')'));
                    $params['courier_id'] = $courier_id;
                }
            } else {
                $text[] = _w('Courier').': '._w('None');
                $params['courier_id'] = null;
            }
        }

        if ($text || $params || $update) {
            $update['params'] = $params;
            return array(
                'text'   => join("\n", $text), // order log text
                'params' => $params, // order log params
                'update' => $update, // order update, including order params
            );
        } else {
            return true;
        }
    }

    public function getHTML($order_id)
    {
        $params = $this->order_params_model->get($order_id);
        $storefront = ifset($params['storefront'], '');
        if ($storefront) {
            $storefront = rtrim($storefront, '/*');
            if (false !== strpos($storefront, '/')) {
                $storefront .= '/';
            }
        }

        $courier_model = new shopApiCourierModel();
        $all_couriers = $couriers = $courier_model->getEnabled();
        if ($storefront) {
            $couriers = $courier_model->getByStorefront($storefront, $couriers);
        }

        list($customer_delivery_date, $customer_delivery_time) = shopHelper::getOrderCustomerDeliveryTime($params);
        list($shipping_date, $shipping_time_start, $shipping_time_end) = shopHelper::getOrderShippingInterval($params);

        $this->getView()->assign(array(
            'other_couriers_exist' => count($all_couriers) > count($couriers),
            'storefront'           => $storefront,
            'couriers'             => $couriers,
            'selected_courier_id'  => ifset($params['courier_id']),
            'tracking_number'      => ifset($params['tracking_number']),
            'customer_delivery_date' => $customer_delivery_date,
            'customer_delivery_time' => $customer_delivery_time,
            'customer_delivery_date_str' => ifset($params['shipping_params_desired_delivery.date_str']),
            'shipping_time_start'  => $shipping_time_start,
            'shipping_time_end'    => $shipping_time_end,
            'shipping_date'        => $shipping_date,
        ));
        return parent::getHTML($order_id);
    }

    protected function getTemplateBasename($template = '')
    {
        return 'ShipAction.html';
    }

    public function getButton()
    {
        // This makes the form appear above order instead of in the right sidebar
        return parent::getButton('data-container="#workflow-content"');
    }
}
