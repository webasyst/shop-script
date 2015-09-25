<?php

class shopBackendAutocompleteController extends waController
{
    protected $limit = 10;
    public function execute()
    {
        $data = array();
        $q = waRequest::get('term', '', waRequest::TYPE_STRING_TRIM);
        if ($q) {
            $type = waRequest::get('type', 'product', waRequest::TYPE_STRING_TRIM);
            if ($type == 'sku') {
                $data = $this->skusAutocomplete($q);
            } else if ($type == 'order') {
                $data = $this->ordersAutocomplete($q);
            } else if ($type == 'customer') {
                $data = $this->customersAutocomplete($q);
            } else if ($type == 'contact') {
                $data = $this->contactsAutocomplete($q);
            } else if ($type == 'feature') {
                $data = $this->featuresAutocomplete($q);
            } else if ($type == 'type') {
                $data = $this->typesAutocomplete($q);
            } else {
                $data = $this->productsAutocomplete($q);
            }
            $data = $this->formatData($data, $type);
        }
        echo json_encode($data);
    }

    private function formatData($data, $type)
    {
        if ($type == 'order') {

            $orders = array();
            foreach ($data as $k => $item) {
                if ($item['autocomplete_item_type'] == 'order') {
                    $orders[] = $item;
                    unset($data[$k]);
                }
            }

            if ($orders) {
                shopHelper::workupOrders($orders);
                $data = array_merge($orders, $data);
            }

            foreach ($data as &$item) {
                if ($item['autocomplete_item_type'] == 'order') {
                    $item['value'] = shopHelper::encodeOrderId($item['id']);
                    $item['label'] = '';
                    if (!empty($item['icon'])) {
                        $item['label'] .= "<i class='{$item['icon']}'></i>";
                    }
                    $item['label'] .= $item['value']." ".$item['total_str'];
                    $item['label'] .= ' <span class="hint">'.htmlspecialchars($item['customer_name']).'</span>';
                    $item = array(
                        'id' => $item['id'],
                        'value' => $item['value'],
                        'label' => $item['label'],
                        'autocomplete_item_type' => 'order'
                    );
                }
            }
            return $data;

        } else if ($type == 'product') {

            $with_counts = waRequest::get('with_counts', 0, waRequest::TYPE_INT);
            $with_sku_name = waRequest::get('with_sku_name', 0, waRequest::TYPE_INT);
            foreach ($data as &$item) {
                if (empty($item['label'])) {
                    $item['label'] = htmlspecialchars($item['value']);
                }
                if ($with_counts) {
                    $item['label'] .= ' '.shopHelper::getStockCountIcon($item['count'], null, true);
                }
                if ($with_sku_name) {
                    $item['label'] .= ' <span class="hint">'.$item['sku_name'].'</span>';
                }
            }

        }

        return $data;
    }

    public function skusAutocomplete($q)
    {
        $product_skus_model = new shopProductSkusModel();
        $q = $product_skus_model->escape($q, 'like');
        return $product_skus_model->
            select('id, name AS value')->
            where("name LIKE '{$q}%' OR sku LIKE '{$q}%'")-> // TODO: change name to full_name
            limit($this->limit)->
            fetchAll();
    }

