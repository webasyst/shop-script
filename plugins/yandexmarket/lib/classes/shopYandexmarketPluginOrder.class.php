<?php

/**
 * Class shopYandexmarketPluginOrder
 *
 * @property-read string $status
 * @property-read int $campaign_id
 * @property-read string $sub_status
 * @property-read string $sub_status_description
 * @property-read int $shipping_id
 * @property-read int $yandex_id
 *
 * @property array[][string]string  $items[]['raw_data']
 */
class shopYandexmarketPluginOrder extends waOrder
{
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_UNPAID = 'UNPAID';

    /**
     * @param $json
     * @param shopYandexmarketPlugin $plugin
     * @param bool $save_contact
     * @return shopYandexmarketPluginOrder
     * @throws waException
     */
    public static function createFromJson($json, $plugin, $save_contact = false)
    {
        if (!empty($json['cart'])) {
            $action = 'cart';
        } elseif (!empty($json['order'])) {
            $action = 'order';
        } else {
            throw new waException('Invalid data');
        }

        $json = $json[$action];

        $data = array(
            'currency'         => ifset($json['currency'], 'RUB'),
            'items'            => array(),
            'shipping_address' => array(),
            'description'      => '',
        );

        if (!empty($json['fake'])) {
            $data['description'] = 'Тестовый заказ';
        }

        if ($data['currency'] == 'RUR') {
            $data['currency'] = 'RUB';
        }

        switch ($action) {
            case 'order':
                $data["yandex_id"] = $json['id'];
                if (isset($json['status'])) {
                    $data['status'] = $json['status'];
                }

                if (isset($json['substatus'])) {
                    $data['sub_status'] = $json['substatus'];
                }

                if (ifset($data['status']) == self::STATUS_CANCELLED) {
                    $cancel_description = array(
                        'RESERVATION_EXPIRED'   => 'покупатель не завершил оформление зарезервированного заказа вовремя',
                        'USER_NOT_PAID'         => 'покупатель не оплатил заказ (для типа оплаты PREPAID)',
                        'USER_UNREACHABLE'      => 'не удалось связаться с покупателем',
                        'USER_CHANGED_MIND'     => 'покупатель отменил заказ по собственным причинам',
                        'USER_REFUSED_DELIVERY' => 'покупателя не устраивают условия доставки',
                        'USER_REFUSED_PRODUCT'  => 'покупателю не подошел товар',
                        'SHOP_FAILED'           => 'магазин не может выполнить заказ',
                        'USER_REFUSED_QUALITY'  => 'покупателя не устраивает качество товара',
                        'REPLACING_ORDER'       => 'покупатель изменяет состав заказа',
                        'PROCESSING_EXPIRED'    => 'магазин не обработал заказ вовремя',
                    );
                    $data['sub_status_description'] = ifset($cancel_description[$data['sub_status']]);
                }

                if (isset($json['fake'])) {
                    $data['test'] = $json['fake'];
                }

                switch (ifset($json['paymentType'])) {
                    case 'PREPAID':
                        switch (ifset($json['paymentMethod'])) {
                            case 'YANDEX':
                                $data['payment_name'] = 'Яндекс.Деньги';
                                break;
                            default:
                                break;
                        }
                        break;
                    case 'POSTPAID':
                        switch (ifset($json['paymentMethod'])) {
                            case 'CASH_ON_DELIVERY':
                                $data['payment_name'] = 'Наличными';
                                break;
                            case 'CARD_ON_DELIVERY':
                                $data['payment_name'] = 'Яндекс.Деньги мобильный терминал';
                                break;
                            default:
                                break;
                        }
                        break;
                }

                if (isset($json['total'])) {
                    $data['total'] = floatval($json['total']);
                }

                if (isset($json['itemsTotal'])) {
                    $data['subtotal'] = floatval($json['itemsTotal']);
                }
                break;
        }

        $product_ids = array();
        $feeds = array();
        foreach ($json['items'] as $item) {
            /**
             * @var array[string]mixed $item
             * @var array[string]int $item[feedId]
             * @var array[string]string $item['offerId']
             * @var array[string]string $item['offerName']
             * @var array[string]int $item['count']
             * @var array[string]string $item['feedCategoryId']
             */

            $feeds[] = $item['feedId'];
            $product_ids[] = intval(preg_replace('@\D.*$@', '', $item['offerId']));
        }

        $feeds = array_unique($feeds);
        $profile = null;
        $profile_map = array();
        if ($feeds) {
            foreach ($feeds as $feed_id) {
                list($path, $profile_id, $campaign_id) = $plugin->getInfoByFeed($feed_id);
                if (!empty($data['campaign_id']) && ($data['campaign_id'] != $campaign_id)) {
                    //WTF?
                }
                $data['campaign_id'] = $campaign_id;
                $profile_map[$feed_id] = $profile_id;
            }
        }

        if ($product_ids) {
            $product_ids = array_unique($product_ids);
            $product_model = new shopProductModel();

            $product_ids = $product_model->select('id,name,currency,sku_id')->where('id IN (i:product_ids)', compact('product_ids'))->fetchAll('id', true);
        }

        $sku_ids = array();

        foreach ($json['items'] as $item) {

            /**
             * @var int $item ['feedId']
             */
            $id = preg_split('@\D+@', $item['offerId'], 2);
            if (count($id) == 2) {
                $product_id = reset($id);
                $sku_id = end($id);
                $product = ifset($product_ids[$product_id]);
            } else {
                $product_id = reset($id);
                $product = ifset($product_ids[reset($id)]);
                $sku_id = $product['sku_id'];
            }

            $sku_ids[] = $sku_id;

            $data['items'][] = array(
                'create_datetime' => date('Y-m-d H:i:s'),
                'type'            => 'product',
                'product_id'      => $product_id,
                'sku_id'          => $sku_id,
                'sku_code'        => '',
                'name'            => ifset($product['name'], $item['offerName']),
                'count'           => 0,
                'price'           => false,
                'profile_id'      => ifset($profile_map[$item['feedId']]),
                'raw_data'        => $item,
            );

        }

        $skus_model = new shopProductSkusModel();
        $skus = $skus_model->select('id,count,sku,primary_price')->where('id IN (i:sku_ids)', compact('sku_ids'))->fetchAll('id');

        foreach ($data['items'] as &$item) {
            if (isset($skus[$item['sku_id']])) {
                $sku = $skus[$item['sku_id']];
                $item['count'] = ($sku['count'] === null) ? 9999 : $sku['count'];
                $item['sku_code'] = $sku['sku'];

                if (true) {
                    #@todo configure it
                    $item['count'] = min($item['count'], $item['raw_data']['count']);
                }


                $item['price'] = ifset($sku['primary_price'], false);
                if ($item['price']) {
                    $product = ifset($product_ids[$item['product_id']]);
                    $item['price'] = shop_currency($item['price'], $product['currency'], $data['currency'], false);
                }
            }

            unset($item);
        }


        if (!empty($json['notes'])) {
            $data['description'] .= ($data['description'] ? "\n" : '').$json['notes'];
        }

        if (!empty($json['delivery'])) {
            $delivery = $json['delivery'];
            if (!empty($delivery['type'])) {
                $data['shipping_id'] = $delivery['id'];
                $data['shipping_name'] = $delivery['serviceName'];
                $data['shipping'] = floatval($delivery['price']);
            }

            $address = array();

            $formats = array(
                'subway' => 'метро %s',
                'house'  => 'дом %s',
                'block'  => 'корпус %s',
                'floor'  => 'этаж %s',
            );

            if (!empty($delivery['address'])) {
                $address_map = array(
                    'country',
                    'city',
                    'subway'   => 'street',
                    'postcode' => 'zip',
                    'street'   => 'street',
                    'house'    => 'street',
                    'block'    => 'street',
                    'floor'    => 'street',
                );

                foreach ($address_map as $field => $target) {
                    if (is_int($field)) {
                        $field = $target;
                    }

                    if (isset($delivery['address'][$field])) {
                        $address[$target][$field] = trim($delivery['address'][$field]);
                        unset($delivery['address'][$field]);
                    }
                }

            } else {

                $region = ifset($delivery['region']);
                $address_map = array(
                    'REGION'                      => 'region',
                    'COUNTRY'                     => array('name' => 'country_name', 'id' => 'country',),
                    'COUNTRY_DISTRICT'            => array('name' => 'region_name', 'id' => 'region'),
                    'SUBJECT_FEDERATION'          => array('name' => 'region_name', 'id' => 'region'),
                    'SUBJECT_FEDERATION_DISTRICT' => array('name' => 'city', 'id' => 'zip'),
                    'CITY'                        => 'city',
                    'VILLAGE'                     => 'city',
                    'CITY_DISTRICT'               => 'street',
                    'SUBWAY_STATION'              => 'subway',
                    'OTHER'                       => '',
                );

                while ($region !== null) {
                    $field = ifset($address_map[$region['type']], false);
                    if ($field) {
                        if (is_array($field)) {
                            foreach ($field as $property => $key) {
                                $address[$key][] = trim($region[$property]);
                            }
                        } else {
                            $address[$field][] = trim($region['name']);
                        }
                    }
                    $region = ifset($region['parent']);
                }
            }


            foreach ($address as &$address_item) {
                $address_item = array_filter($address_item, 'strlen');
                if ($address_item) {
                    foreach ($address_item as $type => &$address_item_element) {
                        if (isset($formats[$type])) {
                            $address_item_element = sprintf($formats[$type], $address_item_element);
                        }
                        unset($address_item_element);
                    }
                    $address_item = implode(' ', $address_item);
                }
                unset($address_item);
            }
            $address = array_filter($address, 'strlen');
            if (!empty($address)) {
                $data['shipping_address'] = $address;
            }
        }

        $contact = null;

        if (!empty($data['yandex_id'])) {
            $order_params_model = new shopOrderParamsModel();
            $order_param = $order_params_model->getByField(array('name' => 'yandexmarket.id', 'value' => $data['yandex_id']));
            if ($order_param) {
                $data['id'] = ifset($order_param['order_id']);
            }
        }

        if (!empty($json['buyer'])) {
            $buyer = $json['buyer'];
            $contact_id = null;

            if ($data['id']) {
                $order_model = new shopOrderModel();
                $order = $order_model->getById($data['id']);
                if ($order['contact_id']) {
                    $contact_id = $order['contact_id'];
                }
            }

            if (!$contact_id) {
                $guest_checkout = wa('shop')->getConfig()->getOption('guest_checkout');

                if ($guest_checkout == 'merge_email') {
                    $email_model = new waContactEmailsModel();
                    $contact_id = $email_model->getContactIdByEmail($buyer['email']);
                }
            }

            if ($save_contact) {

                $contact = new waContact($contact_id);
                $contact['firstname'] = $buyer['firstName'];
                $contact['lastname'] = $buyer['lastName'];
                $contact['email'] = $buyer['email'];
                $contact['phone'] = $buyer['phone'];
                $contact['create_datetime'] = date('Y-m-d H:i:s');
                $contact['create_app_id'] = 'shop';

                $errors = $contact->save();
                if ($contact_id) {
                    waLog::log("Contact {$contact_id} was updated: ".var_export($buyer, true), 'shop/plugins/yandexmarket/order.log');
                }
                if ($errors) {
                    waLog::log('Error occurs during save contact: '.var_export($errors, true), 'shop/plugins/yandexmarket/error.log');
                } else {
                    $contact_id = $contact->getId();
                    waLog::log("Contact {$contact_id} was created: ".var_export($buyer, true), 'shop/plugins/yandexmarket/order.log');
                }
            }
        } else {
            $contact_id = $plugin->getSettings('contact_id');
        }
        if (empty($contact) && $contact_id) {
            $contact = new waContact($contact_id);
        }
        $data['contact'] = $contact;

        return new self($data);
    }

    /**
     * @param SimpleXMLElement $xml
     * @param shopYandexmarketPlugin $plugin
     * @return shopYandexmarketPluginOrder
     * @todo complete method
     */
    public static function createFromXml($xml, $plugin)
    {
        $data = array(
            'items' => array(),
        );

        return new self($data);
    }
}
