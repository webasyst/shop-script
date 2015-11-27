<?php

class shopProductAction extends waViewAction
{
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
            'reviews'  => 0,
            'images'   => 0,
            'pages'    => 0,
            'services' => 0
        );
        $sidebar_counters = array();
        $config = $this->getConfig();
        /**
         * @var shopConfig $config
         */

        #load product types
        $type_model = new shopTypeModel();

        $product_types = $type_model->getTypes(true);
        $product_types_count = count($product_types);

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

            $product_stocks_log_model = new shopProductStocksLogModel();
            $counters['stocks_log'] = $product_stocks_log_model->countByField('product_id', $product->id);

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
                    $product->type_id = wa()->getUser()->getSettings('shop', 'last_type_id', key($product_types));
                }
            } elseif (!$product->checkRights()) {
                throw new waRightsException(_w("Access denied"));
            }
            $this->view->assign('edit_rights', true);

            $product['skus'] = array(
                '-1' => array(
                    'id'             => -1,
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

        $this->view->assign(
            array(
                'use_product_currency' => wa()->getSetting('use_product_currency'),
                'currencies'           => $this->getCurrencies(),
                'primary_currency'     => $config->getCurrency(),
                'taxes'                => $taxes_mode->getAll(),
            )
        );

        $category_model = new shopCategoryModel();
        $categories = $category_model->getFullTree('id, name, depth, url, full_url, parent_id', true);
        $frontend_urls = array();

        if (intval($product->id)) {
            $routing = wa()->getRouting();
            $domain_routes = $routing->getByApp($this->getAppId());
            foreach ($domain_routes as $domain => $routes) {
                foreach ($routes as $r) {
                    if (!empty($r['private'])) {
                        continue;
                    }
                    if (empty($r['type_id']) || (in_array($product->type_id, (array)$r['type_id']))) {
                        $routing->setRoute($r, $domain);
                        $params = array('product_url' => $product->url);
                        if ($product->category_id && isset($categories[$product->category_id])) {
                            if (!empty($r['url_type']) && $r['url_type'] == 1) {
                                $params['category_url'] = $categories[$product->category_id]['url'];
                            } else {
                                $params['category_url'] = $categories[$product->category_id]['full_url'];
                            }
                        }
                        $frontend_url = $routing->getUrl('/frontend/product', $params, true);
                        $frontend_urls[] = array(
                            'url' => $frontend_url
                        );
                    }
                }
            }
        } else {
            $frontend_urls[] = array(
                'url' => wa()->getRouteUrl('/frontend/product', array('product_url' => '%product_url%'), true),
            );
        }

        $stuff = intval($product->id) ? $product->url : '%product_url%';
        foreach ($frontend_urls as &$frontend_url) {
            $pos = strrpos($frontend_url['url'], $stuff);
            $frontend_url['base'] = $pos !== false ? rtrim(substr($frontend_url['url'], 0, $pos), '/').'/' : $frontend_url['url'];
        }
        unset($frontend_url);

        $product_model = new shopProductModel();
        $this->view->assign('storefront_map', $product_model->getStorefrontMap($product->id));

        /**
         * Backend product profile page
         * UI hook allow extends product profile page
         * @event backend_product
         * @param shopProduct $entry
         * @return array[string][string]string $return[%plugin_id%]['title_suffix'] html output
         * @return array[string][string]string $return[%plugin_id%]['action_button'] html output
         * @return array[string][string]string $return[%plugin_id%]['toolbar_section'] html output
         * @return array[string][string]string $return[%plugin_id%]['info_section'] html output*
         * @return array[string][string]string $return[%plugin_id%]['edit_section_li'] html output
         * @return array[string][string]string $return[%plugin_id%]['category_action_li'] html output
         */
        $this->view->assign('backend_product', wa()->event('backend_product', $product, array()));

        /**
         * @event backend_product_edit
         */
        $this->view->assign('backend_product_edit', wa()->event('backend_product_edit', $product));

        $this->view->assign('categories', $categories);

        $this->view->assign('counters', $counters);
        $this->view->assign('product', $product);
        $this->view->assign('current_author', shopProductReviewsModel::getAuthorInfo(wa()->getUser()->getId()));
        $this->view->assign('reply_allowed', true);
        $this->view->assign('review_allowed', true);
        $this->view->assign('sidebar_counters', $sidebar_counters);
        $this->view->assign('lang', substr(wa()->getLocale(), 0, 2));
        $this->view->assign('frontend_urls', $frontend_urls);

        $tag_model = new shopTagModel();
        $this->view->assign('popular_tags', $tag_model->popularTags());

        $counts = array();
        // Selectable features
        $features_selectable = $product->features_selectable;
        if (is_array($features_selectable)) {
            foreach ($features_selectable as $f) {
                if ($f['selected']) {
                    $counts[] = $f['selected'];
                }
            }
        }

        $feature_model = new shopTypeFeaturesModel();
        $features_selectable_types = $feature_model->getSkuTypeSelectableTypes();
        foreach ($product_types as $type_id => &$type) {
            $type['sku_type'] = empty($features_selectable_types[$type_id]) ? shopProductModel::SKU_TYPE_FLAT : shopProductModel::SKU_TYPE_SELECTABLE;
        }

        $this->view->assign('features', $features_selectable);
        $this->view->assign('features_counts', $counts);

        #load product types
        $this->view->assign('product_types', $product_types);

        $this->view->assign('sidebar_width', $config->getSidebarWidth());

        $stocks_log_model = new shopProductStocksLogModel();
        $stocks_log = $stocks_log_model->getList(
            '*,stock_name,sku_name,product_name',
            array(
                'where' => array('product_id' => $product->id),
                'limit' => 5,
                'order' => 'datetime DESC'
            )
        );
        $this->view->assign('stocks_log', $stocks_log);

        $sm = new shopSetModel();
        $sets = array();
        foreach($sm->getAll() as $row) {
            if (!$row['type']) {
                $sets[$row['id']] = $row;
            }
        }
        $this->view->assign('sets', $sets);

        $spm = new shopSetProductsModel();
        $this->view->assign('product_sets', $spm->getByProduct($product->id));

        $this->view->assign('category_name', $product->category_id && isset($product->categories[$product->category_id]) ? strip_tags($product->categories[$product->category_id]['name']) : null);

    }

    protected function assignReportsData(shopProduct $product)
    {
        $report_rights = $this->getRights('reports');
        if ($report_rights) {
            $this->view->assign('sales', $this->getSales($product));
            $this->view->assign('forecast', $product->getNextForecast());
            $this->view->assign('sales_data', $this->getSalesData($product, date("Y-m-d", strtotime(date('Y-m-d')." -30 day"))));

            if ($product->skus > 1) {
                $sku_sales_data = array();
                $order_model = new shopOrderModel();
                $rows = $order_model->getTotalSkuSalesByProduct($product['id'], $product['currency']);
                foreach ($rows as $sku_id => $v) {
                    $name = 'sku_id='.$sku_id;
                    if (!empty($product['skus'][$sku_id]['name'])) {
                        $name = $product['skus'][$sku_id]['name'];
                    }
                    $sku_sales_data[] = array($name, (float)$v['total']);
                }
                $this->view->assign('sku_plot_data', array($sku_sales_data));
            }
        }
        $this->view->assign('report_rights', $report_rights);

    }

    protected function getSalesData($product, $start_date)
    {
        $order_model = new shopOrderModel();
        $sales_by_day = $order_model->getSalesByProduct($product['id'], $start_date);

        // Prepare main chart data for template
        $graph_data = array();

        $i = 0;
        $date = strtotime($start_date);
        while ($date <= time()) {
            $date = date('Y-m-d', $date);
            if (empty($sales_by_day[$date])) {
                $item = array(
                    'date' => str_replace('-', '', $date), 'sales' => 0, 'profit' => 0, 'loss' => 0,
                );
            } else {
                $d = $sales_by_day[$date];
                $item = array(
                    'date' => str_replace('-', '', $date),
                    'sales' => $d['sales'],
                    'profit' => $d['sales'] - $d['purchase'],
                    'loss' => $d['sales'] - $d['purchase'],
                );
            }
            $graph_data[] = $item;
            $date = strtotime($date." +1 day");
        }

        return $graph_data;
    }

    public function getSales($product)
    {
        $order_model = new shopOrderModel();
        $sales_total = $order_model->getTotalSalesByProduct($product['id'], $product['currency']);
        $sales_total['profit'] = $sales_total['total'] - $sales_total['purchase'];
        return $sales_total;
    }

    protected function getCurrencies()
    {
        $model = new shopCurrencyModel();
        return $model->getCurrencies();
    }
}