    public function productsAutocomplete($q, $limit = null)
    {
        $limit = $limit !== null ? $limit : $this->limit;

        $product_model = new shopProductModel();
        $q = $product_model->escape($q, 'like');
        $fields = 'id, name AS value, price, count, sku_id';

        $products = $product_model->select($fields)
            ->where("name LIKE '$q%'")
            ->limit($limit)
            ->fetchAll('id');
        $count = count($products);

        if ($count < $limit) {
            $product_skus_model = new shopProductSkusModel();
            $product_ids = array_keys($product_skus_model->select('id, product_id')
                ->where("sku LIKE '$q%'")
                ->limit($limit)
                ->fetchAll('product_id'));
            if ($product_ids) {
                $data = $product_model->select($fields)
                    ->where('id IN ('.implode(',', $product_ids).')')
                    ->limit($limit - $count)
                    ->fetchAll('id');

                // not array_merge, because it makes first reset numeric keys and then make merge
                $products = $products + $data;
            }
        }

        // try find with LIKE %query%
        if (!$products) {
            $products = $product_model->select($fields)
                ->where("name LIKE '%$q%'")
                ->limit($limit)
                ->fetchAll();
        }
        $currency = wa()->getConfig()->getCurrency();
        foreach ($products as &$p) {
            $p['price_str'] = wa_currency($p['price'], $currency);
            $p['price_html'] = wa_currency_html($p['price'], $currency);
        }
        unset($p);

        if (waRequest::get('with_sku_name')) {
            $sku_ids = array();
            foreach ($products as $p) {
                $sku_ids[] = $p['sku_id'];
            }
            $product_skus_model = new shopProductSkusModel();
            $skus = $product_skus_model->getByField('id', $sku_ids, 'id');
            $sku_names = array();
            foreach ($skus as $sku_id => $sku) {
                $name = '';
                if ($sku['name']) {
                    $name = $sku['name'];
                    if ($sku['sku']) {
                        $name .= ' ('.$sku['sku'].')';
                    }
                } else {
                    $name = $sku['sku'];
                }
                $sku_names[$sku_id] = $name;
            }
            foreach ($products as &$p) {
                $p['sku_name'] = $sku_names[$p['sku_id']];
            }
            unset($p);
        }

        return array_values($products);
    }

    private function getOrders($q, $limit = null)
    {
        $order_model = new shopOrderModel();
        $limit = $limit ? $limit : $this->limit;
        $orders = $order_model->autocompleteById($q, $limit);
        if (!$orders) {
            return $order_model->autocompleteById($q, $limit, true);
        }
        return $orders;
    }

    public function ordersAutocomplete($q)
    {
        // search by:
        // 1. order_id,
        // 2. email, phone, firstname, lastname, name
        // 3. product, sku

        $limit = 5;

        // first, assume $q is encoded $order_id, so decode
        $dq = shopHelper::decodeOrderId($q);
        if (!$dq) {
            $dq = self::decodeOrderId($q);
        }
        if ($dq) {
            $orders = $this->getOrders($dq, $limit);
        } else {
            $orders = array();
        }

        $cnt = count($orders);
        if ($cnt < $limit) {
            $orders = array_merge($orders, $this->getOrders($q, $limit - $cnt));
        }

        foreach ($orders as &$o) {
            $o['autocomplete_item_type'] = 'order';
        }
        unset($o);

        $contacts = $this->contactsAutocomplete($q, $limit);
        foreach ($contacts as &$c) {
            $c['autocomplete_item_type'] = 'contact';
            $c['value'] = $c['name'];
        }
        unset($c);

        $products = $this->productsAutocomplete($q, $limit);
        foreach ($products as &$p) {
            $p['autocomplete_item_type'] = 'product';
            if (empty($p['label'])) {
                $p['label'] = htmlspecialchars($p['value']);
            }
            $p['label'] .= ' '.shopHelper::getStockCountIcon($p['count'], null, true);
        }

        return array_merge(
                $orders,
                $contacts,
                $products,
                $this->couponAutocomplete($q, $limit),
                $this->pluginMethodsAutocomplete($q, 'shipping', $limit),
                $this->pluginMethodsAutocomplete($q, 'payment', $limit),
                $this->cityAutocomplete($q, $limit),
                $this->regionAutocomplete($q, $limit),
                $this->countryAutocomplete($q, $limit)
        );

    }

