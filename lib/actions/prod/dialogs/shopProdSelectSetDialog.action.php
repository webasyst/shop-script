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

        $this->view->assign([
            'sets' => $sets,
            'sets_product' => $sets_product,
        ]);
    }
}
