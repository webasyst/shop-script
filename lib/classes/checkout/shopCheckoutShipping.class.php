<?php

class shopCheckoutShipping extends shopCheckout
{
    protected $step_id = 'shipping';

    public function display()
    {
        $plugin_model = new shopPluginModel();
        if (waRequest::param('shipping_id') && is_array(waRequest::param('shipping_id'))) {
            $methods = $plugin_model->getById(waRequest::param('shipping_id'));
        } else {
            $methods = $plugin_model->listPlugins('shipping');
        }

        $address = $this->getAddress();
        $empty = true;
        foreach ($address as $v) {
            if ($v) {
                $empty = false;
                break;
            }
        }
        if ($empty) {
            $address = array();
        }
        $items = $this->getItems();

        $cart = new shopCart();
        $total = $cart->total();


        $settings = wa('shop')->getConfig()->getCheckoutSettings();
        $address_form = !isset($settings['contactinfo']) || !isset($settings['contactinfo']['fields']['address.shipping']);
        if (!isset($settings['contactinfo']) ||
            (!isset($settings['contactinfo']['fields']['address.shipping']) && !isset($settings['contactinfo']['fields']['address']))) {
            $settings = wa('shop')->getConfig()->getCheckoutSettings(true);
        }
        if (!$address) {
            $address_form = true;
        }

        $currencies = wa('shop')->getConfig()->getCurrencies();
        foreach ($methods as $method_id => $m) {
            $plugin = shopShipping::getPlugin($m['plugin'], $m['id']);
            $plugin_info = $plugin->info($m['plugin']);
            $m['icon'] = $plugin_info['icon'];
            $m['img'] = $plugin_info['img'];
            $m['rates'] = $plugin->getRates($items, $address, array('total_price' => $total));
            $m['currency'] = $plugin->allowedCurrency();
            if (is_array($m['rates'])) {
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
                if ($m['rates']) {
                    $rate = reset($m['rates']);
                    $m['rate'] = $rate['rate'];
                    $m['est_delivery'] = $rate['est_delivery'];
                } else {
                    $m['rates'] = array();
                    $m['rate'] = null;
                }
            } elseif (is_string($m['rates'])) {
                if ($address) {
                    $m['error'] = $m['rates'];
                } else {
                    $m['rates'] = array();
                    $m['rate'] = null;
                }
            } else {
                unset($methods[$method_id]);
                continue;
            }

            $f = $this->getAddressForm($method_id, $plugin, $settings, $address, $address_form);
            if ($f) {
                $m['form'] = $f;
                $m['form']->setValue($this->getContact());
            }

            $methods[$method_id] = $m;
        }


        $view = wa()->getView();
        $view->assign('checkout_shipping_methods', $methods);
        $default_method = '';
        foreach ($methods as $m) {
            if (empty($m['error'])) {
                $default_method = $m['id'];
                break;
            }
        }
        $view->assign('shipping', $this->getSessionData('shipping', array('id' => $default_method, 'rate_id' => '')));
    }

