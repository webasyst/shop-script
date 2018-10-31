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
        $skus = $this->getSkus(array_keys($items));

        foreach ($skus as &$sku) {
            $item = $items[$sku['id']];
            $sku['transfer'] = $item;
        }
        unset($sku);
        $transfer['skus'] = $skus;

        return $transfer;
    }

    public function getSkus($ids)
    {
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