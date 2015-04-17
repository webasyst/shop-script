<?php

class shopCustomersSearchFormAction extends waViewAction
{
    protected $hash;
    protected $hash_parsed;

    public function execute()
    {
        $hash = $this->getHash();
        $this->view->assign(array(
            'hash' => $hash,
            'address_fields' => $this->getAddressFields($hash),
            'shipping_methods' => $this->getShippingMethods($hash),
            'payment_methods' => $this->getPaymentMethods($hash),
            'primary_currency' => wa()->getConfig()->getCurrency(),
            'create_methods' => $this->getCreateMethods(),
            'coupons' => $this->getCoupons(),
            'utm_campagns' => $this->getUtmCampaigns(),
            'product_name' => $this->getProductName($hash),
            'storefronts' => $this->getStorefronts(),
            'referers' => $this->getReferers()
        ));
    }

    public function getAddressFields($hash)
    {
        $fields = array();
        $address = waContactFields::get('address');
        if ($address && $address instanceof waContactCompositeField) {
            foreach ($address->getFields() as $field) {
                if ($field && $field instanceof waContactField && !($field instanceof waContactHiddenField)) {
                    $value = array('val' => '', 'op' => '=');
                    if (isset($hash['contact_info']['address'][$field->getId()])) {
                        $value = $hash['contact_info']['address'][$field->getId()];
                    } else if (isset($hash['address'][$field->getId()])) {
                        $value = $hash['address'][$field->getId()];
                    }
                    if ($field instanceof waContactRegionField) {
                        if (($pos = strpos($value['val'], ':')) !== false) {
                            $value['val'] = substr($value['val'], $pos + 1);
                        }
                    }
                    $html_field = $field->getHTML(array(
                        'value' => $value['val'],
                        'parent' => 'contact_info.address'
                    ));
                    $fields[$field->getId()] = array(
                        'name' => $field->getName(),
                        'type' => $field->getType(),
                        'field_class'  => get_class($field),
                        'html' => $html_field
                    );
                }
            }
        }
        return $fields;
    }

    public function getHash($parsed = true)
    {
        if ($this->hash === null) {
            if (waRequest::request('hash', null, waRequest::TYPE_STRING_TRIM) !== null) {
                $this->hash = waRequest::request('hash', null, waRequest::TYPE_STRING_TRIM);
            } else if (waRequest::param('hash', null, waRequest::TYPE_STRING_TRIM) !== null) {
                $this->hash = waRequest::param('hash', null, waRequest::TYPE_STRING_TRIM);
            } else {
                $this->hash = '';
            }
        }
        $hash = $this->hash;
        if ($parsed) {
            return $this->hash_parsed === null ? ($this->hash_parsed = shopCustomersCollectionPreparator::parseSearchHash($hash)) : $this->hash_parsed;
        }
        return $hash;
    }

    public function getShippingMethods($hash)
    {
        $val = ifset($hash['app']['shipment_method'], array('val' => ''));
        return $this->getPluginMethods($val, 'shipping');
    }

    public function getPaymentMethods($hash)
    {
        $val = ifset($hash['app']['payment_method'], array('val' => ''));
        return $this->getPluginMethods($val, 'payment');
    }

    public function getCreateMethods()
    {
        $m = new waContactModel();
        $methods = $m->query("SELECT DISTINCT create_method FROM wa_contact c JOIN shop_customer sc ON c.id = sc.contact_id WHERE c.create_app_id = 'shop'")
                            ->fetchAll(null, true);
        foreach ($methods as &$m) {
            $m = array(
                'id' => 'shop.' . $m,
                'name' => $m ? $m : 'shop'
            );
        }
        unset($m);

        return $methods;
    }

    protected function getPluginMethods($val, $type)
    {
        $m = new shopPluginModel();
        $data = array();

        foreach ($m->select('id, name')
                    ->where('type = :0', array($type))
                        ->order('name ASC')->fetchAll() as $item)
        {
            $item['selected'] = $val['val'] == $item['id'];
            $data[$item['id']] = $item;
        }
        return $data;
    }

    public function getCoupons()
    {
        $cm = new shopCustomerModel();
        $coupons = array(
            array(
                'id' => ':any',
                'name' => _w('Any discount coupon')
            )
        );
        foreach ($cm->getAllCoupons() as $c) {
            $coupons[] = array(
                'id' => $c['id'],
                'name' => $c['code'] ? $c['code'] : (_w('Coupon') . ' # ' . $c['id'])
            );
        }
        return $coupons;
    }

    public function getUtmCampaigns()
    {
        $omp = new shopOrderParamsModel();
        return array_merge(array(
            array(
                'id' => ':any',
                'name' => _w('Any UTM campaign')
            )),
            $omp->getAllUtmCampaign()
        );
    }

    public function getProductName($hash)
    {
        if (isset($hash['app']['product']['val']) && is_numeric($hash['app']['product']['val'])) {
            $product_id = $hash['app']['product']['val'];
            $m = new shopProductModel();
            $product = $m->getById($product_id);
            if ($product) {
                return $product['name'];
            }
        }
        return '';
    }

    public function getStorefronts()
    {
        foreach (wa()->getRouting()->getByApp('shop') as $domain => $domain_routes) {
            foreach ($domain_routes as $route) {
                $url = rtrim($domain.'/'.$route['url'], '/*');
                $storefronts[] = array(
                    'id' => $url,
                    'name' => $url
                );
            }
        }
        $storefronts[] = array(
            'id' => ':backend',
            'name' => _w('Backend')
        );
        return $storefronts;
    }

    public function getReferers()
    {
        $traffic_sources = wa('shop')->getConfig()->getOption('traffic_sources');
        $m = new waModel();
        $op = new shopOrderParamsModel();
        $refers = array();
        foreach ($op->getByField('name', 'referer_host', 'value') as $item) {
            if ($item['value']) {
                $refers[$item['value']] = $item['value'];
            }
        }
        foreach ($traffic_sources as $source_id => $source_param) {
            $refers[$source_id] = $source_id;
        }
        return array_values($refers);
    }

}

