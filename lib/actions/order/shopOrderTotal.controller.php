<?php

/**
 * Class shopOrderTotalController
 *
 * How work Discount_description:
 *
 * New order.
 * 1) If you enter a discount hands, then the input value is passed. In the logs of the order, the text is saved, that the discount was entered by hands when creating.
 * 2) If you clicked to recalculate the discount, then it is transferred to 'calculate'. In the order logs the text 'discount for the ordered goods is saved:' And the information on the items is written down.
 * 3) If the discount is not touched, then we do not transfer anything and we do not display anything on the order logs.

 * Old order.
 * 1) If you enter a discount hands, then the input value is passed. In the logs of the order, the text is saved, that the discount was entered by the hands when editing.
 * 2) If you clicked to recalculate the discount, then it is transferred to 'calculate'. In the order logs the text 'discount for the ordered goods is saved:' And the information on the items is written down.
 * 3) If the discount is not touched, and before the discount was not there, then nothing is transferred and in the logs of the order is not deduced.
 * 4) If the discount was not touched and before the discount was, we do not transfer the discount, but we pass the discount_description. In the logs we display unallocated information on the old discounts
 * @method shopConfig getConfig()
 */
class shopOrderTotalController extends waJsonController
{
    public function execute()
    {
        $order = $this->getShopOrder();

        // To calculate all shipping rates, need extract clean ID
        $params = waRequest::request('params', array());
        $shipping_id = preg_replace('/\W.+$/', '', ifset($params, 'shipping_id', 0));
        $this->response['shipping_methods'] = $order->getShippingMethods(false, $shipping_id);

        $this->response['shipping_method_ids'] = array_keys($this->response['shipping_methods']);
        $this->response['discount'] = $order->discount;
        $this->response['discount_description'] = $order->discount_description;
        $this->response['total'] = $order->total;
        $this->response['subtotal'] = $order->subtotal;
        $this->response['errors'] = $order->errors();
        $this->response['shipping'] = $order->shipping;
        $this->response['tax'] = $order->tax;
        $this->response['items_tax'] = $order->items_tax;
        $this->response['items_discount'] = $order->items_discount;
    }

    public function getShopOrder()
    {
        $data = waRequest::post();
        $data['id'] = ifset($data, 'order_id', null);

        //If the coupon is not transferred, then it was not there or dropped it.
        $data['params']['coupon_id'] = ifset($data, 'params', 'coupon_id', 0);
        unset($data['order_id']);

        $form = new shopBackendCustomerForm();
        $form->setAddressDisplayType('first');

        $storefront = waRequest::post('storefront', null, waRequest::TYPE_STRING_TRIM);
        $form->setStorefront($storefront, true);

        $order = new shopOrder($data, array(
            'items_format'   => 'tree',
            'shipping_round' => true,
            'customer_form'  => $form
        ));


        return $order;
    }
}
