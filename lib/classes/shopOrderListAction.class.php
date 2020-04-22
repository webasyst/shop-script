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
        if ($sort[0] !== 'create_datetime') {
            $order_by['create_datetime'] = 'desc';
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

            $search = waRequest::get('search', null, waRequest::TYPE_STRING_TRIM);
            if ($search) {
                $this->hash = "search/{$search}";
                return $this->hash;
            }

            $filter_params = $this->getFilterParams();
            $hash = '';
            if ($filter_params) {
                if (count($filter_params) == 1) {
                    $k = key($filter_params);
                    $v = $filter_params[$k];
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
                    $hash = "search/{$k}{$op}{$v}";
                }
            } elseif (waRequest::get('hash')) {
                $hash = waRequest::get('hash');
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
            if ($state_id) {
                if (strstr($state_id, '|') !== false) {
                    $params['state_id'] = explode('|', $state_id);
                } else {
                    $params['state_id'] = $state_id;
                }
            }
            $contact_id = waRequest::get('contact_id', null, waRequest::TYPE_INT);
            if ($contact_id) {
                $params['contact_id'] = $contact_id;
            }
            $storefront = urldecode(waRequest::get('storefront', null, waRequest::TYPE_STRING_TRIM));
            if ($storefront) {
                $params['storefront'] = $storefront;
            }
            $sales_channel = urldecode(waRequest::get('sales_channel', null, waRequest::TYPE_STRING_TRIM));
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

        // TODO: rework to use shopCustomer::getUserpics()

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
                    $o['contact']['photo_50x50'] = waContact::getPhotoUrl($o['contact']['id'], $o['contact']['photo'], 50, 50);
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
            $sort = explode('/', $sort, 2);
            $sort_field = (string) ifset($sort[0]);
            $sort_order = (string) ifset($sort[1]);
        }

        if (!in_array($sort_field, array('create_datetime', 'updated', 'paid_date', 'shipping_datetime', 'state_id'))) {
            $sort_field = 'create_datetime';
            $sort_order = 'desc';
        }
        $sort_order = strtolower($sort_order) === 'desc' ? 'desc' : 'asc';

        $csm->set(wa()->getUser()->getId(), 'shop', 'order_list_sort', "{$sort_field}/{$sort_order}");

        return array($sort_field, $sort_order);
    }
}
