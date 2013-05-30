<?php
class shopProductAction extends waViewAction
{
    /**
     * @var waAppSettingsModel
     */
    protected $app_settings_model;

    public function execute()
    {
        $product = new shopProduct(waRequest::get('id', 0, waRequest::TYPE_INT));
        if (!$product->id) {
            if (waRequest::get('id') == 'new') {
                $product->name = '';
                $product->id = 'new';
                $product->status = 1;
            } else {
                throw new waException("Product not found", 404);
            }
        }

        $counters = array(
            'reviews'  => 0, 'images'   => 0, 'pages'    => 0, 'services' => 0
        );
        $sidebar_counters = array();
        $config = $this->getConfig();

        #load product types
        $type_model = new shopTypeModel();
        $product_types = $type_model->getAll($type_model->getTableId(), true);
        $product_types_count = count($product_types);

        if ($product_types) {
            if (!$this->getUser()->isAdmin($this->getAppId())) {
                $rights = $this->getUser()->getRights($this->getAppId(), 'type.%');
                if (empty($rights['all'])) {
                    foreach ($product_types as $id => $type) {
                        if (empty($rights[$id])) {
                            unset($product_types[$id]);
                        }
                    }
                }
            }
        }

        if (intval($product->id)) {
            # 1 fill extra product data
            # 1.1 fill product reviews
            $product_reviews_model = new shopProductReviewsModel();
            $product['reviews'] = $product_reviews_model->getReviews(
                $product->id,
                0,
                $config->getOption('reviews_per_page_product'),
                'datetime DESC',
                array('is_new' => true)
            );
            $counters['reviews'] = $product_reviews_model->count($product->id);
            $sidebar_counters['reviews'] = array(
                'new' => $product_reviews_model->countNew()
            );

            $counters['images'] = count($product['images']);

            $product_pages_model = new shopProductPagesModel();
            $counters['pages'] = $product_pages_model->count($product->id);

            $product_services_model = new shopProductServicesModel();
            $counters['services'] = $product_services_model->countServices($product->id);

            $this->view->assign('edit_rights', $product->checkRights());
        } else {
            $counters += array_fill_keys(array('images', 'services', 'pages', 'reviews'), 0);
            $product['images'] = array();
            reset($product_types);

            $product->type_id = 0;
            if ($product_types_count) {
                if (!$product_types) {
                    throw new waRightsException(_w("Access denied"));
                } else {
                    reset($product_types);
                    $product->type_id = key($product_types);
                }
            } elseif (!$product->checkRights()) {
                throw new waRightsException(_w("Access denied"));
            }
            $this->view->assign('edit_rights', true);

            $product['skus'] = array(
                '-1' => array(
                    'id'             => - 1,
                    'sku'            => '',
                    'available'      => 1,
                    'name'           => '',
                    'price'          => 0.0,
                    'purchase_price' => 0.0,
                    'count'          => null,
                    'stock'          => array(),
                    'virtual'        => 0
                ),
            );
            $product->currency = $config->getCurrency();

        }

        $this->assignReportsData($product);

        $stock_model = new shopStockModel();
        $taxes_mode = new shopTaxModel();
        $this->view->assign('stocks', $stock_model->getAll('id'));

        $this->view->assign(array(
            'use_product_currency' => wa()->getSetting('use_product_currency'),
            'currencies'           => $this->getCurrencies(),
            'primary_currency'     => $config->getCurrency(),
            'taxes'                => $taxes_mode->getAll(),
        ));

        // %product_url% - stuff used only when creating new product
        $stuff = $product->url ? $product->url : '%product_url%';
        $frontend_url = null;
        $fontend_base_url = null;

        if ($product->id) {
            $routing = wa()->getRouting();
            $domain_routes = $routing->getByApp($this->getAppId());
            foreach ($domain_routes as $domain => $routes) {
                foreach ($routes as $r) {
                    if (empty($r['type_id']) || (in_array($product->type_id, (array) $r['type_id']))) {
                        $routing->setRoute($r, $domain);
                        $frontend_url = $routing->getUrl('/frontend/product', array('product_url' => $stuff), true);
                        break;
                    }
                }
            }
        }
        if (empty($frontend_url) && !$product->id) {
            $frontend_url = wa()->getRouteUrl('/frontend/product', array('product_url' => $stuff), true);
        }
        if (!empty($frontend_url)) {
            $pos = strrpos($frontend_url, $stuff);
            $fontend_base_url = $pos !== false ? rtrim(substr($frontend_url, 0, $pos), '/').'/' : $frontend_url;
        }

        /**
         * !!! FIXME: update this description?.. E.g. include title_suffix. Or remove it...
         *
         * Backend product profile page
         * UI hook allow extends product profile page
         * @event backend_product
         * @param shopProduct $entry
         * @return array[string][string]string $return[%plugin_id%]['title_suffix'] html output
         * @return array[string][string]string $return[%plugin_id%]['action_button'] html output
         * @return array[string][string]string $return[%plugin_id%]['toolbar_section'] html output
         * @return array[string][string]string $return[%plugin_id%]['image_li'] html output
         */
        $this->view->assign('backend_product', wa()->event('backend_product', $product));

        $category_model = new shopCategoryModel();
        $this->view->assign('categories', $category_model->getFullTree('id, name, depth', true));

        $this->view->assign('counters', $counters);
        $this->view->assign('product', $product);
        $this->view->assign('current_author', shopProductReviewsModel::getAuthorInfo(wa()->getUser()->getId()));
        $this->view->assign('reply_allowed', true);
        $this->view->assign('review_allowed', true);
        $this->view->assign('sidebar_counters', $sidebar_counters);
        $this->view->assign('lang', substr(wa()->getLocale(), 0, 2));
        $this->view->assign('frontend_url', $frontend_url);
        $this->view->assign('frontend_base_url', $fontend_base_url);

        // Selectable features
        $selectable_features = $this->getSelectableFeatures($product);

        $counts = array();
        foreach ($selectable_features as $f) {
            if ($f['count']) {
                $counts[] = $f['count'];
            }
        }
        $this->view->assign('features', $selectable_features);
        $this->view->assign('features_counts', $counts);

        #load product types
        $this->view->assign('product_types', $product_types);
    }

