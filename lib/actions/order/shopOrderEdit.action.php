<?php

class shopOrderEditAction extends waViewAction
{
    private $crop_size = null;
    /**
     * @var shopOrderModel
     */
    private $order_model;

    public function __construct($params = null) {
        $this->order_model = new shopOrderModel();
        return parent::__construct($params);
    }

    public function execute()
    {
        $order_id = waRequest::get('id', null, waRequest::TYPE_INT);

        $order    = array();
        $form     = !$order_id ? shopHelper::getCustomerForm() : null;
        $currency = $this->getConfig()->getCurrency();

        if ($order_id) {
            $order = $this->getOrder($order_id);

            if ($order['contact_id']) {
                if (shopHelper::getContactRights($order['contact_id'])) {
                    $form = shopHelper::getCustomerForm($order['contact_id']);
                }
            }
            $currency = $order['currency'];
        }

        $stock_model = new shopStockModel();
        $stocks = $stock_model->getAll('id');

        $tax_model = new shopTaxModel();
        $taxes_count = $tax_model->countAll();


        $this->view->assign(array(
            'order'    => $order,
            'stocks'   => $stocks,
            'form'     => $form,
            'currency' => $currency,
            'taxes_count' => $taxes_count
        ));
    }

    private function getOrder($order_id)
    {
        $order = $this->order_model->getOrder($order_id, true, true);
        if (!$order) {
            throw new waException("Unknow order", 404);
        }
        $order['shipping_id'] = ifset($order['params']['shipping_id'], '').'.'.ifset($order['params']['shipping_rate_id'], '');

        $sku_ids = array();
        foreach ($order['items'] as $item) {
            foreach ($item['skus'] as $sku) {
                if (empty($sku['fake'])) {
                    $sku_ids[] = $sku['id'];
                }
            }
        }
        $sku_stocks = $this->getSkuStocks(array_unique($sku_ids));

        foreach ($order['items'] as &$item) {
            $this->workupItems($item, $sku_stocks);
        }
        return $order;
    }

    private function getSkuStocks($sku_ids)
    {
        if (!$sku_ids) {
            return array();
        }
        $product_stocks_model = new shopProductStocksModel();
        return $product_stocks_model->getBySkuId($sku_ids);
    }

    private function getCropSize()
    {
        if ($this->crop_size === null) {
            $this->crop_size = $this->getConfig()->getImageSize('crop_small');
        }
        return $this->crop_size;
    }

    private function workupItems(&$item, $sku_stocks)
    {
        $size = $this->getCropSize();
        if (empty($item['image_id'])) {
            $item['url_crop_small'] = null;
        } else {
            $item['url_crop_small'] = shopImage::getUrl(
                array(
                    'id' => $item['image_id'],
                    'product_id' => $item['id'],
                    'ext' => $item['ext']
                ),
                $size
            );
        }
        if (empty($item['fake'])) {
            $item['icon'] = shopHelper::getStockCountIcon($item['count'], null, true);
        }
        foreach ($item['skus'] as &$sku) {
            if (empty($sku['fake'])) {

                if (empty($sku_stocks[$sku['id']])) {
                    $sku['icon'] = shopHelper::getStockCountIcon($sku['count'], null, true);
                } else {
                    $icons = array();
                    foreach ($sku_stocks[$sku['id']] as $stock_id => $stock) {
                        $icon  = &$icons[];
                        $icon  = shopHelper::getStockCountIcon($stock['count'], $stock_id)." ";
                        $icon .= $stock['count']." ";
                        $icon .= "@".htmlspecialchars($stock['name']);
                        unset($icon);
                    }
                    $sku['icon'] = implode(', ', $icons);
                }
            }
        }
        unset($sku);
    }
}

