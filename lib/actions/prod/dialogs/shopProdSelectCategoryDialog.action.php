<?php
/**
 * Dialog shown from General tab to select categories for a product.
 */
class shopProdSelectCategoryDialogAction extends waViewAction
{
    public function execute()
    {
        // Not actually used...
        $product_id = waRequest::request('product_id', 0, 'int');

        $category_model = new shopCategoryModel();
        $categories = $category_model->getFullTree('id, name, parent_id', true);
        $categories_tree = $category_model->buildNestedTree($categories);

        $this->view->assign([
            'categories' => $categories,
            'categories_tree' => $categories_tree,
        ]);
    }
}
