<?php

class shopOrderListAction extends waViewAction
{
    /**
     * @var shopOrderModel
     */
    protected $model;

    /**
     * Params by which list is filtered
     * array|null
     */
    protected $filter_params;

    /**
     * Hash for collection related with filter-params
     * @var string
     */
    protected $hash;

    protected $orders;

    protected $updated_orders;

    /**
     * @var shopOrdersCollection
     */
    protected $collection;

    public function __construct($params = null)
    {
        parent::__construct($params);
        $this->model = new shopOrderModel();
        $this->collection = new shopOrdersCollection($this->getHash());
        $sort = $this->getSort();
        $order_by = array($sort[0] => $sort[1]);
        if ($sort[0] !== 'id') {
            if ($sort[0] !== 'create_datetime') {
                $order_by['create_datetime'] = 'desc';
            }
            $order_by['id'] = 'desc';
        }
        $this->collection->orderBy($order_by);
    }

    public function getOrders($offset, $limit)
    {
        if ($this->orders === null) {
            $this->orders = $this->collection->getOrders("*,products,contact,params,courier", $offset, $limit);
            self::extendContacts($this->orders);
            shopHelper::workupOrders($this->orders);
        }
        return $this->orders;
    }

    public function getUpdatedOrders()
    {
        if ($this->updated_orders === null) {
            $search = waRequest::get('search', '');
            if ($search) {
                $search = preg_replace('/([<>]=?)([^=]+)/', '$1"$2"', $search);
                $this->collection->addWhere($search);
                $this->updated_orders = $this->collection->getOrders("*,products,contact,params,courier");
                self::extendContacts($this->updated_orders);
                shopHelper::workupOrders($this->updated_orders);
            }
        }
        return $this->updated_orders;
    }

    public function getTotalCount()
    {
        //return $this->model->countByField($this->getFilterParams());
        return $this->collection->count();
    }

    /**
     * Get distinct field values of order list
     * Be sure field_id input not involve any injection - check $field_id from user OR use static predefined const
     *
     * @param string $field_id
     * @return array
     */
    public function getDistinctOrderFieldValues($field_id)
    {
        return $this->collection->getDistinctFieldValues($field_id);
    }

    public function getListView()
    {
        $default_view = $this->getConfig()->getOption('orders_default_view');
        return waRequest::get('view', $default_view, waRequest::TYPE_STRING_TRIM);
    }

    public function getCount()
    {
        $orders_per_page = $this->getConfig()->getOption('orders_per_page');
        $view = $this->getListView();
        if (is_array($orders_per_page)) {
            if (isset($orders_per_page[$view])) {
                $count = $orders_per_page[$view];
            } else {
                $count = reset($orders_per_page);
            }
        } else {
            $count = $orders_per_page;
        }
        return $count;
    }

    public function getHash()
    {
        if ($this->hash === null) {

            $hash = '';
            $filter_conditions = [];
            $filter_params = $this->getFilterParams();
            if ($filter_params) {
                $updated_orders_param = '';
                if (!empty($filter_params['viewpos'])) {
                    $filter_conditions[] = "params.sales_channel=pos:";
                    $ts = strtotime($filter_params['viewpos']);
                    if ($ts) {
                        $filter_conditions[] = "create_datetime>=".date('Y-m-d 00:00:00', $ts);
                        $filter_conditions[] = "create_datetime<=".date('Y-m-d 23:59:59', $ts);
                    }
                }
                unset($filter_params['viewpos']);
                if (!empty($filter_params['updated_orders'])) {
                    $updated_orders_param = $filter_params['updated_orders'];
                    unset($filter_params['updated_orders']);
                }

                foreach($filter_params as $k => $v) {
                    if (is_array($v)) {
                        $v = implode("||", $v);
                    }
                    $op = '=';
                    if ($k == 'storefront') {
                        $k = 'params.'.$k;
                        if (strlen($v) && $v !== 'NULL') {
                            $v = rtrim($v, '/*');
                            $v .= "||$v/";
                        }
                    } elseif ($k == 'sales_channel') {
                        $k = 'params.'.$k;
                    } elseif ($k == 'item_code') {
                        $k = 'item_code.any';
                    } elseif ($k == 'product_id') {
                        $k = 'items.'.$k;
                    } elseif ($k == 'city' || $k == 'country' || $k == 'region') {
                        if ($k == 'city') {
                            $op = '*=';
                        }
                        $k = 'address.'.$k;
                    } elseif (!$this->model->fieldExists($k)) {
                        $k = 'params.'.$k;
                    }
                    $filter_conditions[] = "{$k}{$op}{$v}";
                }

                if ($updated_orders_param) {
                    $filter_conditions[] = $updated_orders_param;
                }
            }

            if (waRequest::get('hash')) {
                $hash = waRequest::get('hash');
                if ($filter_conditions && substr($hash, 0, 7) == 'search/') {
                    $hash .= '&'.join('&', $filter_conditions);
                }
            } else if ($filter_conditions) {
                $hash = 'search/'.join('&', $filter_conditions);
            }

            $this->hash = $hash;
        }
        return $this->hash;
    }

