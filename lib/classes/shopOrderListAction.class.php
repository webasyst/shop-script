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

    protected $orders;

    public function __construct($params=null) {
        parent::__construct($params);
        $this->model = new shopOrderModel();
    }

    public function getOrders($offset, $limit)
    {
        if ($this->orders === null) {
            $this->orders =
                $this->model->getList("*,items.name,items.type,items.quantity,contact,params", array(
                    'offset' => $offset,
                    'limit' => $limit,
                    'where' => $this->getFilterParams())
                );
            shopHelper::workupOrders($this->orders);
        }
        return $this->orders;
    }

    public function getTotalCount()
    {
        return $this->model->countByField($this->getFilterParams());
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

    public function assign($data)
    {
        $this->view->assign($data);
    }
}