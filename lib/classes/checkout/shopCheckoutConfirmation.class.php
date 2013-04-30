<?php

class shopCheckoutConfirmation extends shopCheckout
{
    protected $step_id = 'confirmation';

    public function display()
    {
        $cart = new shopCart();
        $items = $cart->items(false);

        $subtotal = $cart->total(false);
        $order = array('contact' => $this->getContact(), 'total' => $subtotal);
        $order['discount'] = shopDiscounts::calculate($order);

        $contact = $this->getContact();

        $view = wa()->getView();
        if (!$contact) {
            $view->assign('error', _w('Not enough data in the contact information to place the order.'));
            return;
        }

        $shipping_address = $contact->getFirst('address.shipping');
        if (!$shipping_address) {
            $shipping_address = array('data' => array(), 'value' => '');
        }
        $billing_address = $contact->getFirst('address.billing');
        if (!$billing_address) {
            $billing_address = array('data' => array(), 'value' => '');
        }

        $discount_rate = $subtotal ? ($order['discount'] / $subtotal) : 0;
        $taxes = shopTaxes::apply($items, array('shipping' => $shipping_address['data'],
            'billing' => $billing_address['data'], 'discount_rate' => $discount_rate));

        $tax = 0;
        $tax_included = 0;
        foreach ($taxes as $t) {
            if (isset($t['sum'])) {
                $tax += $t['sum'];
            }
            if (isset($t['sum_included'])) {
                $tax_included += $t['sum_included'];
            }
        }

        if (!isset($order['shipping'])) {
            $shipping_step = new shopCheckoutShipping();
            $rate = $shipping_step->getRate();
            if ($rate) {
                $order['shipping'] = $rate['rate'];
            } else {
                $order['shipping'] = 0;
            }
        }

        $view->assign(array(
            'contact' => $contact,
            'items' => $items,
            'shipping' => $order['shipping'],
            'discount' => $order['discount'],
            'total' => $subtotal - $order['discount'] + $order['shipping'] + $tax,
            'tax' => $tax_included + $tax,
            'subtotal' => $subtotal,
            'shipping_address' => $shipping_address,
            'billing_address' => $billing_address,
        ));
    }


    public function execute()
    {
        if ($comment = waRequest::post('comment')) {
            $this->setSessionData('comment', $comment);
        }
        return true;
    }
}