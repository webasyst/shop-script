<?php
/**
 * List of products
 */
class shopProdListAction extends waViewAction
{
    public function execute()
    {
        $collection = new shopProductsCollection('');
        $this->view->assign('products', $collection->getProducts('*,sku_count,image', 0, 10));

        $this->setLayout(new shopBackendProductsListSectionLayout());
    }
}
