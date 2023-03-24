<?php

class shopProdExcludeFromCategoriesDialogAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            throw new waRightsException(_w('Access denied'));
        }

        $product_ids = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        if ($presentation_id) {
            $presentation = new shopPresentation($presentation_id, true);
            $options = [];
            if ($presentation->getFilterId() > 0) {
                $options['prepare_filter'] = $presentation->getFilterId();
                $options['exclude_products'] = $product_ids;
            }
            $collection = new shopProductsCollection('', $options);
            $category_products = $presentation->getProducts($collection, [
                'fields' => ['id'],
                'offset' => max(0, waRequest::post('offset', 0, waRequest::TYPE_INT)),
            ]);
            $product_ids = array_keys($category_products);
        }

        $this->view->assign([
            'categories' => $this->getCategories($product_ids)
        ]);

        $this->setTemplate('templates/actions/prod/main/dialogs/products.exclude_from_categories.html');
    }

    /**
     * @param $product_ids
     * @return array
     */
    protected function getCategories($product_ids)
    {
        $category_tree = [];

        if ($product_ids) {
            $category_products_model = new shopCategoryProductsModel();
            $category_products = $category_products_model->getByField('product_id', $product_ids, 'category_id');
            $required_category_ids = array_keys($category_products);
            if ($required_category_ids) {
                $category_model = new shopCategoryModel();
                $full_tree = $category_model->getFullTree('id, name, parent_id, depth, count, type, status', true);
                $filtered_tree = [];
                $not_used = false;
                do {
                    $related_categories = array_intersect_key($full_tree, array_flip($required_category_ids));
                    foreach ($related_categories as $key => $related_category) {
                        $related_categories[$key]['not_used'] = $not_used;
                    }
                    if (!$not_used) {
                        $not_used = true;
                    }
                    $filtered_tree += $related_categories;
                    $required_category_ids = array_values(array_unique(array_column($related_categories, 'parent_id')));
                } while ($required_category_ids && (isset($required_category_ids[1]) || $required_category_ids[0] > 0));
                $category_tree = $this->buildNestedTree($filtered_tree);
            }
        }
        return $category_tree;
    }

    /**
     * Format a flat list of categories into a tree
     * @param array $categories
     * @return array
     */
    protected function buildNestedTree($categories)
    {
        $tree = [];
        foreach($categories as &$category) {
            $category['categories'] = ifset($category, 'categories', []);
            if ($category['parent_id'] > 0) {
                if (isset($categories[$category['parent_id']])) {
                    $categories[$category['parent_id']]['categories'][] = &$category;
                }
            } else {
                $tree[] = &$category;
            }
        }
        unset($category, $categories);
        return $tree;
    }
}
