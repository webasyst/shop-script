<?php

class shopProdAddToCategoriesController extends waJsonController
{
    /**
     * @var shopCategoryModel
     */
    private $category_model;

    public function execute()
    {
        $this->category_model = new shopCategoryModel();
        $category_products_model = new shopCategoryProductsModel();

        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            $this->errors[] = [
                'id' => 'access_denied',
                'text' => _w('Access denied'),
            ];
        }

        $category_ids = waRequest::post('category_ids', [], waRequest::TYPE_ARRAY_INT);
        $new_category_id = null;
        $new_category = waRequest::post('new_category', '', waRequest::TYPE_STRING_TRIM);
        if ($new_category) {
            $new_category_id = $this->createCategory($new_category);
            $category_ids[] = $new_category_id;
        }
        if (!$category_ids) {
            return;
        }

        $product_id = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        if (!$presentation_id) {
            $all_product_ids = $product_id;
        } else {
            $presentation = new shopPresentation($presentation_id, true);
            $options = [];
            if ($presentation->getFilterId() > 0) {
                $options['prepare_filter'] = $presentation->getFilterId();
                $options['exclude_products'] = $product_id;
            }
            $collection = new shopProductsCollection('', $options);
            $all_product_ids = $presentation->getProducts($collection, [
                'fields' => ['id'],
                'offset' => max(0, waRequest::post('offset', 0, waRequest::TYPE_INT)),
            ]);
            $all_product_ids = array_keys($all_product_ids);
        }
        $hash = 'id/'.join(',', $all_product_ids);

        /**
         * Adds a product to the category. Get data before changes
         *
         * @param int $new_category_id
         * @param array $category_ids with $new_category_id
         * @param string $hash
         * @param array $all_product_ids products_id
         *
         * @event products_set_categories.before
         */
        $params = [
            'new_category_id' => $new_category_id,
            'category_ids'    => $category_ids,
            'hash'            => $hash,
            'products_id'     => $all_product_ids,
        ];
        wa('shop')->event('products_set_categories.before', $params);

        $ids_left = $all_product_ids;
        while ($ids_left) {
            $product_ids = array_splice($ids_left, 0, min(100, count($ids_left)));
            $category_products_model->add($product_ids, $category_ids);
        }

        $count_all_updated_products = count($all_product_ids);
        if ($count_all_updated_products > 1) {
            for ($offset = 0; $offset < $count_all_updated_products; $offset += 5000) {
                $part_updated_products = array_slice($all_product_ids, $offset, 5000);
                $this->logAction('products_edit', count($part_updated_products) . '$' . implode(',', $part_updated_products));
            }
        } elseif (isset($all_product_ids[0]) && is_numeric($all_product_ids[0])) {
            $this->logAction('product_edit', $all_product_ids[0]);
        }

        /**
         * Adds a product to the category
         *
         * @param int $new_category_id
         * @param array $category_ids with $new_category_id
         * @param string $hash
         * @param array|string $all_product_ids products_id
         *
         * @event products_set_categories.after
         */
        $params = [
            'new_category_id' => $new_category_id,
            'category_ids'    => $category_ids,
            'hash'            => $hash,
            'products_id'     => $all_product_ids,
        ];
        wa('shop')->event('products_set_categories.after', $params);

        $categories = $this->category_model->getByField('id', $category_ids, 'id');
        if (isset($categories[$new_category_id])) {
            $this->response['new_category'] = $categories[$new_category_id];
            unset($categories[$new_category_id]);
        }
        $this->response['categories'] = $categories;
    }

    protected function createCategory($name)
    {
        $url = shopHelper::transliterate($name, false);
        $url = $this->category_model->suggestUniqueUrl($url);
        if (empty($name)) {
            $name = _w('(no name)');
        }
        return $this->category_model->add([
            'name' => $name,
            'url' => $url
        ]);
    }
}
