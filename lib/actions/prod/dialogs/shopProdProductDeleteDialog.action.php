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

        $backend_prod_dialog_event = $this->throwEvent($product);

        $this->view->assign([
            'product' => $product,
            'orders_list_url' => $this->getOrdersListUrl($product_id),
            'orders_count' => $count,
            'backend_prod_dialog_event' => $backend_prod_dialog_event,
        ]);
    }

    protected function getOrdersListUrl($product_id)
    {
        return wa('shop')->getUrl().
            '?action=orders#/orders/search/items.product_id='.$product_id.
            '&view=table/';
    }

    /**
     * Throw 'backend_prod_dialog' event
     * @param shopProduct $product
     * @return array
     * @throws waException
     */
    protected function throwEvent($product)
    {
        /**
         * @event backend_prod_dialog
         * @since 8.18.0
         *
         * @param shopProduct $product
         * @param string $dialog_id
         *       Which dialog is shown
         */
        $params = [
            'product' => $product,
            'dialog_id' => 'product_delete',
        ];
        return wa('shop')->event('backend_prod_dialog', $params);
    }
}
