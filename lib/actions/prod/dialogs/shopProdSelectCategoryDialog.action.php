<?php
/**
 * Dialog shown from General tab to select categories for a product.
 */
class shopProdSelectCategoryDialogAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::request('product_id', 0, 'int');

        $category_model = new shopCategoryModel();
        $categories = $category_model->getFullTree('id, name, parent_id', true);
        $categories_tree = $category_model->buildNestedTree($categories);

        $backend_prod_dialog_event = $this->throwEvent($product_id);

        $this->view->assign([
            'categories' => $categories,
            'categories_tree' => $categories_tree,
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
         * @event backend_prod_dialog
         * @since 8.18.0
         *
         * @param shopProduct $product
         * @param string $dialog_id
         *       Which dialog is shown
         */
        $params = [
            'product' => new shopProduct($id),
            'dialog_id' => 'select_category',
        ];
        return wa('shop')->event('backend_prod_dialog', $params);
    }
}
