<?php
/**
 * Генератор демо-заказов (и покупателей).
 * Принимает сабмит от формы shopGenorderPluginBackendGeneratorAction
 */
class shopGenorderPluginBackendGenerateController extends waJsonController
{
    public function execute()
    {
        /*
          date_start => '2015-02-02'
          date_end => '2015-03-04'
          storefront => 'localhost/shop2'
          source_type => 'campaign'
          source => ''
          action => 'complete'
          new_percent => '80'
          photo_percent => '80'
          customer_api => 'randus' // 'randomuser'
        */
        $settings = waRequest::request('settings', array(), 'array');

        try {
            $ts = null;
            if (!empty($settings['date_start']) && !empty($settings['date_end'])) {
                $ts_start = strtotime($settings['date_start']);
                $ts_end = strtotime($settings['date_end']);
                if ($ts_start && $ts_end) {
                    $ts = mt_rand($ts_start, $ts_end);
                }
            }
            if (!$ts) {
                throw new waException('Incorrect date period');
            }
            $order_datetime = date('Y-m-d H:i:s', $ts);
            $settings['order_datetime'] = $order_datetime;

            $contact = self::generateContact($settings, $order_datetime);
            $settings['customer_is_new'] = $customer_is_new = !$contact->getId();

            $order = self::generateOrderData($settings, $contact);
            //$this->response = wa_dump_helper($order);
            $order = self::saveOrder($settings, $order);
            self::processOrder($settings, $order);

            self::saveContactPhoto($settings, $contact);

            $sales_model = new shopSalesModel();
            $sales_model->deletePeriod($order_datetime, ifset($settings['action_datetime']));

            $this->response = '<a href="?action=orders#/order/'.$order['id'].'/">'._w('Order').' '.shopHelper::encodeOrderId($order['id']).'</a> ';
            if ($customer_is_new) {
                $this->response .= sprintf_wp('from a %snew customer%s', '<a href="?action=customers#/id/'.$contact->getId().'">', '</a>');
            } else {
                $this->response .= sprintf_wp('from an %sexisting customer%s', '<a href="?action=customers#/id/'.$contact->getId().'">', '</a>');
            }
        } catch (Exception $e) {
            $this->errors = $e->getMessage();
        }
    }

    public static function generateContact(&$settings, $order_datetime)
    {
        if (waConfig::get('is_template')) {
            return;
        }

        $new_percent = ifset($settings['new_percent'], 0);
        if (mt_rand(0, 99) >= $new_percent) {
            //
            // Randomly select existing customer
            //
            $m = new waModel();
            $sql = "SELECT c.*
                    FROM wa_contact AS c
                        INNER JOIN (SELECT RAND()*(SELECT MAX(id) FROM wa_contact) AS id) AS t ON c.id >= t.id
                    WHERE c.create_datetime <= ?
                        AND c.is_company=0
                    ORDER BY c.id
                    LIMIT 1";
            $row = $m->query($sql, $order_datetime)->fetchAssoc();
            if ($row) {
                return new waContact($row);
            }
        }

        // Generate new customer
        if (ifset($settings['customer_api']) == 'randomuser') {
            return self::generateContactRandomuserMe($settings);
        } if (ifset($settings['customer_api']) == 'randomdatatools') {
            return self::generateContactRandomdatatools($settings);
        } else {
            return self::generateContactNoAPI($settings);
        }
    }

