<?php

class shopTransferListAction extends waViewAction
{
    protected $count;

    public function execute()
    {
        $offset = $this->getOffset();
        $limit = $this->getLimit();
        $order = $this->getOrder();
        $sort = $this->getSort();
        $filter = $this->getFilter();

        $transfers = $this->getTransferModel()->getList(
            array(
                'fields' => '*',
                'offset' => $offset,
                'limit'  => $limit,
                'order'  => $sort.' '.$order,
                'filter' => $filter
            )
        );

        $transfers = self::workupList($transfers);

        $count = count($transfers);
        $this->count = $count + $offset;

        /**
         * Show transfer list
         *
         * @param array $transfers
         * @param string $order
         * @param string $sort
         * @param int $already_loaded_count
         *
         * @event backend_stocks.transfer_list
         */
        $params = array(
            'transfers'            => $transfers,
            'sort'                 => $sort,
            'order'                => $order,
            'already_loaded_count' => $this->count,
        );

        $backend_stocks_hook = wa('shop')->event('backend_stocks.transfer_list', $params);
        $this->view->assign('backend_stocks_hook', $backend_stocks_hook);

        $this->view->assign(array(
            'transfers'            => $transfers,
            'disabled_lazyload'    => $this->disabledLazyLoad(),
            'sort'                 => $sort,
            'order'                => $order,
            'offset'               => $offset,
            'limit'                => $limit,
            'already_loaded_count' => $this->count,
            'now_loaded_count'     => $count,
            'total_count'          => $this->getTotalCount(),
            'disabled_sort'        => $this->disabledSort()
        ));
    }

    public function count()
    {
        if ($this->count === null) {
            $offset = $this->getOffset();
            $count = $this->getTransferModel()->getListCount(array('filter' => $this->getFilter()));
            $this->count = $offset + $count;
        }
        return $this->count;
    }

    public function getTransferModel()
    {
        return new shopTransferModel();
    }

    public function disabledLazyLoad()
    {
        return $this->getParameter('disabled_lazyload');
    }

    public function getOffset()
    {
        return (int)$this->getParameter('offset');
    }

    public function getFilter()
    {
        return $this->getParameter('filter', '');
    }

    public function getTotalCount()
    {
        $total_count = $this->getRequest()->get('total_count');
        if ($total_count === null) {
            $total_count = $this->getTransferModel()->getListCount();
        }
        return (int)$total_count;
    }

    public function getDefaultLimit()
    {
        return 50;
    }

    public function getLimit()
    {
        return (int)$this->getParameter('limit', $this->getDefaultLimit());
    }

    public function getOrder()
    {
        $order = $this->getParameter('order');
        $order = strtolower($order);
        return $order === 'desc' ? 'desc' : '';
    }

    public function disabledSort()
    {
        return (int)$this->getParameter('disabled_sort');
    }

    public function getSort()
    {
        return $this->getParameter('sort', '');
    }

    public static function workupList($transfers)
    {
        $stock_ids = array();
        foreach ($transfers as $transfer) {
            $stock_ids[] = $transfer['stock_id_from'];
            $stock_ids[] = $transfer['stock_id_to'];
        }
        $stock_ids = array_unique($stock_ids);

        $sm = new shopStockModel();

        $empty_stock = $sm->getEmptyRow();

        $stocks = $sm->getById($stock_ids);
        foreach ($stock_ids as $stock_id) {
            if (!isset($stocks[$stock_id])) {
                $stocks[$stock_id] = $empty_stock;
                $stocks[$stock_id]['id'] = $stock_id;
                $stocks[$stock_id]['name'] = $stock_id;
            }
        }
        foreach ($transfers as &$transfer) {
            $transfer['stock_from'] = $stocks[$transfer['stock_id_from']];
            $transfer['stock_to'] = $stocks[$transfer['stock_id_to']];
        }
        unset($transfer);

        return $transfers;
    }

    private function getParameter($name, $default = null)
    {
        if (isset($this->params[$name])) {
            return $this->params[$name];
        } else {
            return $this->getRequest()->request($name, $default);
        }
    }
}