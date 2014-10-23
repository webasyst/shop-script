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

    public function __construct()
    {
        $this->category_model = new shopCategoryModel();
        $this->category_products_model = new shopCategoryProductsModel();
    }

    public function execute()
    {
        $category_ids = waRequest::post('category_id', array(), waRequest::TYPE_ARRAY_INT);

        // create new category
        $new_category_id = null;
        if (waRequest::post('new_category')) {
            $new_category_id = $this->createCategory(
                waRequest::post('new_category_name')
            );
            $category_ids[] = $new_category_id;
        }

        if (!$category_ids) {
            return;
        }

        // add products to categories
        $hash = waRequest::post('hash', '');
        if (!$hash) {
            $product_ids = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
            if (!$product_ids) {
                return;
            }
            // add just selected products
            $this->category_products_model->add($product_ids, $category_ids);
        } else {
            // add all products of collection with this hash
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

        // form a response
        $categories = $this->category_model->getByField('id', $category_ids, 'id');
        if (isset($categories[$new_category_id])) {
            $this->response['new_category'] = $categories[$new_category_id];
            unset($categories[$new_category_id]);
        }
        $this->response['categories'] = $categories;
    }

    public function createCategory($name)
    {
        $url = shopHelper::transliterate($name, false);
        $url = $this->category_model->suggestUniqueUrl($url);
        if (empty($name)) {
            $name = _w('(no-name)');
        }
        return $this->category_model->add(array(
            'name' => $name,
            'url'  => $url
        ));
    }
}