    /**
     * Tries to decode order_id ignoring all non-digit characters in string.
     * Helps to implement human-intuitive searching over decoded IDs.
     */
    public static function decodeOrderId($encoded_id)
    {
        $format = wa('shop')->getConfig()->getOrderFormat();
        $format = str_replace('%', 'garbage', $format);
        $format = str_replace('{$order.id}', '%', $format);
        $format = preg_split('~[^0-9%]~', $format);
        foreach($format as $part) {
            if (strpos($part, '%')) {
                $format = $part;
                break;
            }
        }
        if (!is_string($format)) {
            return '';
        }

        $format = '/^'.str_replace('%', '(\d+)', preg_quote($format,'/')).'$/';
        if (!preg_match($format, $encoded_id, $m)) {
            return '';
        }
        return $m[1];
    }

    public function contactsAutocomplete($q, $limit = null)
    {
        $m = new waModel();

        // The plan is: try queries one by one (starting with fast ones),
        // until we find 5 rows total.
        $sqls = array();

        // Name starts with requested string
        $sqls[] = "SELECT c.id, c.name, c.firstname, c.middlename, c.lastname
                   FROM wa_contact AS c
                   WHERE c.name LIKE '".$m->escape($q, 'like')."%'
                   LIMIT {LIMIT}";

        // Email starts with requested string
        $sqls[] = "SELECT c.id, c.name, e.email, c.firstname, c.middlename, c.lastname
                   FROM wa_contact AS c
                       JOIN wa_contact_emails AS e
                           ON e.contact_id=c.id
                   WHERE e.email LIKE '".$m->escape($q, 'like')."%'
                   LIMIT {LIMIT}";

        // Phone contains requested string
        if (preg_match('~^[wp0-9\-\+\#\*\(\)\. ]+$~', $q)) {
            $dq = preg_replace("/[^\d]+/", '', $q);
            $sqls[] = "SELECT c.id, c.name, d.value as phone, c.firstname, c.middlename, c.lastname
                       FROM wa_contact AS c
                           JOIN wa_contact_data AS d
                               ON d.contact_id=c.id AND d.field='phone'
                       WHERE d.value LIKE '%".$m->escape($dq, 'like')."%'
                       LIMIT {LIMIT}";
        }

        // Name contains requested string
        $sqls[] = "SELECT c.id, c.name, c.firstname, c.middlename, c.lastname
                   FROM wa_contact AS c
                   WHERE c.name LIKE '_%".$m->escape($q, 'like')."%'
                   LIMIT {LIMIT}";

        // Email contains requested string
        $sqls[] = "SELECT c.id, c.name, e.email, c.firstname, c.middlename, c.lastname
                   FROM wa_contact AS c
                       JOIN wa_contact_emails AS e
                           ON e.contact_id=c.id
                   WHERE e.email LIKE '_%".$m->escape($q, 'like')."%'
                   LIMIT {LIMIT}";

        $limit = $limit !== null ? $limit : 5;
        $result = array();
        $term_safe = htmlspecialchars($q);
        foreach($sqls as $sql) {
            if (count($result) >= $limit) {
                break;
            }
            foreach($m->query(str_replace('{LIMIT}', $limit, $sql)) as $c) {
                if (empty($result[$c['id']])) {
                    if (!empty($c['firstname']) || !empty($c['middlename']) || !empty($c['lastname'])) {
                        $c['name'] = waContactNameField::formatName($c);
                    }
                    $name = $this->prepare($c['name'], $term_safe);
                    $email = $this->prepare(ifset($c['email'], ''), $term_safe);
                    $phone = $this->prepare(ifset($c['phone'], ''), $term_safe);
                    $phone && $phone = '<i class="icon16 phone"></i>'.$phone;
                    $email && $email = '<i class="icon16 email"></i>'.$email;
                    $result[$c['id']] = array(
                        'id' => $c['id'],
                        'value' => $c['id'],
                        'name' => $c['name'],
                        'label' => implode(' ', array_filter(array($name, $email, $phone))),
                    );
                    if (count($result) >= $limit) {
                        break 2;
                    }
                }
            }
        }

        foreach ($result as &$c) {
            $contact = new waContact($c['id']);
            $c['label'] = "<i class='icon16 userpic20' style='background-image: url(\"".$contact->getPhoto(20)."\");'></i>" . $c['label'];
        }
        unset($c);

        return array_values($result);
    }

