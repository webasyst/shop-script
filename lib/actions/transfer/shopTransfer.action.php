<?php

class shopTransferAction extends waViewAction
{
    public function execute()
    {
        $smarty_variables = array(
            'from'      => (int)$this->getRequest()->request('from'),
            'to'        => (int)$this->getRequest()->request('to'),
            'skus'      => $this->getSkus(),
            'stocks'    => $this->getStocks(),
            'string_id' => $this->getStringId()
        );

        /**
         * Create new transfer
         *
         * @param int $from From Stock
         * @param int $to To Stock
         * @param array $skus
         * @param array $stocks
         * @param int $string_id Transfer id
         *
         * @event backend_stocks.transfer
         */
        $params = $smarty_variables;

        $backend_stocks_hook = wa('shop')->event('backend_stocks.transfer', $params);
        $this->view->assign('backend_stocks_hook', $backend_stocks_hook);

        $this->view->assign($smarty_variables);
    }

    public function getStocks()
    {
        $m = new shopStockModel();
        return $m->getAll('id');
    }

    public function getSkus()
    {
        $ids = array_map('intval', (array)$this->getRequest()->request('sku_id'));
        if (!$ids) {
            return array();
        }
        $sql = "
            SELECT s.id, s.name, s.count, p.id AS product_id, p.name AS product_name, p.image_id FROM `shop_product` p 
            JOIN `shop_product_skus` s ON p.id = s.product_id
            WHERE s.id IN (i:0)
        ";
        $skus = $this->getWaModel()->query($sql, array($ids))->fetchAll();
        $skus = shopTransferSkusController::workupSkus($skus);
        return $skus;
    }

    public function getStringId()
    {
        $m = new shopTransferModel();
        return $m->generateStringId();
    }

    public function getWaModel()
    {
        return new waModel();
    }
} 