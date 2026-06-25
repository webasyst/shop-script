<?php

class shopTransferInfoAction extends waViewAction
{
    public function execute()
    {
        $transfer = $this->getTransfer();

        /**
         * Show Transfer info
         *
         * @param array $transfer Extend transfer info
         *
         * @event backend_stocks.transfer_info
         */
        $params = array(
            'transfer' => $transfer,
        );

        $backend_stocks_hook = wa('shop')->event('backend_stocks.transfer_info', $params);
        $this->view->assign('backend_stocks_hook', $backend_stocks_hook);


        $this->view->assign(array(
            'transfer'       => $transfer,
            'printable_docs' => $this->getTransferPrintForms($transfer)
        ));
    }

    public function getTransfer()
    {
        $id = $this->getRequest()->get('id');
        $transfer = $this->getTransferModel()->getById($id);
        $transfers = array($transfer['id'] => $transfer);
        $transfers = shopTransferListAction::workupList($transfers);
        $transfer = $transfers[$transfer['id']];

        $items = $this->getTransferProductsModel()->getByTransfer($transfer['id']);
        $skus = $this->getSkus(array_keys($items), ifset($transfer, 'stock_id_from', null));

        foreach ($skus as &$sku) {
            $item = $items[$sku['id']];
            $sku['price'] = $item['price'];
            $sku['count'] = shopFrac::discardZeros($sku['count']);
            $item['count'] = shopFrac::discardZeros($item['count']);
            $sku['transfer'] = $item;
        }
        unset($sku);
        $transfer['skus'] = $skus;

        return $transfer;
    }

    public function getSkus(array $ids, $stock_id_from = null)
    {
        if (!$ids) {
            return array();
        }
        $sql = "
            SELECT s.id, s.name, s.count, s.sku AS sku_code, s.image_id AS sku_image_id, p.id AS product_id, p.name AS product_name, p.image_id FROM `shop_product` p
            JOIN `shop_product_skus` s ON p.id = s.product_id
            WHERE s.id IN (i:0)
        ";
        $skus = $this->getWaModel()->query($sql, array($ids))->fetchAll();
        $skus = shopTransferSkusController::workupSkus($skus);

        // get stock count
        if ($stock_id_from && $skus) {
            $sku_ids = array_map(function ($sku) {
                return $sku['id'];
            }, $skus);

            $sku_ids = implode(', ', $sku_ids);

            $stocksModel = new shopProductStocksModel();
            $query = $stocksModel->select('sku_id, count');
            $query->where('stock_id = ? AND sku_id IN (?)', [$stock_id_from, $sku_ids]);
            $skus_with_stock_count = $query->fetchAll('sku_id');

            foreach ($skus as &$sku) {
                if (isset($skus_with_stock_count[$sku['id']])) {
                    $sku['stock_from_count'] = $skus_with_stock_count[$sku['id']]['count'];
                    $sku['stock_from_count_html'] = shopHelper::getStockCountIcon($sku['stock_from_count'], $stock_id_from, true);
                }
            }
            unset($sku);
        }

        return $skus;
    }

    public function getTransferModel()
    {
        return new shopTransferModel();
    }

    public function getTransferProductsModel()
    {
        return new shopTransferProductsModel();
    }

    public function getWaModel()
    {
        return new waModel();
    }

    public function getTransferPrintForms($transfer)
    {
        $plugins = array();
        foreach (wa('shop')->getConfig()->getPlugins() as $id => $plugin) {
            $printform = ifset($plugin['printform']);
            if ($printform !== 'transfer') {
                continue;
            }
            $plugin['url'] = "?plugin={$id}&module=printform&action=display&transfer_id={$transfer['id']}";
            $plugins[$id] = $plugin;
        }
        return $plugins;
    }
}