    protected static function generateContactRandomuserMe(&$settings)
    {
        $userdata = file_get_contents('https://randomuser.me/api/');
        if (!$userdata) {
            throw new waException('Unable to fetch data from httpы://api.randomuser.me/');
        }
        $userdata = json_decode($userdata, true);
        if (!$userdata || empty($userdata['results'][0])) {
            throw new waException('Unable to decode data from httpsы://api.randomuser.me/');
        }

        /*
            'gender' => 'female',
            'name' => [
                'title' => 'Mrs',
                'first' => 'Cathrine',
                'last' => 'Mundal',
            ],
            'location' => [
                'street' => [
                    'number' => 6598,
                    'name' => 'Dronning Mauds gate',
                ],
                'city' => 'Steinkjer',
                'state' => 'Description',
                'country' => 'Norway',
                'postcode' => '8325',
                'coordinates' => [
                    'latitude' => '41.6762',
                    'longitude' => '156.8232',
                ],
                'timezone' => [
                    'offset' => '+5:00',
                    'description' => 'Ekaterinburg, Islamabad, Karachi, Tashkent',
                ],
            ],
            'email' => 'cathrine.mundal@example.com',
            'login' => [
                'uuid' => '299aa2c3-a14d-487a-a128-289442700dc5',
                'username' => 'orangedog595',
                'password' => 'blazers',
                'salt' => 'dt1FDMyg',
                'md5' => '8891ba3629f5450bef71c9f8af1138d3',
                'sha1' => '5158c11d105574eb36b72ffb24f261b9b5727790',
                'sha256' => '8ae939bca6cd9b89b88ee1f5be5bab5b50ea1c1d28a64f14ec9528185b486de2',
            ],
            'dob' => [
                'date' => '1976-09-02T21:48:44.201Z',
                'age' => 46,
            ],
            'registered' => [
                'date' => '2007-05-24T07:45:40.066Z',
                'age' => 15,
            ],
            'phone' => '24008735',
            'cell' => '99318505',
            'id' => [
                'name' => 'FN',
                'value' => '02097648681',
            ],
            'picture' => [
                'large' => 'https://randomuser.me/api/portraits/women/25.jpg',
                'medium' => 'https://randomuser.me/api/portraits/med/women/25.jpg',
                'thumbnail' => 'https://randomuser.me/api/portraits/thumb/women/25.jpg',
            ],
            'nat' => 'NO',
        */
        $userdata = $userdata['results'][0];
        $c = new waContact();
        $c['sex'] = $userdata['gender'] == 'male' ? 'm' : 'f';
        $c['title'] = $userdata['name']['title'];
        $c['firstname'] = ucfirst($userdata['name']['first']);
        $c['lastname'] = ucfirst($userdata['name']['last']);
        $c['email'] = str_replace('example.com', self::pick(array(
            'sibmail.com',
            'mail.com',
            'yandex.com',
            'yahoo.com',
            'easy.com',
            'aol.com',
            'lycos.com',
        )), $userdata['email']).'.wa';
        $c['phone.home'] = $userdata['phone'];
        $c['phone.mobile'] = $userdata['cell'];

        // country by code
        $address = [
            'region' => $userdata['location']['state'],
            'street' => join(', ', $userdata['location']['street']),
            'city' => $userdata['location']['city'],
            'zip' => $userdata['location']['postcode'],
        ];

        if (!empty($userdata['nat'])) {
            $cm = new waCountryModel();
            $row = $cm->getByField('iso2letter', $userdata['nat']);
            if ($row) {
                $address['country'] = $row['iso3letter'];
            }
        }
        $c['address.shipping'] = $address;
        $c['address.billing'] = $address;

        // Contact photo to save later, when contact has ID
        $settings['contact_photo_url'] = ifset($userdata['picture']['large']);

        return $c;
    }

