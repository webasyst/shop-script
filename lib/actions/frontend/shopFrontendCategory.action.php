<?php

class shopFrontendCategoryAction extends shopFrontendAction
{
    /**
     * @var shopCategoryModel $model
     */
    protected $model;

    /**
     * @return shopCategoryModel
     */
    protected function getModel()
    {
        if (!$this->model) {
            $this->model = new shopCategoryModel();
        }
        return $this->model;
    }

    /**
     * @return mixed
     * @throws waException
     */
    protected function getCategory()
    {
        $category_model = $this->getModel();
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
        $category_url = wa()->getRouteUrl('shop/frontend/category', array('category_url' => '%CATEGORY_URL%'));
        foreach ($category['subcategories'] as &$sc) {
            $sc['url'] = str_replace('%CATEGORY_URL%', waRequest::param('url_type') == 1 ? $sc['url'] : $sc['full_url'], $category_url);
            $sc['params'] = array();
        }
        unset($sc);

        // params for category and subcategories
        $category['params'] = array();
        $category_params_model = new shopCategoryParamsModel();
        $rows = $category_params_model->getByField('category_id', array_keys(array($category['id'] => 1) + $category['subcategories']), true);
        foreach($rows as $row) {
            if (!empty($category['subcategories'][$row['category_id']])) {
                $category['subcategories'][$row['category_id']]['params'][$row['name']] = $row['value'];
            } else if ($row['category_id'] == $category['id']) {
                $category['params'][$row['name']] = $row['value'];
            }
        }

        // smarty description
        if ($this->getConfig()->getOption('can_use_smarty') && $category['description']) {
            $category['description'] = wa()->getView()->fetch('string:' . $category['description']);
        }

        // Open Graph data
        $category_og_model = new shopCategoryOgModel();
        $category['og'] = $category_og_model->get($category['id']) + array(
            'type' => 'article',
            'title' => $category['meta_title'],
            'description' => $category['meta_description'],
            'url' => wa()->getConfig()->getHostUrl().wa()->getConfig()->getRequestUrl(false, true),
            'image' => '',
        );

        return $category;
    }