    public function getAddressForm($method_id, waShipping $plugin, $config, $contact_address, $address_form)
    {
        $config_address = isset($config['contactinfo']['fields']['address.shipping']) ?
            $config['contactinfo']['fields']['address.shipping'] :
            (isset($config['contactinfo']['fields']['address']) ? $config['contactinfo']['fields']['address'] : array());

        $address_fields = $plugin->requestedAddressFields();
        $disabled_only = true;
        if ($address_fields === false || $address_fields === null) {
            return false;
        }
        foreach ($address_fields as $f) {
            if ($f !== false) {
                $disabled_only = false;
                break;
            }
        }
        $address = array();
        if ($disabled_only) {
            $allowed = $plugin->allowedAddress();
            if (count($allowed) == 1) {
                $one = true;
                if (!isset($config_address['fields'])) {
                    $address_field = waContactFields::get('address');
                    foreach ($address_field->getFields() as $f) {
                        $fields[$f->getId()] = array();
                    }
                } else {
                    $fields =  $config_address['fields'];
                }
                foreach ($allowed[0] as $k => $v) {
                    if (is_array($v)) {
                        $one = false;
                        break;
                    } else {
                        $fields[$k]['hidden'] = 1;
                        $fields[$k]['value'] = $v;
                    }
                }
                foreach ($address_fields as $k => $v) {
                    if ($v === false && isset($fields[$k])) {
                        unset($fields[$k]);
                    }
                }
                if ($one) {
                    $address = $config_address;
                    $address['fields'] = $fields;
                }
            }
        } else {
            if (isset($config_address['fields'])) {
                $fields = $config_address['fields'];
                foreach ($fields as $f_id => $f) {
                    if (isset($address_fields[$f_id])) {
                        foreach ($address_fields[$f_id] as $k => $v) {
                            $fields[$f_id][$k] = $v;
                        }
                    } else {
                        unset($fields[$f_id]);
                    }
                }
                $address_fields = $fields;
            }
            if ($address_fields) {
                $address = array('fields' => $address_fields);
            }
        }


        if (!$address_form && !empty($address['fields'])) {
            foreach ($address['fields'] as $k => $v) {
                if (empty($contact_address[$k])) {
                    $address_form = true;
                }
            }
        }
        if ($address_form) {
            if (!empty($config['shipping']['promt_type'])) {
                if (!empty($address['fields'])) {
                    foreach ($address['fields'] as $k => $v) {
                        if (empty($v['cost'])) {
                            unset($address['fields'][$k]);
                        }
                    }
                    if (!$address['fields']) {
                        return null;
                    }
                } else {
                    $empty = true;
                    foreach ($address_fields as $f) {
                        if (!empty($f['cost'])) {
                            $empty = false;
                            break;
                        }
                    }
                    if ($empty) {
                        return null;
                    }
                }
            }
            return waContactForm::loadConfig(array('address.shipping' => $address), array('namespace' => 'customer_'.$method_id));;
        } else {
            return null;
        }
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
        if ($rate_id) {
            $result = $rates[$rate_id];
        } else {
            $result = array('rate' => 0);
        }
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

            if ($data = waRequest::post('customer_'.$shipping_id)) {

                $settings = wa('shop')->getConfig()->getCheckoutSettings();
                if (!isset($settings['contactinfo']) ||
                    (!isset($settings['contactinfo']['fields']['address.shipping']) && !isset($settings['contactinfo']['fields']['address']))) {
                    $settings = wa('shop')->getConfig()->getCheckoutSettings(true);
                }
                $plugin = shopShipping::getPlugin(null, $shipping_id);
                $form = $this->getAddressForm($shipping_id, $plugin, $settings, array(), true);
                if (!$form->isValid()) {
                    return false;
                }

                $contact = $this->getContact();
                if (!$contact) {
                    $contact = new waContact();
                }
                if ($data && is_array($data)) {
                    foreach ($data as $field => $value) {
                        $contact->set($field, $value);
                    }
                    if (wa()->getUser()->isAuth()) {
                        $contact->save();
                    } else {
                        $this->setSessionData('contact', $contact);
                    }
                }
            }

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
        $html = '
<div class="field">
    <div class="name">
        <p>'._w('Prompt for address').'</p>
    </div>
    <div class="value">
        <p>'._w('During the “Shipping” checkout step, when customer selects a preferred shipping option but shipping address was not yet entered, instantly prompt customer to provide:').'</p>
    </div>
    <div class="value no-shift">
        <label><input '.(empty($config['promt_type']) ? 'checked' : '').' name="config[promt_type]" type="radio" value="0"> '._w('All required address fields').'</label>
        <p class="hint">'._w('Prompt for all address fields according to the selected shipping option implementation. If you use this option and have “Shipping” prior to “Contact info” in the checkout step order, it is advisable to hide (disable) shipping address form on the “Contact Info” checkout step to avoid asking for address twice.').'</p>
    </div>
    <div class="value no-shift">
        <label><input '.(!empty($config['promt_type']) ? 'checked' : '').' name="config[promt_type]" type="radio" value="1"> '._w('Only fields required for shipping rate estimation').'</label>
        <p class="hint">'._w('Prompt for fields required for shipping rate and delivery date estimation only (shipping option implementation declares the list of such fields). This is a suitable setup option if you have “Shipping” prior to “Contact info” in the checkout step order setup.').'</p>
    </div>
    </div>
    ';

        return $html;
    }

}