<?php

class shopFrontendCategoryAction extends shopFrontendAction
{
    public function execute()
    {
        $category_model = new shopCategoryModel();
        if (waRequest::param('category_id')) {
            $category = $category_model->getById(waRequest::param('category_id'));
        } else {
            $category = $category_model->getByField(waRequest::param('url_type') == 1 ? 'url' : 'full_url', waRequest::param('category_url'));
        }
        $route = wa()->getRouting()->getDomain(null, true).'/'.wa()->getRouting()->getRoute('url');
        if (!$category || ($category['route'] && $category['route'] != $route)) {
            throw new waException('Category not found', 404);
        }

        if ($category['filter']) {
            $filter_ids = explode(',', $category['filter']);
            $feature_model = new shopFeatureModel();
            $features = $feature_model->getById(array_filter($filter_ids, 'is_numeric'));
            if ($features) {
                $features = $feature_model->getValues($features);
            }
            $filters = array();
            foreach ($filter_ids as $fid) {
                if ($fid == 'price') {
                    $filters['price'] = true;
                } elseif (isset($features[$fid])) {
                    $filters[$fid] = $features[$fid];
                }
            }
            $this->view->assign('filters', $filters);
        }

        $category['subcategories'] = $category_model->getSubcategories($category, true);
        $category_url = wa()->getRouteUrl('shop/frontend/category', array('category_url' => '%CATEGORY_URL%'));
        foreach ($category['subcategories'] as &$sc) {
            $sc['url'] = str_replace('%CATEGORY_URL%', waRequest::param('url_type') == 1 ? $sc['url'] : $sc['full_url'], $category_url);
        }
        unset($sc);

        if ($category['parent_id']) {
            $breadcrumbs = array();
            $path = array_reverse($category_model->getPath($category['id']));
            foreach ($path as $row) {
                $breadcrumbs[] = array(
                    'url' => wa()->getRouteUrl('/frontend/category', array('category_url' => waRequest::param('url_type') == 1 ? $row['url'] : $row['full_url'])),
                    'name' => $row['name']
                );
            }
            if ($breadcrumbs && $this->layout) {
                $this->layout->assign('breadcrumbs', $breadcrumbs);
            }
        }

        if ($category['type'] == shopCategoryModel::TYPE_DYNAMIC && !$category['sort_products']) {
            $category['sort_products'] = 'create_datetime DESC';
        }

        $category_params_model = new shopCategoryParamsModel();
        $category['params'] = $category_params_model->get($category['id']);

        if ($this->getConfig()->getOption('can_use_smarty') && $category['description']) {
            $category['description'] = wa()->getView()->fetch('string:'.$category['description']);
        }


        $this->view->assign('category', $category);

        if ($category['sort_products'] && !waRequest::get('sort')) {
            $sort = explode(' ', $category['sort_products']);
            if (isset($sort[1])) {
                $order = strtolower($sort[1]);
            } else {
                $order = 'asc';
            }
            $_GET['sort'] = $sort[0];
            $_GET['order'] = $order;
        } elseif (!$category['sort_products'] && !waRequest::get('sort')) {
            $this->view->assign('active_sort', '');
        }


        $this->setCollection(new shopProductsCollection('category/'.$category['id']));

        $title = $category['meta_title'] ? $category['meta_title'] : $category['name'];
        wa()->getResponse()->setTitle($title);
        wa()->getResponse()->setMeta('keywords', $category['meta_keywords']);
        wa()->getResponse()->setMeta('description', $category['meta_description']);

        /**
         * @event frontend_category
         * @return array[string]string $return[%plugin_id%] html output for category
         */
        $this->view->assign('frontend_category', wa()->event('frontend_category'));
        $this->setThemeTemplate('category.html');
    }
}