    protected static function generateContactRandomdatatools(&$settings)
    {
        // api.randomdatatools.ru
        $userdata = file_get_contents('https://api.randomdatatools.ru/');
        if (!$userdata) {
            throw new waException('Unable to fetch data from http://randus.ru/api.php');
        }
        $userdata = @json_decode($userdata, true);
        if (!$userdata) {
            throw new waException('Unable to decode data from http://randus.ru/api.php');
        }
        /*
        'LastName' => 'Ярмольник',
        'FirstName' => 'Евгения',
        'FatherName' => 'Григорьевна',
        'DateOfBirth' => '25.07.1992',
        'YearsOld' => 30,
        'Phone' => '+7 (998) 146-94-53',
        'Login' => 'evgeniya8253',
        'Password' => '8d103e7e3',
        'Email' => 'evgeniya8253@outlook.com',
        'Gender' => 'Женщина',
        'GenderCode' => 'woman',
        'PasportNum' => '4487 343786',
        'PasportSerial' => '4487',
        'PasportNumber' => 343786,
        'PasportCode' => '570-154',
        'PasportOtd' => 'ОВД России по г. Артем',
        'PasportDate' => '17.08.2018',
        'inn_fiz' => '894384489576',
        'inn_ur' => '6842923441',
        'snils' => '32887710213',
        'oms' => 8007683742791415,
        'ogrn' => '6118503898276',
        'kpp' => 873998417,
        'Address' => 'Россия, г. Артем, Школьный пер., д. 11 кв.120',
        'AddressReg' => 'Россия, г. Самара, Пролетарская ул., д. 4 кв.121',
        'Country' => 'Россия',
        'Region' => 'Чувашская Республика',
        'City' => 'г. Артем',
        'Street' => 'Школьный пер.',
        'House' => 11,
        'Apartment' => 120,
        'bankBIK' => 693024011,
        'bankCorr' => '40609757100000006324',
        'bankINN' => 5477430254,
        'bankKPP' => 949503060,
        'bankNum' => '40939922700000003323',
        'bankClient' => 'Evgeniya YArmolnik',
        'bankCard' => '4807 2021 9959 5548',
        'bankDate' => '03/23',
        'bankCVC' => 904,
        'EduSpecialty' => 'Репродуктолог',
        'EduProgram' => '31.05.01 Лечебное дело',
        'EduName' => 'Первый Московский государственный медицинский университет имени И.М. Сеченова',
        'EduDocNum' => '121858 6323679',
        'EduRegNumber' => '27-909-281',
        'EduYear' => 2017,
        'CarBrand' => 'Geely',
        'CarModel' => 'Emgrand X7',
        'CarYear' => 2019,
        'CarColor' => 'Серо-бежевый',
        'CarNumber' => 'Т464ТУ74',
        'CarVIN' => 'SR6KP748551984118',
        'CarSTS' => '7586 360908',
        'CarSTSDate' => '27.03.2020',
        'CarPTS' => '49АА 648290',
        'CarPTSDate' => '07.05.2019',
        */

        $c = new waContact();
        $c['firstname'] = $userdata['FirstName'];
        $c['lastname'] = $userdata['LastName'];
        $c['middlename'] = $userdata['FatherName'];
        $c['birthday'] = date('Y-m-d', strtotime($userdata['DateOfBirth']));
        $address = array(
            'country' => 'rus',
            'city' => $userdata['City'],
            'street' => sprintf(self::pick(array(
                '%s, %s-%s',
                '%s, %s %s',
                '%s, %s, %s',
                '%s, дом %s, квартира %s',
                'улица %s, дом %s, квартира %s',
                'ул. %s, д. %s, кв. %s',
            )), $userdata['Street'], $userdata['House'], $userdata['Apartment']),
        );
        $c['address.shipping'] = $address;
        $c['address.billing'] = $address;

        $c['phone'] = $userdata['Phone'];
        $c['email'] = $userdata['Login'].'@'.self::pick(array(
            'yandex.ru',
            'ya.ru',
            'mail.ru',
            'rambler.ru',
            'sibmail.com',
            'km.ru',
            'nextmail.ru',
            'online.ua',
            'ua.fm',
            'post.su',
            'mail.com',
            'yandex.com',
            'yahoo.com',
            'easy.com',
            'aol.com',
            'lycos.com',
        )).'.wa';

        return $c;
    }

