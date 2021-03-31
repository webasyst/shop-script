<?php
/**
 * Dialog shown from General tab to select sets for a product.
 */
class shopProdSelectSetDialogAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::request('product_id', 0, 'int');

        $set_model = new shopSetModel();
        $sets = $set_model->getByField('type', 0, 'id');

        $set_products_model = new shopSetProductsModel();
        $sets_product = $set_products_model->getByProduct($product_id);

        $backend_prod_dialog_event = $this->throwEvent($product_id);

        $this->view->assign([
            'sets' => $sets,
            'sets_product' => $sets_product,
            'backend_prod_dialog_event' => $backend_prod_dialog_event,
        ]);
    }

    /**
     * Throw 'backend_prod_dialog' event
     * @param int $id
     *      Product ID
     * @return array
     * @throws waException
     */
    protected function throwEvent($id)
    {
        /**
         * @event select_set
         * @since 8.18.0
         *
         * @param shopProduct $product
         * @param string $dialog_id
         *       Which dialog is shown
         */
        $params = [
            'product' => new shopProduct($id),
            'dialog_id' => 'select_set',
        ];
        return wa('shop')->event('backend_prod_dialog', $params);
    }
}