    private function featuresAutocomplete($q)
    {
        $result = array();
        $model = new shopFeatureModel();
        $value = $model->escape($q, 'like');
        $table = $model->getTableName();
        $options = (array)waRequest::get('options',array());
        $where = array('1');
        if(!empty($options['single'])){
            $where[] = '`multiple`=0';
        }
        $where = ' AND (('.implode(') AND (',$where).'))';
        $sql = <<<SQL
SELECT * FROM {$table}
WHERE
  (`parent_id` IS NULL
  AND
  (
  (`name` LIKE '%{$value}%')
  OR
  (`code` LIKE '%{$value}%')
  )){$where}
ORDER BY `count` DESC
LIMIT 20
SQL;
        foreach( $model->query($sql)->fetchAll('code', true) as $code=> $f){
            $label = array(
                'name'=>$f['name'],
                'type' => shopFeatureModel::getTypeName($f),
                'count' => _w('%d value', '%d values', $f['count']),
            );

            $result[] = array(
                'id' => $f['id'],
                'value' => $code,
                'name' => $f['name'],
                'label' => implode('; ', array_filter($label)),
            );
        }

        return $result;
    }

    private function typesAutocomplete($q)
    {
        $result = array();
        $model = new shopTypeModel();
        $value = $model->escape($q, 'like');
        $table = $model->getTableName();
        $sql = <<<SQL
SELECT `icon`,`id`,`name`,`count` FROM {$table}
WHERE
  `name` LIKE '%{$value}%'
ORDER BY `count` DESC
LIMIT 20
SQL;
        foreach( $model->query($sql)->fetchAll('id', true) as $id=> $t){
            $icon = shopHelper::getIcon($t['icon']);
            unset($t['icon']);
            $t['count'] = _w('%d product', '%d products', $t['count']);
            $result[] = array(
                'id' => $id,
                'value' => $id,
                'name' => $t['name'],
                'label' => $icon.implode('; ', array_filter($t)),
            );
        }

        return $result;
    }

    // Helper for contactsAutocomplete()
    protected function prepare($str, $term_safe, $escape = true)
    {
        return preg_replace('~('.preg_quote($term_safe, '~').')~ui', '<span class="bold highlighted">\1</span>',
                    $escape ? htmlspecialchars($str) : $str);
    }

    protected function match($str, $term_safe, $escape = true)
    {
        return preg_match('~('.preg_quote($term_safe, '~').')~ui',
                    $escape ? htmlspecialchars($str) : $str);
    }

