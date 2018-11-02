<?php

class shopOrderSaveController extends waJsonController
{
    public function execute()
    {
        $storefront = waRequest::post('storefront', null, waRequest::TYPE_STRING_TRIM);
        $data = array(
            'id'                   => waRequest::get('id', null, waRequest::TYPE_INT),
            'contact_id'           => waRequest::post('customer_id', null, waRequest::TYPE_INT),
            'discount_description' => waRequest::request('discount_description', null, 'string'),
            'payment_params'       => waRequest::post('payment_'.waRequest::post('payment_id'), null),
            'shipping_params'      => $this->getShippingParams(),
            'params'               => array(
                'shipping_id'        => waRequest::post('shipping_id', null),
                'payment_id'         => waRequest::post('payment_id', null),
                'storefront'         => $storefront,
                'referer_host'       => waRequest::post('customer_source', null, waRequest::TYPE_STRING_TRIM),
                'departure_datetime' => shopDepartureDateTimeFacade::getDeparture(null, $storefront),
            ),
            'comment'              => waRequest::post('comment', null, waRequest::TYPE_STRING_TRIM),
            'shipping'             => waRequest::post('shipping', 0),
            'discount'             => waRequest::post('discount', null),

            'currency' => waRequest::post('currency'),
            'customer' => waRequest::post('customer'),

            'items' => array(
                'item'     => waRequest::post('item', array()),
                'product'  => waRequest::post('product', array()),
                'service'  => waRequest::post('service', array()),
                'variant'  => waRequest::post('variant', array()),
                'name'     => waRequest::post('name', array()),
                'price'    => waRequest::post('price', array()),
                'quantity' => waRequest::post('quantity', array()),
                'sku'      => waRequest::post('sku', array()),
                'stock'    => waRequest::post('stock', array()),
            ),
        );

        $options = array(
            'items_format'          => 'flat',
            'ignore_count_validate' => true,
        );
        $order = new shopOrder($data, $options);
        try {
            $saved_order = $order->save();
            $this->response['order'] = $saved_order->getData();
        } catch (waException $ex) {
            $this->errors = $order->errors();
        }

    }

    private function getShippingParams()
    {
        $shipping_id = waRequest::post('shipping_id', '', 'string');
        $_ = explode('.', $shipping_id, 2);
        return waRequest::post('shipping_'.$_[0], null);
    }

}
