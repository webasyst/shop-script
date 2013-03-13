<?php

class shopProductsAddToCategoriesController extends waJsonController
{
    /**
     * @var shopCategoryModel
     */
    private $category_model;
    /**
     * @var shopCategoryProductsModel
     */
    private $category_products_model;

    /**
     * @var shopProductModel
     */
    private $product_model;

    /**
     * @var shopSetProductsModel
     */
    private $set_products_model;

    public function __construct()
    {
        $this->category_model = new shopCategoryModel();
        $this->category_products_model = new shopCategoryProductsModel();
    }

    public function execute()
    {
        $category_ids = waRequest::post('category_id', array(), waRequest::TYPE_ARRAY_INT);
        if (!$category_ids) {
            return;
        }

        $hash = waRequest::post('hash', '');
        if (!$hash) {
            $product_ids = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
            if (!$product_ids) {
                return;
            }
            $this->category_products_model->add($product_ids, $category_ids);
        } else {
            $collection = new shopProductsCollection($hash);
            $offset = 0;
            $count = 100;
            $total_count = $collection->count();
            while ($offset < $total_count) {
                $ids = array_keys($collection->getProducts('*', $offset, $count));
                $this->category_products_model->add($ids, $category_ids);
                $offset += count($ids);
            }
        }

        $this->response['categories'] = $this->category_model->getByField('id', $category_ids, 'id');
    }
}