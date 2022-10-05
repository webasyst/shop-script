<?php

class shopProdCategoryStorefrontsController extends waJsonController
{
    public function execute()
    {
        $category_id = waRequest::post('category_id', null, waRequest::TYPE_INT);
        $storefronts = waRequest::post('storefronts', [], waRequest::TYPE_ARRAY_TRIM);

        $this->validateData($category_id);

        if (!$this->errors) {
            $this->save($category_id, $storefronts);
        }
    }

    protected function validateData($category_id)
    {
        $correct_category = false;
        if ($category_id > 0) {
            $category_model = new shopCategoryModel();
            $category = $category_model->getById($category_id);
            if ($category) {
                $correct_category = true;
            }
        }
        if (!$correct_category) {
            $this->errors = [
                'id' => 'not_found',
                'text' => _w('The category to update was not found.')
            ];
        }
    }

    protected function save($category_id, $storefronts)
    {
        $category_routes_model = new shopCategoryRoutesModel();
        $category_routes_model->setRoutes($category_id, $storefronts);
        $this->logAction('category_edit', $category_id);
        $this->response['updated'] = true;
    }
}