    protected static function generateContactNoAPI(&$settings)
    {
        if (wa()->getLocale() == 'ru_RU') {
            $firsts = array('Дмитрий', 'Леонид', 'Юрий', 'Сергей', 'Максим', 'Владислав', 'Владимир', 'Антон', 'Степан', 'Ярослав', 'Том', 'Филипп', 'Артур');
            $lasts = array('Иванов', 'Смирнов', 'Кузнецов', 'Попов', 'Васильев', 'Петров', 'Соколов', 'Лебедев', 'Морозов', 'Петров', 'Медведев', 'Титов');
        } else {
            $firsts = array('John', 'Sam', 'Matthew', 'Steve', 'Max', 'Donald', 'Rick', 'Liam', 'Noah', 'Ethan', 'Mason');
            $lasts = array('Smith', 'Johnson', 'Williams', 'Jones', 'Davis', 'Taylor', 'Thomas', 'Harris', 'Potter', 'Lee', 'Scott', 'Moore');
        }

        $c = new waContact();
        $c['firstname'] = self::pick($firsts);
        $c['lastname'] = self::pick($lasts);
        $c['sex'] = 'm';
        $c['email'] = shopHelper::transliterate($c['name'], 1).'@'.self::pick(array(
            'yandex.ru',
            'ya.ru',
            'mail.ru',
            'rambler.ru',
            'sibmail.com',
            'km.ru',
            'nextmail.ru',
            'online.ua',
            'ua.fm',
            'post.su',
            'mail.com',
            'yandex.com',
            'yahoo.com',
            'easy.com',
            'aol.com',
            'lycos.com',
        )).'.wa';

        return $c;
    }

    protected static function pick($arr)
    {
        return ifset($arr[array_rand($arr)]);
    }

