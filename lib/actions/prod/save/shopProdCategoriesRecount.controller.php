<?php

class shopProdCategoriesRecountController extends waJsonController
{
    public function execute()
    {
        $static_ids = waRequest::post('static_ids', [], waRequest::TYPE_ARRAY_INT);
        $dynamic_ids = waRequest::post('dynamic_ids', [], waRequest::TYPE_ARRAY_INT);
        $category_ids = array_merge($static_ids, $dynamic_ids);
        $categories = $this->count($category_ids);
        if (!$this->errors) {
            $this->response['categories'] = $categories;
        }
    }

    protected function count($category_ids)
    {
        $category_model = new shopCategoryModel();

        $categories = [];
        if ($category_ids) {
            $old_count = $category_model->select('`id`, `count`')->where('`id` IN (?)', [$category_ids])->fetchAll('id');
            foreach ($category_ids as $category_id) {
                try {
                    $product_collection = new shopProductsCollection("category/$category_id");
                    $category_right_count = $product_collection->count();
                    $categories[] = [
                        'id' => $category_id,
                        'count' => $category_right_count,
                    ];
                    if (isset($old_count[$category_id]) && $old_count[$category_id]['count'] != $category_right_count) {
                        $category_model->update($category_id, ['count' => $category_right_count]);
                    }
                } catch (Exception $e) {
                    $this->errors = $e->getMessage();
                }
            }
        }

        return $categories;
    }
}