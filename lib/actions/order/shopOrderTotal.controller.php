<?php

/**
 * Class shopOrderTotalController
 *
 * @method shopConfig getConfig()
 */
class shopOrderTotalController extends waJsonController
{
    public function execute()
    {
        $order = $this->getShopOrder();

        // To calculate all shipping rates, need extract clean ID
        $params = waRequest::request('params', array());
        $shipping_id = preg_replace('/\W.+$/', '' ,ifset($params,'shipping_id', 0));
        $this->response['shipping_methods'] = $order->getShippingMethods(false, $shipping_id);

        $this->response['shipping_method_ids'] = array_keys($this->response['shipping_methods']);
        $this->response['discount'] = $order->discount;
        $this->response['discount_description'] = $order->discount_description;
        $this->response['items_discount'] = array();
        $this->response['total'] = $order->total;
        $this->response['subtotal'] = $order->subtotal;
        $this->response['errors'] = $order->errors();

        foreach ($order->items as $id => $item) {

            if (!empty($item['total_discount'])) {
                switch ($item['type']) {
                    case 'service':
                        $selector = sprintf('%d_%d', $item['_parent_index'], $item['service_id']);
                        break;
                    default:
                        $selector = $item['_index'];
                        break;
                }
                $this->response['items_discount'][] = array(
                    'value'    => $item['total_discount'],
                    'html'     => $item['discount_description'],
                    'selector' => $selector,
                );
            }
        }
    }

    public function getShopOrder()
    {
        $data = waRequest::post();
        $data['id'] = ifset($data, 'order_id', null);
        unset($data['order_id']);

        return new shopOrder($data, array(
            'items_format' => 'tree',
        ));
    }
}
