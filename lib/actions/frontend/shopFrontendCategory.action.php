<?php

class shopFrontendCategoryAction extends shopFrontendAction
{
    public function execute()
    {
        $category_model = new shopCategoryModel();
        $url_field = waRequest::param('url_type') == 1 ? 'url' : 'full_url';

        if (waRequest::param('category_id')) {
            $category = $category_model->getById(waRequest::param('category_id'));
            if ($category) {
                $category_url = wa()->getRouteUrl('/frontend/category', array('category_url' => $category[$url_field]));
                if (urldecode(wa()->getConfig()->getRequestUrl(false, true)) !== $category_url) {
                    $q = waRequest::server('QUERY_STRING');
                    $this->redirect($category_url.($q ? '?'.$q : ''), 301);
                }
            }
        } else {
            $category = $category_model->getByField($url_field, waRequest::param('category_url'));
            if ($category && $category[$url_field] !== urldecode(waRequest::param('category_url'))) {
                $q = waRequest::server('QUERY_STRING');
                $this->redirect(wa()->getRouteUrl('/frontend/category', array('category_url' => $category[$url_field])).($q ? '?'.$q : ''), 301);
            }
        }
        $route = wa()->getRouting()->getDomain(null, true) . '/' . wa()->getRouting()->getRoute('url');
        if ($category) {
            $category_routes_model = new shopCategoryRoutesModel();
            $routes = $category_routes_model->getRoutes($category['id']);
        }
        if (!$category || ($routes && !in_array($route, $routes))) {
            throw new waException('Category not found', 404);
        }



        $category['subcategories'] = $category_model->getSubcategories($category, $route);

        if ($category['filter']) {
            $filter_ids = explode(',', $category['filter']);
            $feature_model = new shopFeatureModel();
            $features = $feature_model->getById(array_filter($filter_ids, 'is_numeric'));
            if ($features) {
                $features = $feature_model->getValues($features);
            }
            // if static category
            if (!$category['type']) {
                $product_features_model = new shopProductFeaturesModel();
                if ($category['include_sub_categories']) {
                    $cids = $category_model->descendants($category, true)->select('id')->where('type = '.shopCategoryModel::TYPE_STATIC)->fetchAll(null, true);
                    $category_values = $product_features_model->getValuesByCategory($cids);
                } else {
                    $category_values = $product_features_model->getValuesByCategory($category['id']);
                }
            }

            $filters = array();
            foreach ($filter_ids as $fid) {
                if ($fid == 'price') {
                    $filters['price'] = true;
                } elseif (isset($features[$fid])) {
                    if ($category['type'] || isset($category_values[$fid])) {
                        $filters[$fid] = $features[$fid];
                        if (false && ($filters[$fid]['type'] == shopFeatureModel::TYPE_BOOLEAN)) {
                            unset($filters[$fid]['values'][0]);
                        } else {
                            if (isset($category_values[$fid])) {
                                foreach ($filters[$fid]['values'] as $v_id => $v) {
                                    if (!in_array($v_id, $category_values[$fid])) {
                                        unset($filters[$fid]['values'][$v_id]);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $this->view->assign('filters', $filters);
        }
        $category_url = wa()->getRouteUrl('shop/frontend/category', array('category_url' => '%CATEGORY_URL%'));
        foreach ($category['subcategories'] as &$sc) {
            $sc['url'] = str_replace('%CATEGORY_URL%', waRequest::param('url_type') == 1 ? $sc['url'] : $sc['full_url'], $category_url);
        }
        unset($sc);

        $this->addCanonical();

        $root_category_id = $category['id'];

        if ($category['parent_id']) {
            $breadcrumbs = array();
            $path = array_reverse($category_model->getPath($category['id']));
            $root_category = reset($path);
            $root_category_id = $root_category['id'];
            foreach ($path as $row) {
                $breadcrumbs[] = array(
                    'url' => wa()->getRouteUrl('/frontend/category', array('category_url' => waRequest::param('url_type') == 1 ? $row['url'] : $row['full_url'])),
                    'name' => $row['name']
                );
            }
            if ($breadcrumbs) {
                $this->view->assign('breadcrumbs', $breadcrumbs);
            }
        }

        $this->view->assign('root_category_id', $root_category_id);

        if ($category['type'] == shopCategoryModel::TYPE_DYNAMIC && !$category['sort_products']) {
            $category['sort_products'] = 'create_datetime DESC';
        }

        $category_params_model = new shopCategoryParamsModel();
        $category['params'] = $category_params_model->get($category['id']);

        if ($this->getConfig()->getOption('can_use_smarty') && $category['description']) {
            $category['description'] = wa()->getView()->fetch('string:' . $category['description']);
        }


        $this->view->assign('category', $category);

        if ($category['sort_products'] && !waRequest::get('sort')) {
            $sort = explode(' ', $category['sort_products']);
            if (isset($sort[1])) {
                $order = strtolower($sort[1]);
            } else {
                $order = 'asc';
            }
            //$_GET['order'] = $order;
            $this->view->assign('active_sort', $sort[0] == 'count' ? 'stock' : $sort[0]);
        } elseif (!$category['sort_products'] && !waRequest::get('sort')) {
            $this->view->assign('active_sort', '');
        }

        $this->setCollection(new shopProductsCollection('category/' . $category['id']));

        $title = $category['meta_title'] ? $category['meta_title'] : $category['name'];
        wa()->getResponse()->setTitle($title);
        wa()->getResponse()->setMeta('keywords', $category['meta_keywords']);
        wa()->getResponse()->setMeta('description', $category['meta_description']);

        /**
         * @event frontend_category
         * @return array[string]string $return[%plugin_id%] html output for category
         */
        $this->view->assign('frontend_category', wa()->event('frontend_category', $category));
        $this->setThemeTemplate('category.html');
    }
}