    public function customersAutocomplete($q)
    {
        $result = array();
        $hashes = array();

        if (preg_match('~^\+*[0-9\s\-\(\)]+$~', $q)) {
            $hashes['phone'] = 'search/phone*=' . ltrim($q, '+');
        } else {
            $hashes['email|name'] = 'search/email|name*=' . $q;
//            $hashes['shipping_name'] = 'search/order_params.shipping_name*=' . $q;
//            $hashes['billing_name'] = 'search/order_params.billing_name*=' . $q;
//            $hashes['coupon'] = 'search/coupon*=' . $q;
            $hashes['city'] = 'search/address:city*=' . $q;
            $hashes['region'] = 'search/address:region*=' . $q;
            $hashes['country'] = 'search/address:country*=' . $q;
        }

        $used_hash = array_fill_keys(array_keys($hashes), false);
        $used_hash['phone'] = false;
        $used_hash['address'] =false;


        $customers = array();

        $limit = 5;

        foreach ($hashes as $hash_id => $hash) {
            $count = count($customers);
            if ($count < $limit) {
                $col = new shopCustomersCollection($hash);
                $res = $col->getCustomers('id,name,firstname,middlename,lastname,email,phone,address', 0, $limit - $count);
                foreach ($res as $customer) {
                    $customers[$customer['id']] = $customer;
                }
                if (count($res) > 0) {
                    if (in_array($hash_id, array('city', 'region', 'country'))) {
                        $used_hash['address'] = true;
                    } else {
                        $used_hash[$hash_id] = true;
                    }
                }
            }
        }

        if ($used_hash['address']) {
            $address_field = waContactFields::get('address');
        }
        if ($used_hash['phone']) {
            $phone_field = waContactFields::get('phone');
        }
        if ($used_hash['email|name']) {
            $email_field = waContactFields::get('email');
        }

        foreach ($customers as &$customer) {

            $customer['address_formatted'] = array();
            $customer['email_formatted'] = array();
            $customer['phone_formatted'] = array();

            if ($used_hash['address']) {
                if (isset($customer['address'][0])) {
                    foreach ($customer['address'] as $i => $address) {
                        $customer['address_formatted'][$i] = $address_field->format($address, 'html');
                    }
                } else if (isset($customer['address'])) {
                    $customer['address_formatted'][0] = $address_field->format($address, 'html');
                }
            }
            if ($used_hash['phone']) {
                if (isset($customer['phone'][0])) {
                    foreach ($customer['phone'] as $i => $phone) {
                        $customer['phone_formatted'][$i] = $phone_field->format($phone, 'html');
                    }
                } else if (isset($customer['phone'])) {
                    $customer['phone_formatted'][0] = $phone_field->format($phone, 'html');
                }
            }
            if ($used_hash['email|name']) {
                if (isset($customer['email'][0])) {
                    foreach ($customer['email'] as $i => $email) {
                        $customer['email_formatted'][$i] = $email_field->format($email, 'html');
                    }
                } else if (isset($customer['email'])) {
                    $customer['email_formatted'][0] = $email_field->format($email, 'html');
                }
            }
        }
        unset($customer);


        $term_safe = htmlspecialchars($q);
        foreach($customers as $c) {

            $name = waContactNameField::formatName($c);
            $name = $this->prepare($name, $term_safe);

            $emails = array();
            foreach ($c['email_formatted'] as $email) {
                if ($this->match($email, $term_safe, false)) {
                    $emails[] = '<i class="icon16 email"></i>' . $this->prepare($email, $term_safe, false);
                    break;
                }
            }

            $phones = array();
            foreach ($c['phone_formatted'] as $phone) {
                if ($this->match($phone, $term_safe, false)) {
                    $phones[] = '<i class="icon16 phone"></i>' . $this->prepare($phone, $term_safe, false);
                    break;
                }
            }

            $addresses = array();
            foreach ($c['address_formatted'] as $address) {
                if ($this->match($address, $term_safe, false)) {
                    $addresses[] = $this->prepare($address, $term_safe, false);
                    break;
                }
            }


            $result[] = array(
                'value' => $c['name'],
                'label' => implode(' ', array_merge(array($name), $emails, $phones, $addresses)),
                'id' => $c['id'],
            );
        }

        foreach ($result as &$c) {
            $contact = new waContact($c['id']);
            $c['label'] = "<i class='icon16 userpic20' style='background-image: url(\"".$contact->getPhoto(20)."\");'></i>" . $c['label'];
        }
        unset($c);

        return array_merge(
            $result,
            $this->couponAutocomplete($q, $limit),
            $this->pluginMethodsAutocomplete($q, 'shipping', $limit),
            $this->pluginMethodsAutocomplete($q, 'payment', $limit),
            $this->cityAutocomplete($q, $limit),
            $this->regionAutocomplete($q, $limit),
            $this->countryAutocomplete($q, $limit)
        );
    }

    public function couponAutocomplete($q, $limit = 5)
    {
        $cm = new shopCouponModel();
        $q = $cm->escape($q, 'like');
        $term_safe = htmlspecialchars($q);
        $result = array();
        $limit = (int) $limit;
        foreach ($cm->query("SELECT * FROM `shop_coupon` WHERE code LIKE '%{$q}%' LIMIT {$limit}") as $item) {
            $result[] = array(
                'value' => $item['code'],
                'label' => '<i class="icon16 ss coupon"></i> ' . $this->prepare($item['code'], $term_safe),
                'autocomplete_item_type' => 'coupon',
                'id' => $item['id']
            );
        }
        return $result;
    }

