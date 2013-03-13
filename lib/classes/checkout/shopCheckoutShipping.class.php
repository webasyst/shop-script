<?php

class shopCheckoutShipping extends shopCheckout
{
    protected $step_id = 'shipping';

    public function display()
    {
        $plugin_model = new shopPluginModel();
        $methods = $plugin_model->listPlugins('shipping');

        $address = $this->getAddress();
        $items = $this->getItems();

        $cart = new shopCart();
        $total = $cart->total();

        $currencies = wa('shop')->getConfig()->getCurrencies();
        foreach ($methods as $method_id => $m) {
            $plugin = shopShipping::getPlugin($m['plugin'], $m['id']);
            $m['rates'] = $plugin->getRates($items, $address, array('total_price' => $total));
            if (is_array($m['rates'])) {
                $m['currency'] = $plugin->allowedCurrency();
                if (!isset($currencies[$m['currency']])) {
                    $m['rate'] = 0;
                    $m['error'] = sprintf(_w('Shipping rate was not calculated because required currency %s is not defined in your store settings.'), $m['currency']);
                    $methods[$method_id] = $m;
                    continue;
                }
                foreach ($m['rates'] as &$r) {
                    if (is_array($r['rate'])) {
                        $r['rate'] = max($r['rate']);
                    }
                }
                $rate = reset($m['rates']);
                $m['rate'] = $rate['rate'];
                $m['est_delivery'] = $rate['est_delivery'];
                $methods[$method_id] = $m;
            } elseif (is_string($m['rates'])) {
                $m['error'] = $m['rates'];
                $methods[$method_id] = $m;
            } else {
                unset($methods[$method_id]);
            }
        }

        $view = wa()->getView();
        $view->assign('checkout_shipping_methods', $methods);
        $m = reset($methods);
        $view->assign('shipping', $this->getSessionData('shipping', array('id' => $m ? $m['id'] : '', 'rate_id' => '')));
    }

    public function getItems()
    {
        $items = array();
        $cart = new shopCart();
        $cart_items = $cart->items();
        $product_ids = $sku_ids = array();
        foreach ($cart_items as $item) {
            $product_ids[] = $item['product_id'];
            $sku_ids[] = $item['sku_id'];
        }
        $feature_model = new shopFeatureModel();
        $f = $feature_model->getByCode('weight');
        if (!$f) {
            $values = array();
        } else {
            $values_model = $feature_model->getValuesModel($f['type']);
            $values = $values_model->getProductValues($product_ids, $f['id']);
        }

        foreach ($cart_items as $item) {
            $items[] = array(
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'weight' => isset($values[$item['product_id']]) ? $values[$item['product_id']] : 0
            );
        }
        return $items;
    }

    public function getRate($id = null, $rate_id = null)
    {
        if (!$id) {
            $shipping = $this->getSessionData('shipping');
            if (!$shipping) {
                return array();
            }
            $id = $shipping['id'];
            $rate_id = $shipping['rate_id'];
        }
        $plugin_model = new shopPluginModel();
        $plugin_info = $plugin_model->getById($id);
        $plugin = shopShipping::getPlugin($plugin_info['plugin'], $id);
        $cart = new shopCart();
        $total = $cart->total();
        $currency = $plugin->allowedCurrency();
        $currrent_currency = wa()->getConfig()->getCurrency(false);
        if ($currency != $currrent_currency) {
            $total = shop_currency($total, $currrent_currency, $currency, false);
        }
        $rates = $plugin->getRates($this->getItems(), $this->getAddress(), array('total_price' => $total));
        $result = $rates[$rate_id];
        if (is_array($result['rate'])) {
            $result['rate'] = max($result['rate']);
        }
        if ($currency != $currrent_currency) {
            $result['rate'] = shop_currency($result['rate'], $currency, $currrent_currency, false);
        }
        $result['plugin'] = $plugin->getId();
        $result['name'] = $plugin_info['name'].(!empty($result['name']) ? ' ('.$result['name'].')': '');
        return $result;
    }

    public function getAddress()
    {
        if (!$this->getContact()) {
            return array();
        }
        $address = $this->getContact()->getFirst('address.shipping');
        if ($address) {
            return $address['data'];
        } else {
            return array();
        }
    }

    public function validate()
    {


    }

    public function execute()
    {
        if ($shipping_id = waRequest::post('shipping_id')) {
            $rates = waRequest::post('rate_id');
            $this->setSessionData('shipping', array(
                'id' => $shipping_id,
                'rate_id' => isset($rates[$shipping_id]) ? $rates[$shipping_id] : null
            ));
            if ($comment = waRequest::post('comment')) {
                $this->setSessionData('comment', $comment);
            }
            return true;
        } else {
            return false;
        }
    }

    public function getOptions($config)
    {
        return '<div class="field">
    <div class="name">'._w('Shipping methods').'</div>
    <div class="value no-shift">
        <p>'._w('The list of available shipping methods is determined automatically based on the user address, shopping cart content, and the list of available <a class="inline" href="#/shipping/">shipping options</a>.').'</p>
    </div>
</div>';
    }

}