    public static function generateOrderData($settings, $contact)
    {
        // Prepare a fake cart
        $code = md5(uniqid(time(), true));
        $m = $cart_items_model = new shopCartItemsModel();
        $item_row = array(
            'code' => $code,
            'contact_id' => $contact->getId(),
            'create_datetime' => $settings['order_datetime'],
            'quantity' => 1,
            'type' => 'product',
            'parent_id' => null,
            'service_id' => null,
            'service_variant_id' => null,
            'product_id' => null,
            'sku_id' => null,
        ) + $cart_items_model->getEmptyRow();
        unset($item_row['id']);

        // Select random skus from the database
        $row = $m->query("SELECT MIN(id) AS min_id, MAX(id) AS max_id FROM shop_product_skus")->fetchAssoc();
        $sql = "SELECT s.id AS sku_id, s.product_id
                FROM shop_product_skus AS s
                    INNER JOIN (SELECT {$row['min_id']} + RAND()*(".($row['max_id'] - $row['min_id']).") AS id) AS t ON s.id >= t.id
                WHERE primary_price > 0 AND available
                ORDER BY s.id
                LIMIT 1";
        $sql = join(' UNION ', array_fill(0, mt_rand(1, 5), "({$sql})")); // timelord science!
        foreach($m->query($sql) as $row) {
            $row['item_id'] = $cart_items_model->insert($row + $item_row);
        }

        // Select a random service
        if (!empty($row['item_id']) && mt_rand(1, 100) > 80) {
            $product = wao(new shopProductModel())->getById($row['product_id']);
            $services = wao(new shopTypeServicesModel())->getServiceIds($product['type_id']);
            $services = array_merge($services, wao(new shopProductServicesModel())->getServiceIds($product['id']));
            if ($services) {
                $services = wao(new shopServiceModel())->getById(array_unique($services));
                $service = self::pick($services);
                $cart_items_model->insert(array(
                    'product_id' => $product['id'],
                    'sku_id' => $row['sku_id'],
                    'service_id' => $service['id'],
                    'service_variant_id' => $service['variant_id'],
                    'parent_id' => $row['item_id'],
                    'type' => 'service',
                ) + $item_row);
            }
        }

        // Get data from fake shop cart
        unset($_COOKIE[shopCart::COOKIE_KEY]);
        wa()->getStorage()->del('shop/cart');
        $cart = new shopCart($code);
        $items = $cart->items(false);
        $total = $cart->total(false);
        $cart->clear();

        // remove id from items
        foreach ($items as &$item) {
            unset($item['id'], $item['parent_id']);
        }
        unset($item);

        $order = array(
            'total'   => $total,
            'params'  => array(),
            'contact' => $contact,
            'items'   => $items,
        );

        $order['discount_description'] = null;
        $order['discount'] = shopDiscounts::apply($order, $order['discount_description']);

        // Select shipping method randomly
        $order['params']['shipping'] = 0;
        $plugin_model = new shopPluginModel();
        try {
            $shipping_plugins = $plugin_model->listPlugins('shipping');
            $order['params']['shipping_id'] = array_rand($shipping_plugins);
            $plugin_info = $shipping_plugins[$order['params']['shipping_id']];
            $shipping_plugin = shopShipping::getPlugin(null, $order['params']['shipping_id']);
            $order['params']['shipping_plugin'] = $plugin_info['plugin'];
            $order['params']['shipping_name'] = $plugin_info['name'];
            @$rates = $shipping_plugin->getRates($items, $contact['address']);
            if (!empty($rates) && is_array($rates)) {
                $rate = reset($rates);
                $rate_id = key($rates);
                $order['params']['shipping_rate_id'] = $rate_id;
                $order['shipping'] = $rate['rate'];
            } else {
                $order['shipping'] = 0;
            }
        } catch (Exception $e) {
        }

        // Select payment method randomly
        try {
            $payment_plugins = $plugin_model->listPlugins('payment');
            $order['params']['payment_id'] = array_rand($payment_plugins);
            $plugin_info = $payment_plugins[$order['params']['payment_id']];
            $order['params']['payment_plugin'] = $plugin_info['plugin'];
            $order['params']['payment_name'] = $plugin_info['name'];
        } catch (Exception $e) {
        }

        // Storefront
        $order['params']['storefront'] = ifset($settings['storefront']);
        if ($order['params']['storefront']) {
            $order['params']['sales_channel'] = 'storefront:'.$order['params']['storefront'];
        } else {
            $order['params']['sales_channel'] = 'backend:';
        }

        // Referring site or UTM campaign
        if ($settings['source_type'] == 'referer') {
            $order['params']['referer'] = ifset($settings['source']);
            if (empty($order['params']['referer']) || $order['params']['referer'] == 'http://') {
                unset($order['params']['referer']);
            } else {
                $ref_parts = @parse_url($order['params']['referer']);
                if (!empty($ref_parts['host'])) {
                    $order['params']['referer_host'] = $ref_parts['host'];
                }
            }
        } else if (!empty($settings['source'])) {
            $order['params']['utm_campaign'] = $settings['source'];
        }

        // landing page
        if (!empty($order['params']['storefront'])) {
            $order['params']['landing'] = 'http://'.$order['params']['storefront'].'/';
            if (mt_rand(1, 100) > 70) {
                $sql = "SELECT full_url FROM shop_category WHERE parent_id=0 ORDER BY RAND() LIMIT 1";
                $order['params']['landing'] .= $m->query($sql)->fetchField().'/';
            }
        }

        $order['params']['ip'] = waRequest::getIp();
        $order['params']['user_agent'] = waRequest::getUserAgent();

        // Addresses
        foreach (array('shipping', 'billing') as $ext) {
            $address = $contact->getFirst('address.'.$ext);
            if ($address) {
                foreach ($address['data'] as $k => $v) {
                    $order['params'][$ext.'_address.'.$k] = $v;
                }
            }
        }

        return $order;
    }

