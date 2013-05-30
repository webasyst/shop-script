<?php

class shopFrontendProductAction extends shopFrontendAction
{
    /**
     * @var shopProductReviewsModel
     */
    protected $reviews_model;

    public function __construct($params = null) {
        $this->reviews_model = new shopProductReviewsModel();
        parent::__construct($params);
    }

    public function getBreadcrumbs(shopProduct $product, $product_link = false)
    {
        if ($product['category_id']) {
            $category_model = new shopCategoryModel();
            $category = $category_model->getById($product['category_id']);
            $product['category_url'] = waRequest::param('url_type') == 1 ? $category['url'] : $category['full_url'];

            if (waRequest::param('url_type') == 2 && !waRequest::param('category_url')) {
                $this->redirect(wa()->getRouteUrl('/frontend/product', array('product_url' => $product['url'], 'category_url' => $product['category_url'])));
            }
            $breadcrumbs = array();
            $path = $category_model->getPath($category['id']);
            $path = array_reverse($path);
            foreach ($path as $row) {
                $breadcrumbs[] = array(
                    'url' => wa()->getRouteUrl('/frontend/category', array('category_url' => waRequest::param('url_type') == 1 ? $row['url'] : $row['full_url'])),
                    'name' => $row['name']
                );
            }
            $breadcrumbs[] = array(
                'url' => wa()->getRouteUrl('/frontend/category', array('category_url' => waRequest::param('url_type') == 1 ? $category['url'] : $category['full_url'])),
                'name' => $category['name']
            );
            if ($product_link) {
                $breadcrumbs[] = array(
                    'url' => wa()->getRouteUrl('/frontend/product', array('product_url' => $product['url'], 'category_url' => $product['category_url'])),
                    'name' => $product['name']
                );
            }
            if ($breadcrumbs) {
                if ($this->layout) {
                    $this->layout->assign('breadcrumbs', $breadcrumbs);
                }
                $this->view->assign('breadcrumbs', $breadcrumbs);
            }
        }
    }

