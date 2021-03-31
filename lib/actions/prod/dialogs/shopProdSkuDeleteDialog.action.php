<?php
/**
 * Dialog to confirm SKU deletion
 */
class shopProdSkuDeleteDialogAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::request('product_id', 0, 'int');
        $sku_id = waRequest::request('sku_id');
        $product = new shopProduct($product_id);
        if (!$product['id']) {
            throw new waException('Not found', 404);
        }

        if (is_array($sku_id)) {
            $sku_class = 'sku'; // whole group of modifications
            $sku_ids = $sku_id;
        } else {
            $sku_class = 'modification'; // one modification of a sku
            $sku_ids = [$sku_id];
        }

        $skus = [];
        foreach ($sku_ids as $sku_id) {
            $sku = ifset($product, 'skus', $sku_id, null);
            if ($sku) {
                $skus[$sku['id']] = $sku;
            }
        }

        if (!$skus) {
            throw new waException('Not found', 404);
        }

        $collection = new shopOrdersCollection('search/items.sku_id='.implode('||', array_keys($skus)));
        $count = $collection->count();

        $backend_prod_dialog_event = $this->throwEvent($product, $sku_id);

        $this->view->assign([
            'skus' => $skus,
            'product' => $product,
            'sku_class' => $sku_class,
            'sku_name' => $this->formatSkuName($product, $skus),
            'orders_list_url' => $this->getOrdersListUrl($product_id, $skus),
            'orders_count' => $count,
            'backend_prod_dialog_event' => $backend_prod_dialog_event,
        ]);
    }

    protected function getOrdersListUrl($product_id, $skus)
    {
        return wa('shop')->getUrl().
            '?action=orders#/orders/search/items.sku_id='.
            implode('||', array_keys($skus)).
            '&view=table/';
    }

    protected function formatSkuName($product, $skus)
    {
        $sku = reset($skus);
        if (!empty($sku['name'])) {
            return $sku['name'];//.' ('.$sku['id'].')';
        }
        if (!empty($sku['sku'])) {
            return $sku['sku'];//.' ('.$sku['id'].')';
        }
        return $sku['id'];
    }

    /**
     * Throw 'backend_prod_dialog' event
     * @param shopProduct $product
     * @param int $sku_id
     * @return array
     * @throws waException
     */
    protected function throwEvent($product, $sku_id)
    {
        /**
         * @event backend_prod_dialog
         * @since 8.18.0
         *
         * @param int $sku_id
         * @param shopProduct $product
         * @param string $dialog_id
         *       Which dialog is shown
         */
        $params = [
            'sku_id' => $sku_id,
            'product' => $product,
            'dialog_id' => 'sku_delete',
        ];
        return wa('shop')->event('backend_prod_dialog', $params);
    }
}