    /**
     * Get only multiple type features
     * @param shopProduct $product
     */
    protected function getSelectableFeatures(shopProduct $product)
    {
        $features_model = new shopFeatureModel();
        $features = $features_model->getMultipleSelectableFeaturesByType($product->type_id);

        // attach values
        $features = $features_model->getValues($features);

        $features_selectable_model = new shopProductFeaturesSelectableModel();
        $selected = array();
        foreach ($features_selectable_model->getByField('product_id', $product->id, true) as $item) {
            $selected[$item['feature_id']][$item['value_id']] = true;
        }
        foreach ($features as $code => $f) {
            $count = 0;
            foreach ($f['values'] as $v_id => $v) {
                $is_selected = isset($selected[$f['id']][$v_id]);
                $features[$code]['values'][$v_id] = array(
                    'name'     => (string) $v,
                    'selected' => $is_selected
                );
                if ($is_selected) {
                    $count += 1;
                }
            }
            $features[$code]['count'] = $count;
        }
        return $features;
    }

    protected function assignReportsData($product)
    {
        $order_model = new shopOrderModel();
        $sales_total = $order_model->getTotalSalesByProduct($product['id']);
        $this->view->assign('sales', $sales_total['total']);
        $profit = $sales_total['total'];

        $rows = $order_model->getSalesByProduct($product['id']);
        $date = strtotime(date('Y-m-d')." -30 day");
        $sales_data = array();
        $i = 0;
        while ($date < time()) {
            $date = date('Y-m-d', $date);
            $sales_data[] = array($i++, isset($rows[$date]) ? (float) $rows[$date] : 0);
            $date = strtotime($date." +1 day");
        }
        $this->view->assign('sales_plot_data', array($sales_data));

        if (count($product['skus']) > 1) {
            $sku_sales_data = array();
            $rows = $order_model->getTotalSkuSalesByProduct($product['id']);
            foreach ($rows as $sku_id => $v) {
                $sku_sales_data[] = array($product['skus'][$sku_id]['name'], (float) $v['total']);
                if (!(double) $product['skus'][$sku_id]['purchase_price']) {
                    $profit = false;
                } elseif ($profit) {
                    $profit -= $v['quantity'] * $product['skus'][$sku_id]['purchase_price'];
                }
            }
            $this->view->assign('sku_plot_data', array($sku_sales_data));
        } else {
            $sku_id = $product['sku_id'];
            if ($profit && (double) $product['skus'][$sku_id]['purchase_price']) {
                $profit -= $sales_total['quantity'] * $product['skus'][$sku_id]['purchase_price'];
            } else {
                $profit = false;
            }
        }

        if ($profit) {
            $this->view->assign('profit', $profit);
        }
    }

    protected function getCurrencies()
    {
        $model = new shopCurrencyModel();
        return $model->getCurrencies();
    }
}