    public static function saveOrder($settings, $data)
    {
        //
        // Create the order by hand (and not via shopWorkflow)
        // to avoid sending notification emails, sms, etc.
        //

        $m = $order_model = new shopOrderModel();

        // Save contact
        if (!empty($data['contact'])) {
            if (is_numeric($data['contact'])) {
                $contact = new waContact($data['contact']);
            } else {
                /** @var waContact $contact */
                $contact = $data['contact'];
                if (!$contact->getId()) {
                    $contact->save();
                    $m->exec("UPDATE wa_contact SET create_datetime=? WHERE id=?", array($settings['order_datetime'], $contact->getId()));
                }
            }
        } else {
            $data['contact'] = $contact = wa()->getUser();
        }
        $contact->addToCategory('shop');

        $currency = wa('shop')->getConfig()->getCurrency(false);
        $row = wao(new shopCurrencyModel())->getById($currency);
        $rate = $row['rate'];

        // Calculate subtotal, taking currency convertion into account
        $subtotal = 0;
        foreach ($data['items'] as &$item) {
            if ($currency != $item['currency']) {
                $item['price'] = shop_currency($item['price'], $item['currency'], null, false);
                if (!empty($item['purchase_price'])) {
                    $item['purchase_price'] = shop_currency($item['purchase_price'], $item['currency'], null, false);
                }
                $item['currency'] = $currency;
            }
            $subtotal += $item['price'] * $item['quantity'];
        }
        unset($item);

        // Make sure discount is specified
        if (empty($data['discount'])) {
            $data['discount'] = 0;
            $data['discount_description'] = null;
        } else if (empty($data['discount_description'])) {
            $data['discount_description'] = null;
        }

        // Calculate taxes
        $shipping_address = $contact->getFirst('address.shipping');
        if (!$shipping_address) {
            $shipping_address = $contact->getFirst('address');
        }
        $billing_address = $contact->getFirst('address.billing');
        if (!$billing_address) {
            $billing_address = $contact->getFirst('address');
        }
        $discount_rate = $subtotal ? ($data['discount'] / $subtotal) : 0;
        $taxes = shopTaxes::apply($data['items'], array(
            'shipping' => isset($shipping_address['data']) ? $shipping_address['data'] : array(),
            'billing' => isset($billing_address['data']) ? $billing_address['data'] : array(),
            'discount_rate' => $discount_rate
        ));
        $tax = $tax_included = 0;
        foreach ($taxes as $t) {
            if (isset($t['sum'])) {
                $tax += $t['sum'];
            }
            if (isset($t['sum_included'])) {
                $tax_included += $t['sum_included'];
            }
        }

        // Save order
        $order = array(
            'state_id' => 'new',
            'total' => $subtotal - $data['discount'] + $data['shipping'] + $tax,
            'currency' => $currency,
            'rate' => $rate,
            'tax' => $tax_included + $tax,
            'discount' => $data['discount'],
            'shipping' => $data['shipping'],
            'comment' => isset($data['comment']) ? $data['comment'] : '',
            'contact_id' => $contact->getId(),
            'create_datetime' => $settings['order_datetime'],
        );
        $order_id = $order_model->insert($order);

        // Create record in shop_customer, or update existing record
        $scm = new shopCustomerModel();
        $scm->updateFromNewOrder($order['contact_id'], $order_id, ifset($data['params']['referer_host']));

        // save items
        $items_model = new shopOrderItemsModel();
        $parent_id = null;
        foreach ($data['items'] as $item) {
            $item['order_id'] = $order_id;
            if ($item['type'] == 'product') {
                $parent_id = $items_model->insert($item);
            } elseif ($item['type'] == 'service') {
                $item['parent_id'] = $parent_id;
                $items_model->insert($item);
            }
        }

        // Save order params
        if (!empty($data['params'])) {
            $params_model = new shopOrderParamsModel();
            $params_model->set($order_id, $data['params']);
        }

        // Write discounts description to order log
        if (!empty($data['discount_description']) && !empty($data['discount'])) {
            $order_log_model = new shopOrderLogModel();
            $order_log_model->add(array(
                'order_id' => $order_id,
                'contact_id' => $order['contact_id'],
                'before_state_id' => $order['state_id'],
                'after_state_id' => $order['state_id'],
                'text' => $data['discount_description'],
                'action_id' => '',
                'datetime' => $settings['order_datetime'],
            ));
        }

        $shop_order = new shopOrder($order_id);
        $workflow = new shopMarketingPromoWorkflow($shop_order);
        $workflow->run();

        return $order_model->getById($order_id);
    }

