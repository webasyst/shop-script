<?php

class shopProductAddToCategoryMethod extends shopProductUpdateMethod
{
    protected $method = 'POST';

    public function execute()
    {
        $id = $this->get('id', true);
        $this->getProduct($id);

        $category_id = $this->post('category_id', true);
        $category_model = new shopCategoryModel();
        $category = $category_model->getById($category_id);

        if (!$category) {
            throw new waAPIException('invalid_param', 'Category not found', 404);
        }

        if ($category['type'] == shopCategoryModel::TYPE_DYNAMIC) {
            throw new waAPIException('invalid_param', 'Category type must be static');
        }

        $category_products_model = new shopCategoryProductsModel();
        $category_products_model->add($id, $category_id);

        $this->response = true;
    }
}