    public function getFilterParams($str = false)
    {
        if ($this->filter_params === null) {
            $params = array();
            $state_id = waRequest::get('state_id');
            if ($state_id && wa()->getUser()->getRights('shop', 'orders') != shopRightConfig::RIGHT_ORDERS_COURIER) {
                if (strstr($state_id, '|') !== false) {
                    $params['state_id'] = explode('|', $state_id);
                } else {
                    $params['state_id'] = $state_id;
                }
            }

            $search = urldecode(waRequest::get('search', '', waRequest::TYPE_STRING_TRIM));
            if ($search) {
               $params['updated_orders'] = $search;
            }
            $contact_id = waRequest::get('contact_id', null, waRequest::TYPE_INT);
            if ($contact_id) {
                $params['contact_id'] = $contact_id;
            }
            $courier_contact_id = waRequest::get('courier_contact_id', null, waRequest::TYPE_INT);
            if ($courier_contact_id) {
                $params['courier_contact_id'] = $courier_contact_id;
            }
            $storefront = urldecode(waRequest::get('storefront', '', waRequest::TYPE_STRING_TRIM));
            if ($storefront) {
                $params['storefront'] = $storefront;
            }
            $sales_channel = urldecode(waRequest::get('sales_channel', '', waRequest::TYPE_STRING_TRIM));
            if ($sales_channel) {
                $params['sales_channel'] = $sales_channel;
            }
            $product_id = waRequest::get('product_id', null, waRequest::TYPE_INT);
            if ($product_id) {
                $params['product_id'] = $product_id;
            }
            $shipping_id = waRequest::get('shipping_id', null, waRequest::TYPE_INT);
            if ($shipping_id) {
                $params['shipping_id'] = $shipping_id;
            }
            $payment_id = waRequest::get('payment_id', null, waRequest::TYPE_INT);
            if ($payment_id) {
                $params['payment_id'] = $payment_id;
            }
            $coupon_id = waRequest::get('coupon_id', null, waRequest::TYPE_INT);
            if ($coupon_id) {
                $params['coupon_id'] = $coupon_id;
            }
            $tracking_number = waRequest::get('tracking_number', null, waRequest::TYPE_STRING_TRIM);
            if ($tracking_number) {
                $params['tracking_number'] = $tracking_number;
            }
            $city = waRequest::get('city', null, waRequest::TYPE_STRING_TRIM);
            if ($city) {
                $params['city'] = $city;
            }
            $region = waRequest::get('region', null, waRequest::TYPE_STRING_TRIM);
            if ($region) {
                $params['region'] = $region;
            }
            $country = waRequest::get('country', null, waRequest::TYPE_STRING_TRIM);
            if ($country) {
                $params['country'] = $country;
            }
            $item_code = waRequest::get('item_code', null, waRequest::TYPE_STRING_TRIM);
            if ($item_code) {
                $params['item_code'] = $item_code;
            }
            $viewpos = waRequest::get('viewpos', null, waRequest::TYPE_STRING);
            if ($viewpos) {
                if (wa_is_int($viewpos)) {
                    $params['viewpos'] = date('Y-m-d');
                } else {
                    $params['viewpos'] = date('Y-m-d', strtotime($viewpos));
                }
            }
            $unsettled = waRequest::get('unsettled', null, waRequest::TYPE_INT);
            if ($unsettled) {
                $params['unsettled'] = $unsettled;
            }
            $this->filter_params = $params;
        }
        if (!$str) {
            return $this->filter_params;
        }

        if (!$this->filter_params && $this->hash) {
            return 'hash='.urlencode($this->hash);
        }

        $params_str = '';
        foreach ($this->filter_params as $p => $v) {
            $params_str .= '&'.$p.'='. (is_array($v) ? implode('|', $v) : $v);
        }
        return substr($params_str, 1);
    }

