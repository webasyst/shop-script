<?php

class shopStocksLogListAction extends waViewAction
{
    protected $product_id = null;

    public function execute()
    {
        // filter by stock_id
        $stock_id = waRequest::get('stock_id', null, waRequest::TYPE_INT);

        // order
        $order = waRequest::get('order', null, waRequest::TYPE_STRING_TRIM);
        if ($order != 'asc' && $order != 'desc') {
            $order = 'desc';
        }

        // build where filter
        $where = array();
        if ($this->product_id) {
            $where['product_id'] = $this->product_id;
        }
        if ($stock_id) {
            $where['stock_id'] = $stock_id;
        }

        // offset
        $offset = waRequest::get('offset', 0, waRequest::TYPE_INT);

        // limit
        $total_count = $this->getTotalCount($where);

        // chunk size
        $count = $this->getConfig()->getOption('stocks_log_items_per_page');

        // get list of log items
        $log = $this->getList(array(
            'offset' => $offset,
            'limit' => $count,
            'where' => $where,
            'order' => 'datetime '.$order.',id '.$order
        ));
        $this->workupList($log);


        // get all stocks
        $stock_model = new shopStockModel();
        $stocks = $stock_model->getAll('id');

        $this->view->assign(array(
            'log' => $log,
            'stocks' => $stocks,
            'product_id' => $this->product_id,
            'stock_id' => $stock_id,
            'order' => $order,
            'reverse_order' => $order === 'desc' ? 'asc' : 'desc',
            'offset' => $offset,
            'total_count' => $total_count,
            'count' => count($log),
            'lazy' => waRequest::get('lazy', false)
        ));
    }

    protected function getList($options)
    {
        $pslm = new shopProductStocksLogModel();
        return $pslm->getList('*,stock_name,sku_name,sku_count', $options);
    }

    protected function workupList(&$list)
    {
        $stock_model = new shopStockModel();
        $stocks = $stock_model->getAll('id');
        foreach ($list as &$item) {
            $item['sku_count_show'] = !$item['stock_id'] || !isset($stocks[$item['stock_id']]);
        }
        unset($item);
    }


    protected function getTotalCount($options)
    {
        $total_count = waRequest::get('total_count', null, waRequest::TYPE_INT);
        if (!$total_count) {
            $pslm = new shopProductStocksLogModel();
            $total_count = $pslm->countByField($options);
        }
        return $total_count;
    }

}