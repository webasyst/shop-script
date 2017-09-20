<?php

/**
 * Class shopYandexmarketPluginOrder
 *
 * @property-read string $status
 * @property-read int $campaign_id
 * @property-read int $region_id
 * @property-read string $sub_status
 * @property-read string $sub_status_description
 * @property-read int $shipping_id
 * @property-read string $shipping_name
 * @property-read string $shipping_plugin
 * @property-read string $shipping_rate_id
 * @property-read string $shipping_est_delivery
 * @property-read int $outlet_id
 * @property-read int $yandex_id
 * @property-read int $profile_id
 * @property-read int $delivery_from
 * @property-read int $delivery_before
 * @property-read int $delivery_cost
 * @property bool $over_sell
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
            $data['description'] .= "Тестовый заказ\n";
        }

        if (!empty($json['isBooked'])) {
            $data['description'] .= "Заказ оформлен в рамках программы «Забронировать на Маркете».\n";
        }

        if (!empty($json['notes'])) {
            $data['description'] .= $json['notes'];
        }

        if ($data['currency'] == 'RUR') {
            $data['currency'] = 'RUB';
        }

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
        }

        $feeds = array_unique($feeds);
        $profile = null;
        $profile_map = array();
        $profile_id = null;
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

        if (empty($profile_id)) {
            //Bad...
            throw new waException('Profile not found');
        } elseif (count($profile_map) > 1) {
            throw new waException('Multiple feeds not supported');
        }

        $profile = null;
        $data['items'] = shopYandexmarketPluginRunController::getCartItems($json['items'], $profile_id, $data['currency'], $profile);

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
                    $data['sub_status_description'] = shopYandexmarketPlugin::describeSubStatus($data['sub_status']);
                }

                if (isset($json['fake'])) {
                    $data['test'] = $json['fake'];
                }

                switch (ifset($json['paymentType'])) {
                    case 'PREPAID':
                        switch (ifset($json['paymentMethod'])) {
                            case 'YANDEX':
                                $data['payment_name'] = 'Яндекс.Деньги';
                                if (ifset($json['status']) === self::STATUS_PROCESSING) {
                                    $data['paid_datetime'] = date('d.m.Y H:i:s');
                                }
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
                                $data['payment_name'] = 'мобильный терминал «Яндекс.Деньги»';
                                if (!empty($profile['payment']['CARD_ON_DELIVERY'])) {
                                    if ($payment_plugin = shopPayment::getPluginInfo($profile['payment']['CARD_ON_DELIVERY'])) {
                                        $data['payment_id'] = $payment_plugin['id'];
                                        $data['payment_plugin'] = $payment_plugin['plugin'];
                                        $data['payment_name'] = $payment_plugin['name'];
                                    }
                                }

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

        $data['delivery_from'] = array();
        $data['delivery_before'] = array();
        $data['delivery_cost'] = array();
        foreach ($data['items'] as $item) {
            if (isset($item['shipping'])) {
                if (isset($item['shipping']['days']) && ($item['shipping']['days'] !== '')) {
                    $days = shopYandexmarketPlugin::getDays($item['shipping']['days']);

                    if ($days) {
                        $data['delivery_from'][] = min($days);
                        $data['delivery_before'][] = max($days);
                    }
                }
                if (isset($item['shipping']['cost']) && ($item['shipping']['cost'] !== '')) {
                    $data['delivery_cost'][] = $item['shipping']['cost'];
                }
            }
        }

        $data['delivery_from'] = $data['delivery_from'] ? max($data['delivery_from']) : null;
        $data['delivery_before'] = $data['delivery_before'] ? max($data['delivery_before']) : null;
        $data['delivery_cost'] = $data['delivery_cost'] ? max($data['delivery_cost']) : null;

        if (!empty($json['delivery'])) {
            $delivery = $json['delivery'];
            if (!empty($delivery['type'])) {
                if (strpos($delivery['id'], '.')) {
                    list($type, $id) = explode('.', $delivery['id'], 2);
                    switch ($type) {
                        case 'outlet':
                            $data['shipping_name'] = sprintf('Точка продаж %s', $delivery['serviceName']);
                            $data['outlet_id'] = $id;
                            break;
                        case 'shipping':
                            if (strpos($id, '.')) {
                                list($data['shipping_id'], $data['shipping_rate_id']) = explode('.', $id);
                            } else {
                                $data['shipping_id'] = $id;
                            }
                            $data['shipping_name'] = $delivery['serviceName'];
                            $plugin_model = new shopPluginModel();
                            $shipping_plugin = $plugin_model->getPlugin($data['shipping_id'], shopPluginModel::TYPE_SHIPPING);
                            if ($shipping_plugin) {
                                $data['shipping_plugin'] = $shipping_plugin['plugin'];
                                $data['shipping_name'] = $shipping_plugin['name'];
                            }
                            if (!empty($delivery['outlet']['id'])) {
                                $data['outlet_id'] = $delivery['outlet']['id'];
                            }
                            break;
                    }
                } else {
                    $data['shipping_name'] = $delivery['serviceName'];
                }
                $data['shipping'] = floatval($delivery['price']);

                if (!empty($delivery['dates'])) {
                    $data['shipping_est_delivery'] = implode('—', $delivery['dates']);
                }
            }
            $home_region_id = null;
            if (!empty($data['campaign_id'])) {
                if ($home_region = $plugin->getCampaignRegion($data['campaign_id'])) {
                    $home_region_id = ifset($home_region['id']);
                    $data['region_id'] = shopYandexmarketPlugin::getOutletRegion($home_region);
                }
            }

            $address = self::parseAddress($delivery, $home_region_id);

            if (!empty($delivery['region'])) {
                $data['region_id'] = shopYandexmarketPlugin::getOutletRegion($delivery['region']);
            }

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


                if (!empty($data['shipping_address'])) {
                    $contact['address.shipping'] = array_filter($data['shipping_address']);
                }
                $errors = $contact->save();
                $save_contact = false;
                if ($contact_id) {
                    waLog::log("Contact {$contact_id} was updated: ".var_export($buyer, true), 'shop/plugins/yandexmarket/order.log');
                } else {
                    waLog::log("Contact was created: ".var_export($buyer, true), 'shop/plugins/yandexmarket/order.log');
                }
                if ($errors) {
                    waLog::log('Error occurs during save contact: '.var_export($errors, true), 'shop/plugins/yandexmarket/order.error.log');
                } else {
                    $contact_id = $contact->getId();
                    waLog::log("Contact {$contact_id} was created: ".var_export($buyer, true), 'shop/plugins/yandexmarket/order.log');
                }
            }
        } else {
            $contact_id = $plugin->getSettings('contact_id');
            $contact_id = null;
        }
        if (empty($contact) /*&& $contact_id*/) {
            $contact = new waContact($contact_id);

            if (!empty($data['shipping_address'])) {
                $contact['address.shipping'] = array_filter($data['shipping_address']);
            }
            if ($save_contact) {
                $errors = $contact->save();
                if ($errors) {
                    waLog::log('Error occurs during save contact: '.var_export($errors, true), 'shop/plugins/yandexmarket/order.error.log');
                } else {
                    $contact_id = $contact->getId();
                    waLog::log("Contact {$contact_id} was created with address: ".var_export($data['shipping_address'], true), 'shop/plugins/yandexmarket/order.log');
                }
            }
        }

        $data['contact'] = $contact;
        $data['over_sell'] = false;

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

    public static function parseAddress($data, $home_region_id = null, $format = false)
    {
        $home = false;


        $formats = array(
            'subway'     => 'метро %s',
            'house'      => 'дом %s',
            'block'      => 'корпус %s',
            'floor'      => 'этаж %s',
            //update order
            'entrance'   => 'подъезд %s',
            'entryphone' => 'домофон %s',
            'apartment'  => 'кв %s',
        );


        #region
        $region = ifset($data['region'], $data);
        $address_map = array(
            'REGION'                      => 'region',
            'COUNTRY'                     => array('name' => 'country_name', 'id' => 'country_id',),// страна
            'COUNTRY_DISTRICT'            => array('name' => 'region_name', 'id' => 'region_id'),// федеральный округ — устарело
            'REPUBLIC'                    => array('name' => 'region_name', 'id' => 'region_id'),// — субъект федерации;
            'REPUBLIC_AREA'               => '',// — район субъекта федерации;
            'SUBJECT_FEDERATION'          => array('name' => 'region_name', 'id' => 'region_id'),
            'SUBJECT_FEDERATION_DISTRICT' => array('name' => 'district_name', 'id' => 'district_id'),
            'CITY'                        => array('name' => 'city', 'id' => 'city_id'),//город
            'VILLAGE'                     => 'city',//поселок или село
            'CITY_DISTRICT'               => 'street',//район города
            'SUBWAY_STATION'              => 'subway',
            'OTHER'                       => '',
        );
        $region_address = array();
        while ($region !== null) {
            if ($home_region_id && ((int)ifset($region['id']) == (int)$home_region_id)) {
                $home = true;
            }

            if ($field = ifset($address_map[$region['type']], false)) {
                if (is_array($field)) {
                    foreach ($field as $property => $target) {
                        $region_address[$target][] = trim($region[$property]);
                    }
                } else {
                    $region_address[$field][] = trim($region['name']);
                }
            }

            $region = empty($region['parent']) ? null : $region['parent'];
        }

        foreach ($region_address as $field => &$address_item) {
            $address_item = array_filter($address_item, 'strlen');
            if ($address_item) {
                foreach ($address_item as $type => &$address_item_element) {
                    if (isset($formats[$type])) {
                        $address_item_element = sprintf($formats[$type], $address_item_element);
                    }
                    unset($address_item_element);
                }
                $address_item = reset($address_item);

            }
            unset($address_item);
        }

        #address
        $exact_address = array();

        if (!empty($data['address'])) {
            $address_map = array(
                'country',
                'city',
                'subway'     => 'street',
                'postcode'   => 'zip',
                'street'     => 'street',
                'house'      => 'street',
                'block'      => 'street',
                'floor'      => 'street',
                //update order
                'entrance'   => 'street',
                'entryphone' => 'street',
                'apartment'  => 'street',
            );

            foreach ($address_map as $field => $target) {
                if (is_int($field)) {
                    $field = $target;
                }

                if (isset($data['address'][$field])) {
                    $address_item = trim(trim($data['address'][$field]));
                    if (strlen($address_item)) {
                        if (isset($formats[$field])) {
                            $address_item = sprintf($formats[$field], $address_item);
                        }
                        $exact_address[$target][$field] = $address_item;
                    }

                    unset($data['address'][$field]);
                    unset($address_item);
                }
            }

            foreach ($exact_address as &$address_item) {
                $address_item = implode(' ', $address_item);
                unset($address_item);
            }
        }

        $address = $region_address + $exact_address;

        if (!empty($address['country_id'])) {
            $map = include(dirname(dirname(__FILE__)).'/config/regions.php');
            if (isset($map[$address['country_id']])) {
                $country = $map[$address['country_id']];
                $address['country'] = $country['iso'];
                $regions = $country['regions'];
                $regions = array_flip($regions);
                if (!empty($address['region_id']) && isset($regions[$address['region_id']])) {
                    $address['region'] = $regions[$address['region_id']];
                } else {
                    //$address['region'] = $address['region'];
                }
                if (!empty($address['city_id'])) {
                    if (isset($regions[$address['city_id']])) {
                        $address['region_id'] = $address['city_id'];
                        $address['region'] = $regions[$address['region_id']];
                    }
                }
            }
        }
        if (isset($address['city_id'])) {
            unset($address['city_id']);
        }

        foreach ($address as $field => $item) {
            if (preg_match('@_id$@', $field)) {
                unset($address[$field]);
            }
        }

        $address = array_filter($address, 'strlen');
        $address['is_home_region'] = $home;
        array_reverse($address, true);

        if ($format) {
            class_exists('waContactAddressField');
            $formatter = new waContactAddressOneLineFormatter();
            $data = $address;

            foreach ($data as $field => $value) {
                if (preg_match('@^(\w+)_name@', $field, $matches) && !isset($data[$matches[1]])) {
                    $data[$matches[1]] = $value;
                }
                unset($value);
            }
            return $formatter->format(compact('data'));
        } else {
            return $address;
        }
    }

    public function getProfileId()
    {
        $profile_id = null;
        foreach ($this->items as $item) {
            if (!empty($item['profile_id'])) {
                $profile_id = $item['profile_id'];
                break;
            }
        }
        return $profile_id;
    }
}