    public static function processOrder(&$settings, $order)
    {
        $m = $order_model = new shopOrderModel();
        $action_timestamp = min(time(), strtotime($order['create_datetime']) + mt_rand(3*3600, 3*24*3600));
        if (in_array(date('w', $action_timestamp), array('0', '6'))) {
            $action_timestamp += 3600*24*2;
        }

        $action_datetime = date('Y-m-d H:i:s', $action_timestamp);
        $settings['action_datetime'] = $action_datetime;
        switch(ifempty($settings['action'])) {
            case 'complete':
            case 'pay':
                $state_id = $settings['action'] == 'pay' ? 'paid' : 'completed';
                $update = array(
                    'paid_year' => date('Y', $action_timestamp),
                    'paid_quarter' => floor((date('n', $action_timestamp) - 1) / 3) + 1,
                    'paid_month' => date('n', $action_timestamp),
                    'paid_date' => date('Y-m-d', $action_timestamp),
                    'update_datetime' => $action_datetime,
                    'state_id' => $state_id,
                );
                if ($settings['customer_is_new'] && $settings['action'] == 'complete') {
                    $update['is_first'] = 1;
                }
                $order_model->updateById($order['id'], $update);
                shopCustomer::recalculateTotalSpent($order['contact_id']);

                // Order log
                $data = array(
                    'order_id' => $order['id'],
                    'action_id' => $settings['action'],
                    'before_state_id' => $order['state_id'],
                    'after_state_id' => $state_id,
                );

                $order_log_model = new shopOrderLogModel();
                $data['id'] = $order_log_model->add($data);
                break;
            case 'ship':
            case 'delete':
                $state_id = $settings['action'] == 'ship' ? 'shipped' : 'deleted';
                $update = array(
                    'update_datetime' => $action_datetime,
                    'state_id' => $state_id,
                );
                $order_model->updateById($order['id'], $update);

                // Order log
                $data = array(
                    'order_id' => $order['id'],
                    'action_id' => $settings['action'],
                    'before_state_id' => $order['state_id'],
                    'after_state_id' => $state_id,
                );

                $order_log_model = new shopOrderLogModel();
                $data['id'] = $order_log_model->add($data);
                break;
            default:
                unset($settings['action_datetime']);
                return;
        }

        if (!empty($data['id'])) {
            $order_log_model->updateById($data['id'], array(
                'datetime' => $action_datetime,
            ));
        }
    }

    public static function saveContactPhoto($settings, $contact)
    {
        $photo_percent = ifset($settings['photo_percent'], 0);
        if (empty($settings['contact_photo_url']) || !$contact->getId() || mt_rand(0, 99) >= $photo_percent) {
            return;
        }
        if (substr($settings['contact_photo_url'], 0, 7) != 'http://' && substr($settings['contact_photo_url'], 0, 8) != 'https://') {
            return;
        }
        $image_data = @file_get_contents($settings['contact_photo_url']);
        if (!$image_data) {
            return;
        }

        $rand = mt_rand();
        $dir = waContact::getPhotoDir($contact->getId(), true);
        $filename = wa()->getDataPath("{$dir}$rand.original.jpg", true, 'contacts');
        $cropped_filename = wa()->getDataPath("{$dir}{$rand}.jpg", true, 'contacts');
        $full_dir = wa()->getDataPath($dir, true, 'contacts');
        @waFiles::create($full_dir);
        @file_put_contents($filename, $image_data);
        @file_put_contents($cropped_filename, $image_data);
        if (file_exists($filename) && file_exists($cropped_filename)) {
            $contact['photo'] = $rand;
            $contact->save();
        }
    }
}

