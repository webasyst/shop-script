<?php

/**
 * Class shopCustomersOrdersAction
 *
 * List of orders of current customer
 *
 */
class shopCustomersOrdersAction extends waViewAction
{
    /**
     * Customer
     * @var waContact
     */
    protected $contact;

    /**
     * Limit
     * @var int
     */
    protected $limit = 50;

    /**
     * @var shopOrdersCollection
     */
    protected $collection;

    /**
     * @var array
     */
    protected $orders;


    /**
     * Main endpoint of action
     * @throws waException
     */
    public function execute()
    {
        $this->view->assign(array(
            'contact' => $this->getContact(),
            'orders' => $this->getOrders(),
            'page' => $this->getPage(),
            'pages_count' => $this->getPagesCount()
        ));
    }

    /**
     * Get offset
     * @return int
     */
    protected function getOffset()
    {
        $page = $this->getPage();
        return ($page - 1) * $this->limit;
    }

    protected function getPage()
    {
        $page = (int)$this->getParameter('page');
        if ($page < 1) {
            $page = 1;
        }
        return $page;
    }

    /**
     * Actually this method return waContact object (current customer)
     * But never throw exception
     *
     * @return waContact
     * @throws waException
     */
    protected function getContact()
    {
        if ($this->contact === null) {
            $id = (int)$this->getParameter('id');
            $id = $id > 0 ? $id : 0;
            $this->contact = new waContact($id);
            if (!$this->contact->exists()) {
                // To prevent exception in template
                $this->contact = new waContact(0);
            }
        }
        return $this->contact;
    }

    /**
     * @return shopOrdersCollection
     * @throws waException
     */
    protected function getCollection()
    {
        if ($this->collection === null) {
            $this->collection = new shopOrdersCollection('search/contact_id=' . $this->getContact()->getId());
        }
        return $this->collection;
    }

    /**
     * Obtain (load) from DB
     * @return array
     * @throws waException
     */
    protected function obtainOrders()
    {
        // For not existed contact not make any DB query
        if ($this->getContact()->getId() <= 0) {
            return array();
        }

        $offset = $this->getOffset();

        // A little bit optimization
        $total_count = $this->getCollection()->count();
        if ($offset >= $total_count) {
            return array();
        }

        $orders = $this->getCollection()->getOrders('*,items,params', $offset, $this->limit);
        $this->workupOrders($orders);

        return $orders;
    }

    /**
     * Get already obtained orders
     * @return array
     * @throws waException
     */
    protected function getOrders()
    {
        if ($this->orders === null) {
            $this->orders = $this->obtainOrders();
        }
        return $this->orders;
    }

    /**
     * @return float
     * @throws waException
     */
    protected function getPagesCount()
    {
        $count = $this->getCollection()->count();
        $pages_count = ceil((float)$count / $this->limit);
        return $pages_count;
    }

    /**
     * Workup orders for view
     * @param array &$orders
     */
    protected function workupOrders(&$orders)
    {
        shopHelper::workupOrders($orders);
        foreach ($orders as &$order) {
            $order['total_formatted'] = waCurrency::format('%{h}', $order['total'], $order['currency']);
            $order['shipping_name'] = ifset($order['params']['shipping_name'], '');
            $order['payment_name'] = ifset($order['params']['payment_name'], '');
            // !!! TODO: shipping and payment icons
        }
        unset($order);
    }

    /**
     * Get parameter either form request or constructor params
     * @param $name
     * @return null
     */
    protected function getParameter($name)
    {
        // Request params
        $request = wa()->getRequest()->request();

        if (isset($request[$name])) {
            return $request[$name];
        } elseif (isset($this->params[$name])) {
            // or constructor params
            return $this->params['id'];
        } else {
            return null;
        }
    }

}