    public function pluginMethodsAutocomplete($q, $type, $limit = 5)
    {
        $pm = new shopPluginModel();
        $q = $pm->escape($q, 'like');
        $type = $pm->escape($type, 'like');
        $term_safe = htmlspecialchars($q);
        $result = array();
        $limit = (int) $limit;
        foreach ($pm->query("SELECT * FROM `shop_plugin` WHERE type = '{$type}' AND name LIKE '%{$q}%' LIMIT {$limit}") as $item) {
            $logo = htmlspecialchars($item['logo']);
            $result[] = array(
                'value' => $item['name'],
                'label' => "<img src='{$logo}' style='height: 16px;'> " . $this->prepare($item['name'], $term_safe),
                'autocomplete_item_type' => $type,
                'id' => $item['id']
            );
        }
        return $result;
    }

    public function cityAutocomplete($q, $limit = 5)
    {
        $m = new waContactDataModel();
        $q = $m->escape($q, 'like');
        $term_safe = htmlspecialchars($q);
        $limit = (int) $limit;
        $result = array();
        foreach ($m->query("SELECT DISTINCT value FROM `wa_contact_data` WHERE field = 'address:city' AND value LIKE '%{$q}%' LIMIT {$limit}") as $item) {
            $result[] = array(
                'value' => $item['value'],
                'label' => $this->prepare($item['value'], $term_safe),
                'autocomplete_item_type' => 'city'
            );
        }
        return $result;
    }

    public function regionAutocomplete($q, $limit = 5)
    {
        $rm = new waRegionModel();
        $q = $rm->escape($q, 'like');
        $term_safe = htmlspecialchars($q);
        $limit = (int) $limit;
        $result = array();
        $url = wa()->getRootUrl();
        foreach ($rm->query("SELECT DISTINCT code, country_iso3, name FROM `wa_region` WHERE name LIKE '%{$q}%' LIMIT {$limit}") as $item) {
            $result[] = array(
                'value' => $item['country_iso3'] . ':' . $item['code'],
                'label' => '<img src="'.$url.'wa-content/img/country/'.$item['country_iso3'].'.gif"> ' . $this->prepare($item['name'], $term_safe),
                'autocomplete_item_type' => 'region'
            );
        }
        return $result;
    }

    public function countryAutocomplete($q, $limit = 5)
    {
        $cm = new waCountryModel();
        $q = $cm->escape($q, 'like');
        $term_safe = htmlspecialchars($q);
        $limit = (int) $limit;
        $url = wa()->getRootUrl();
        $result = array();
        foreach ($cm->query("SELECT DISTINCT name, iso3letter FROM `wa_country` WHERE name LIKE '%{$q}%' LIMIT {$limit}") as $item) {
            $result[] = array(
                'value' => $item['iso3letter'],
                'label' => '<img src="'.$url.'wa-content/img/country/'.$item['iso3letter'].'.gif"> ' . $this->prepare($item['name'], $term_safe),
                'autocomplete_item_type' => 'country'
            );
        }
        $count = count($result);
        if ($count < $limit) {
            $all = $cm->select('name,iso3letter')->fetchAll();
            foreach ($all as $item) {
                $name = _ws($item['name']);
                if ($this->match($name, $term_safe)) {
                    $count += 1;
                    $result[] = array(
                        'value' => $item['iso3letter'],
                        'label' =>  '<img src="'.$url.'wa-content/img/country/'.$item['iso3letter'].'.gif"> ' . $this->prepare($name, $term_safe),
                        'autocomplete_item_type' => 'country'
                    );
                    if ($count >= $limit) {
                        break;
                    }
                }
            }
        }
        return $result;
    }

}