    public function execute()
    {
        $this->setLayout(new shopFrontendLayout());
        if ($this->params) {
            $product = $this->params;
        } else {
            $product_model = new shopProductModel();
            $product = $product_model->getByField('url', waRequest::param('product_url'));
        }

        if (!$product) {
            throw new waException(_w('Product not found'), 404);
        }

        if (waRequest::param('category_url')) {
            $category_model = new shopCategoryModel();
            $c = $category_model->getByField('full_url', waRequest::param('category_url'));
            if (!$c) {
                throw new waException(_w('Product not found'), 404);
            }
        }

        if ($types = waRequest::param('type_id')) {
            if (!in_array($product['type_id'], (array)$types)) {
                throw new waException(_w('Product not found'), 404);
            }
        }


        $product = new shopProduct($product);
        if (!isset($product->skus[$product->sku_id])) {
            $product->sku_id = $product->skus ? key($product->skus) : null;
        }
        if (!$product->skus) {
            $product->skus = array(
                null => array(
                    'name' => '',
                    'sku' => '',
                    'id' => null,
                    'available' => false,
                    'count' => 0,
                    'price' => null,
                    'stock' => array()
                )
            );
        }

        if ($this->getConfig()->getOption('can_use_smarty') && $product->description) {
            $product->description = wa()->getView()->fetch('string:'.$product->description);
        }

        $this->view->assign('product', $product);

        $this->getBreadcrumbs($product);

        // get services
        $type_services_model = new shopTypeServicesModel();
        $services = $type_services_model->getServiceIds($product['type_id']);

        $service_model = new shopServiceModel();
        $product_services_model = new shopProductServicesModel();
        $services = array_merge($services, $product_services_model->getServiceIds($product['id']));
        $services = array_unique($services);

        $services = $service_model->getById($services);

        $variants_model = new shopServiceVariantsModel();
        $rows = $variants_model->getByField('service_id', array_keys($services), true);
        foreach ($rows as $row) {
            if (!$row['price']) {
                $row['price'] = $services[$row['service_id']]['price'];
            }
            $services[$row['service_id']]['variants'][$row['id']] = $row;
        }


        $rows = $product_services_model->getByField('product_id', $product['id'], true);
        $skus_services = array();
        foreach ($product['skus'] as $sku) {
            $skus_services[$sku['id']] = array();
        }
        foreach ($rows as $row) {
            if (!$row['sku_id']) {
                // remove disabled services and variants
                if (!$row['status']) {
                    unset($services[$row['service_id']]['variants'][$row['service_variant_id']]);
                } elseif ($row['price'] !== null) {
                    // update price
                    $services[$row['service_id']]['variants'][$row['service_variant_id']]['price'] = $row['price'];
                }
            } else {
                if (!$row['status']) {
                    $skus_services[$row['sku_id']][$row['service_id']][$row['service_variant_id']] = false;
                } else {
                    $skus_services[$row['sku_id']][$row['service_id']][$row['service_variant_id']] = $row['price'];
                }
            }
        }

        foreach ($skus_services as &$sku_services) {
            foreach ($services as $service_id => $service) {
                if (isset($sku_services[$service_id])) {
                    if ($sku_services[$service_id]) {
                        foreach ($service['variants'] as $v) {
                            if (!isset($sku_services[$service_id][$v['id']]) || $sku_services[$service_id][$v['id']] === null) {
                                $sku_services[$service_id][$v['id']] = array($v['name'], $this->getPrice($v['price'], $service['currency']));
                            } elseif ($sku_services[$service_id][$v['id']]) {
                                $sku_services[$service_id][$v['id']] = array($v['name'], $this->getPrice($sku_services[$service_id][$v['id']], $service['currency']));
                            }
                        }
                    }
                } else {
                    foreach ($service['variants'] as $v) {
                        $sku_services[$service_id][$v['id']] = array($v['name'], $this->getPrice($v['price'], $service['currency']));
                    }
                }
            }
        }
        unset($sku_services);

        // disable service if all variants disabled
        foreach ($skus_services as $sku_id => $sku_services) {
            foreach ($sku_services as $service_id => $service) {
                if (is_array($service)) {
                    $disabled = true;
                    foreach ($service as $v) {
                        if ($v !== false) {
                            $disabled = false;
                            break;
                        }
                    }
                    if ($disabled) {
                        $skus_services[$sku_id][$service_id] = false;
                    }
                }
            }
        }

        foreach ($services as $s_id => &$s) {
            if (!$s['variants']) {
                unset($services[$s_id]);
            } elseif (count($s['variants']) == 1) {
                $v = reset($s['variants']);
                if ($v['name']) {
                    $s['name'] .= ' '.$v['name'];
                }
                $s['variant_id'] = $v['id'];
                $s['price'] = $v['price'];
                unset($s['variants']);
                foreach ($skus_services as $sku_id => $sku_services) {
                    if (isset($sku_services[$s_id]) && isset($sku_services[$s_id][$v['id']])) {
                        $skus_services[$sku_id][$s_id] = $sku_services[$s_id][$v['id']][1];
                    }
                }
            }
        }
        unset($s);

        $this->view->assign('sku_services', $skus_services);
        $this->view->assign('services', $services);

        $compare = waRequest::cookie('shop_compare', array(), waRequest::TYPE_ARRAY_INT);
        $this->view->assign('compare', in_array($product['id'], $compare) ? $compare : array());

        $this->view->assign('reviews', $this->getTopReviews($product['id']));
        $this->view->assign('reviews_total_count', $this->getReviewsTotalCount($product['id']));

        $title = $product['meta_title'] ? $product['meta_title'] : $product['name'];
        wa()->getResponse()->setTitle($title);
        wa()->getResponse()->setMeta('keywords', $product['meta_keywords']);
        wa()->getResponse()->setMeta('description', $product['meta_description']);

        $feature_codes = array_keys($product->features);
        $feature_model = new shopFeatureModel();
        $features = $feature_model->getByCode($feature_codes);

        if ($product->sku_type == shopProductModel::SKU_TYPE_SELECTABLE) {
            $features_selectable_model = new shopProductFeaturesSelectableModel();
            $selectable = $features_selectable_model->getByProduct($product->id);
            $features_selectable = $feature_model->getById(array_keys($selectable));
            $features_selectable = $feature_model->getValues($features_selectable);
            foreach ($features_selectable as $f_id => $f) {
                foreach ($f['values'] as $v_id => $v) {
                    if (!in_array($v_id, $selectable[$f_id])) {
                        unset($features_selectable[$f_id]['values'][$v_id]);
                    }
                }
            }
            $this->view->assign('features_selectable', $features_selectable);

            $product_features_model = new shopProductFeaturesModel();
            $sku_features = $product_features_model->getSkuFeatures($product->id);

            $sku_selectable = array();
            foreach ($sku_features as $sku_id => $sf) {
                if (!isset($product->skus[$sku_id])) {
                    continue;
                }
                $sku_f = "";
                foreach ($features_selectable as $f_id => $f) {
                    if (isset($sf[$f_id])) {
                        $sku_f .= $f_id.":".$sf[$f_id].";";
                    }
                }
                $sku = $product->skus[$sku_id];
                $sku_selectable[$sku_f] = array(
                    'id' => $sku_id,
                    'price' => (float)shop_currency($sku['price'], $product['currency'], null, false),
                    'available' => $product->status && $sku['available'] &&
                        ($this->getConfig()->getGeneralSettings('ignore_stock_count') || $sku['count'] === null || $sku['count'] > 0),
                    'image_id' => (int)$sku['image_id']
                );
            }
            $this->view->assign('sku_features_selectable', $sku_selectable);
        }

        $this->view->assign('features', $features);

        $this->view->assign('currency_info', $this->getCurrenyInfo());

        /**
         * @event frontend_product
         * @param shopProduct $product
         * @return array[string][string]string $return[%plugin_id%]['menu'] html output
         * @return array[string][string]string $return[%plugin_id%]['cart'] html output
         * @return array[string][string]string $return[%plugin_id%]['block_aux'] html output
         * @return array[string][string]string $return[%plugin_id%]['block'] html output
         */
        $this->view->assign('frontend_product', wa()->event('frontend_product', $product));

        $sku_stocks = array();
        foreach ($product->skus as $sku) {
            $sku_stocks[$sku_id] = array($sku['count'], $sku['stock']);
        }
        $stock_model = new shopStockModel();
        $this->view->assign('stocks', $stock_model->getAll('id'));

        $this->setThemeTemplate('product.html');
    }

    protected function getCurrenyInfo()
    {
        $currency = waCurrency::getInfo($this->getConfig()->getCurrency(false));
        $locale = waLocale::getInfo(wa()->getLocale());
        return array(
            'code' => $currency['code'],
            'sign' => $currency['sign'],
            'sign_position' => isset($currency['sign_position']) ? $currency['sign_position'] : 1,
            'sign_delim' => isset($currency['sign_delim']) ? $currency['sign_delim'] : ' ',
            'decimal_point' => $locale['decimal_point'],
            'frac_digits' => $locale['frac_digits'],
            'thousands_sep' => $locale['thousands_sep'],
        );
    }

    protected function getPrice($price, $currency)
    {
        return shop_currency($price, $currency, null, 0);
    }

    protected function getReviewsTotalCount($product_id)
    {
        return $this->reviews_model->count($product_id, false);
    }

    protected function getTopReviews($product_id)
    {
        return $this->reviews_model->getReviews($product_id,
                0, wa()->getConfig()->getOption('reviews_per_page_product'),
                'datetime DESC',
                array('escape' => true)
        );
    }
}