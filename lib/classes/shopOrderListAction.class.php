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

    public function __construct($params=null) {
        parent::__construct($params);
        $this->model = new shopOrderModel();
        $this->collection = new shopOrdersCollection($this->getHash());
    }

    public function getOrders($offset, $limit)
    {
        if ($this->orders === null) {
            $this->orders = $this->collection->getOrders("*,items,contact,params", $offset, $limit);
            $this->extendContacts($this->orders);
            shopHelper::workupOrders($this->orders);
        }
        return $this->orders;
    }

    public function getTotalCount()
    {
        //return $this->model->countByField($this->getFilterParams());
        return $this->collection->count();
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
            $filter_params = $this->getFilterParams();
            $hash = '';
            if ($filter_params) {
                if (count($filter_params) == 1) {
                    $k = key($filter_params);
                    $v = $filter_params[$k];
                    if (is_array($v)) {
                        $v = implode("||", $v);
                    }
                    if ($k == 'storefront') {
                        $k = 'params.'.$k;
                        if (substr($v, -1) == '*') {
                            $v = substr($v, 0, -1);
                        }
                        if (substr($v, -1) == '/') {
                            $v = substr($v, 0, -1);
                        }
                        $v .= "||$v/";
                    }
                    if ($k == 'product_id') {
                        $k = 'items.'.$k;
                    }
                    $hash = "search/{$k}={$v}";
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
            $product_id = waRequest::get('product_id', null, waRequest::TYPE_INT);
            if ($product_id) {
                $params['product_id'] = $product_id;
            }
            $this->filter_params = $params;
        }
        if (!$str) {
            return $this->filter_params;
        }
        $params_str = '';
        foreach ($this->filter_params as $p => $v) {
            $params_str .= '&'.$p.'='. (is_array($v) ? implode('|', $v) : $v);
        }
        return substr($params_str, 1);
    }
    
    protected function extendContacts(&$orders)
    {
        $config = $this->getConfig();
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
                    $email =$emails[$o['contact']['id']];
                    $o['contact']['photo_50x50'] = shopHelper::getGravatar($email, 50, $gravatar_default);
                } else {
                    $o['contact']['photo_50x50'] = waContact::getPhotoUrl($o['contact']['id'], $o['contact']['photo'], 50, 50);
                }
            } else { // contact deleted
                $o['contact']['name'] = isset($o['params']['contact_name']) ? $o['params']['contact_name'] : '';
                $o['contact']['name'] = htmlspecialchars($o['contact']['name']);
                $o['contact']['email'] = isset($o['params']['contact_email']) ? $o['params']['contact_email'] : '';
                $o['contact']['phone'] = isset($o['params']['contact_phone']) ? $o['params']['contact_phone'] : '';
                if ($use_gravatar) {
                    $o['contact']['photo_50x50'] = shopHelper::getGravatar($o['contact']['email'], 50, $gravatar_default);
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
}