    public static function extendContacts(&$orders)
    {
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        $use_gravatar = $config->getGeneralSettings('use_gravatar');
        $gravatar_default = $config->getGeneralSettings('gravatar_default');

        $emails = array();

        foreach ($orders as &$o) {
            if (isset($o['contact'])) {
                if (!$o['contact']['photo'] && $use_gravatar) {

                    if (!isset($emails[$o['contact']['id']])) {
                        $c = new waContact($o['contact']['id']);
                        $emails[$o['contact']['id']] = $c->get('email', 'default');
                    }

                    $email = $emails[$o['contact']['id']];
                    $o['contact']['photo_50x50'] = shopHelper::getGravatarPic($email, array(
                        'size' => 50,
                        'default' => $gravatar_default,
                        'is_company' => !empty($o['contact']['is_company'])
                    ));
                } else {
                    $type = !empty($o['contact']['is_company']) ? 'company' : 'person';
                    $o['contact']['photo_50x50'] = waContact::getPhotoUrl($o['contact']['id'], $o['contact']['photo'], 50, 50, $type);
                }
            } else { // contact deleted
                $o['contact']['name'] = isset($o['params']['contact_name']) ? $o['params']['contact_name'] : '';
                $o['contact']['name'] = htmlspecialchars($o['contact']['name']);
                $o['contact']['email'] = isset($o['params']['contact_email']) ? $o['params']['contact_email'] : '';
                $o['contact']['phone'] = isset($o['params']['contact_phone']) ? $o['params']['contact_phone'] : '';
                if ($use_gravatar) {
                    $o['contact']['photo_50x50'] = shopHelper::getGravatarPic($o['contact']['email'], array(
                        'size' => 50,
                        'default' => $gravatar_default,
                        'is_company' => !empty($o['contact']['is_company'])
                    ));
                } else {
                    $o['contact']['photo_50x50'] = waContact::getPhotoUrl(null, null, 50, 50);
                }
            }
        }
        unset($o);
    }

    public function assign($data)
    {
        $this->view->assign($data);
    }

    public function getSort()
    {
        $sort = (array) wa()->getRequest()->request('sort');
        $sort_field = (string) ifset($sort[0]);
        $sort_order = (string) ifset($sort[1]);

        $csm = new waContactSettingsModel();

        if (!$sort_field) {
            $sort = $csm->getOne(wa()->getUser()->getId(), 'shop', 'order_list_sort');
            $sort = explode('/', (string) $sort, 2);
            $sort_field = (string) ifset($sort[0]);
            $sort_order = (string) ifset($sort[1]);
        }

        if (!in_array($sort_field, array('create_datetime', 'updated', 'paid_date', 'shipping_datetime', 'state_id', 'paid_datetime'))) {
            $sort_field = 'create_datetime';
            $sort_order = 'desc';
        }
        $sort_order = strtolower($sort_order) === 'desc' ? 'desc' : 'asc';

        $csm->set(wa()->getUser()->getId(), 'shop', 'order_list_sort', "{$sort_field}/{$sort_order}");

        return array($sort_field, $sort_order);
    }

    public function getStateId()
    {
        $hash = $this->collection->getHash();
        if (is_array($hash)) {
            foreach ($hash as $hash_param) {
                if (mb_strpos($hash_param, 'state_id') !== false) {
                    $parse_conditions = shopOrdersCollection::parseConditions($hash_param);
                    if (isset($parse_conditions['state_id']) && isset($parse_conditions['state_id'][1])) {
                        return explode('||', $parse_conditions['state_id'][1]);
                    }
                }
            }
        }

        return null;
    }
}