    public function execute()
    {
        $category = $this->getCategory();
        $this->addCanonical();
        // breadcrumbs
        $root_category_id = $category['id'];
        if ($category['parent_id']) {
            $breadcrumbs = array();
            $path = array_reverse($this->getModel()->getPath($category['id']));
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
        // sort
        if ($category['type'] == shopCategoryModel::TYPE_DYNAMIC && !$category['sort_products']) {
            $category['sort_products'] = 'create_datetime DESC';
        }
        if ($category['sort_products'] && !waRequest::get('sort')) {
            $sort = explode(' ', $category['sort_products']);
            $this->view->assign('active_sort', $sort[0] == 'count' ? 'stock' : $sort[0]);
        } elseif (!$category['sort_products'] && !waRequest::get('sort')) {
            $this->view->assign('active_sort', '');
        }
        $this->view->assign('category', $category);

        // products
        $collection = new shopProductsCollection('category/' . $category['id']);

        // filters
        if ($category['filter']) {
            $filter_ids = explode(',', $category['filter']);
            $feature_model = new shopFeatureModel();
            $features = $feature_model->getById(array_filter($filter_ids, 'is_numeric'));
            if ($features) {
                $features = $feature_model->getValues($features);
            }
            $category_value_ids = $collection->getFeatureValueIds();

            $filters = array();
            foreach ($filter_ids as $fid) {
                if ($fid == 'price') {
                    $range = $collection->getPriceRange();
                    if ($range['min'] != $range['max']) {
                        $filters['price'] = array(
                            'min' => shop_currency($range['min'], null, null, false),
                            'max' => shop_currency($range['max'], null, null, false),
                        );
                    }
                } elseif (isset($features[$fid]) && isset($category_value_ids[$fid])) {
                    $filters[$fid] = $features[$fid];
                    $min = $max = $unit = null;
                    foreach ($filters[$fid]['values'] as $v_id => $v) {
                        if (!in_array($v_id, $category_value_ids[$fid])) {
                            unset($filters[$fid]['values'][$v_id]);
                        } else {
                            if ($v instanceof shopRangeValue) {
                                $begin = $this->getFeatureValue($v->begin);
                                if ($min === null || $begin < $min) {
                                    $min = $begin;
                                }
                                $end = $this->getFeatureValue($v->end);
                                if ($max === null || $end > $max) {
                                    $max = $end;
                                    if ($v->end instanceof shopDimensionValue) {
                                        $unit = $v->end->unit;
                                    }
                                }
                            } else {
                                $tmp_v = $this->getFeatureValue($v);
                                if ($min === null || $tmp_v < $min) {
                                    $min = $tmp_v;
                                }
                                if ($max === null || $tmp_v > $max) {
                                    $max = $tmp_v;
                                    if ($v instanceof shopDimensionValue) {
                                        $unit = $v->unit;
                                    }
                                }
                            }
                        }
                    }
                    if (!$filters[$fid]['selectable'] && ($filters[$fid]['type'] == 'double' ||
                        substr($filters[$fid]['type'], 0, 6) == 'range.' ||
                        substr($filters[$fid]['type'], 0, 10) == 'dimension.')) {
                        if ($min == $max) {
                            unset($filters[$fid]);
                        } else {
                            $type = preg_replace('/^[^\.]*\./', '', $filters[$fid]['type']);
                            if ($type != 'double') {
                                $filters[$fid]['base_unit'] = shopDimension::getBaseUnit($type);
                                $filters[$fid]['unit'] = shopDimension::getUnit($type, $unit);
                                if ($filters[$fid]['base_unit']['value'] != $filters[$fid]['unit']['value']) {
                                    $dimension = shopDimension::getInstance();
                                    $min = $dimension->convert($min, $type, $filters[$fid]['unit']['value']);
                                    $max = $dimension->convert($max, $type, $filters[$fid]['unit']['value']);
                                }
                            }
                            $filters[$fid]['min'] = $min;
                            $filters[$fid]['max'] = $max;
                        }
                    }
                }
            }
            $this->view->assign('filters', $filters);

            $this->setCollection($collection);

            // fix prices
            $products = $this->view->getVars('products');
            $product_ids = array();
            foreach ($products as $p_id => $p) {
                if ($p['sku_count'] > 1) {
                    $product_ids[] = $p_id;
                }
            }
            if ($product_ids) {
                $min_price = $max_price = null;
                $tmp = array();
                foreach ($filters as $fid => $f) {
                    if ($fid == 'price') {
                        $min_price = waRequest::get('price_min');
                        if (!empty($min_price)) {
                            $min_price = (double)$min_price;
                        } else {
                            $min_price = null;
                        }
                        $max_price = waRequest::get('price_max');
                        if (!empty($max_price)) {
                            $max_price = (double)$max_price;
                        } else {
                            $max_price = null;
                        }
                    } else {
                        $fvalues = waRequest::get($f['code']);
                        if ($fvalues && !isset($fvalues['min']) && !isset($fvalues['max'])) {
                            $tmp[$fid] = $fvalues;
                        }
                    }
                }
                $rows = array();
                if ($tmp) {
                    $pf_model = new shopProductFeaturesModel();
                    $rows = $pf_model->getSkusByFeatures($product_ids, $tmp, waRequest::param('drop_out_of_stock') == 2);
                    $image_ids = array();
                    foreach ($rows as $row) {
                        if ($row['image_id']) {
                            $image_ids[] = $row['image_id'];
                        }
                    }
                    if ($image_ids) {
                        $image_model = new shopProductImagesModel();
                        $images = $image_model->getById($image_ids);
                        foreach ($rows as &$row) {
                            if ($row['image_id'] && isset($images[$row['image_id']])) {
                                $row['ext'] = $images[$row['image_id']]['ext'];
                                $row['image_filename'] = $images[$row['image_id']]['filename'];
                            }
                        }
                        unset($row);
                    }
                } elseif ($min_price || $max_price) {
                    $ps_model = new shopProductSkusModel();
                    $rows = $ps_model->getByField('product_id', $product_ids, true);
                }

                $event_params = array(
                    'products' => $products,
                    'skus' => &$rows
                );
                wa('shop')->event('frontend_products', $event_params);

                $product_skus = array();
                shopRounding::roundSkus($rows, $products);
                foreach ($rows as $row) {
                    $product_skus[$row['product_id']][] = $row;
                }

                $default_currency = $this->getConfig()->getCurrency(true);
                if ($product_skus) {
                    foreach ($product_skus as $product_id => $skus) {
                        $currency = $products[$product_id]['currency'];

                        usort($skus, array($this, 'sortSkus'));
                        $k = 0;
                        if ($min_price || $max_price) {
                            foreach ($skus as $i => $sku) {
                                if ($min_price) {
                                    $tmp_price = shop_currency($min_price, true, $currency, false);
                                    if ($sku['price'] < $tmp_price) {
                                        continue;
                                    }
                                }
                                if ($max_price) {
                                    $tmp_price = shop_currency($max_price, true, $currency, false);
                                    if ($sku['price'] > $tmp_price) {
                                        continue;
                                    }
                                }
                                $k = $i;
                                break;
                            }
                        }
                        $sku = $skus[$k];
                        if ($products[$product_id]['sku_id'] != $sku['id']) {
                            $products[$product_id]['sku_id'] = $sku['id'];
                            $products[$product_id]['frontend_url'] .= '?sku='.$sku['id'];
                            $products[$product_id]['price'] = shop_currency($sku['price'], $currency, $default_currency, false);
                            $products[$product_id]['frontend_price'] = $sku['price'];
                            $products[$product_id]['unconverted_price'] = shop_currency($sku['unconverted_price'], $currency, $default_currency, false);
                            $products[$product_id]['compare_price'] = shop_currency($sku['compare_price'], $currency, $default_currency, false);
                            $products[$product_id]['frontend_compare_price'] = $sku['compare_price'];
                            $products[$product_id]['unconverted_compare_price'] = shop_currency($sku['unconverted_compare_price'], $currency, $default_currency, false);
                            if ($sku['image_id'] && $products[$product_id]['image_id'] != $sku['image_id']) {
                                if (isset($sku['ext'])) {
                                    $products[$product_id]['image_id'] = $sku['image_id'];
                                    $products[$product_id]['ext'] = $sku['ext'];
                                    $products[$product_id]['image_filename'] = $sku['image_filename'];
                                }
                            }
                        }
                    }
                    $this->view->assign('products', $products);
                }
            }
        } else {
            $this->setCollection($collection);
        }

        // set meta
        wa()->getResponse()->setTitle($category['meta_title']);
        wa()->getResponse()->setMeta('keywords', $category['meta_keywords']);
        wa()->getResponse()->setMeta('description', $category['meta_description']);
        foreach(ifset($category['og'], array()) as $property => $content) {
            $content && wa()->getResponse()->setOgMeta('og:'.$property, $content);
        }

        /**
         * @event frontend_category
         * @return array[string]string $return[%plugin_id%] html output for category
         */
        $this->view->assign('frontend_category', wa()->event('frontend_category', $category));

        // default title and meta
        if (!wa()->getResponse()->getTitle()) {
            wa()->getResponse()->setTitle(shopCategoryModel::getDefaultMetaTitle($category));
        }

        if (!wa()->getResponse()->getMeta('keywords')) {
            wa()->getResponse()->setMeta('keywords', shopCategoryModel::getDefaultMetaKeywords($category));
        }

        $this->setThemeTemplate('category.html');
    }

    /**
     * @param shopDimensionValue|double $v
     * @return double
     */
    protected function getFeatureValue($v)
    {
        if ($v instanceof shopDimensionValue) {
            return $v->value_base_unit;
        }
        if (is_object($v)) {
            return $v->value;
        }
        return $v;
    }

    protected function sortSkus($a, $b)
    {
        if ($a['sort'] == $b['sort']) {
            return 0;
        }
        return ($a['sort'] < $b['sort']) ? -1 : 1;
    }
}
