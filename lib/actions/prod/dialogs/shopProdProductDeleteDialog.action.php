<?php
/**
 * Dialog to confirm product deletion
 */
class shopProdProductDeleteDialogAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::request('product_id', 0, 'int');

        $product = new shopProduct($product_id);
        if (!$product['id']) {
            throw new waException('Not found', 404);
        }

        $collection = new shopOrdersCollection('search/items.product_id='.$product_id);
        $count = $collection->count();

        $this->view->assign([
            'product' => $product,
            'orders_list_url' => $this->getOrdersListUrl($product_id),
            'orders_count' => $count,
        ]);
    }

    protected function getOrdersListUrl($product_id)
    {
        return wa('shop')->getUrl().
            '?action=orders#/orders/search/items.product_id='.$product_id.
            '&view=table/';
    }
}
