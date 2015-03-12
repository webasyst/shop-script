<?php

class shopStocksLogAction extends shopStocksLogListAction
{
    public function execute()
    {
        parent::execute();
    }

    protected function getList($options)
    {
        $pslm = new shopProductStocksLogModel();
        return $pslm->getList('*,stock_name,sku_name,sku_count,product_name', $options);
    }

}