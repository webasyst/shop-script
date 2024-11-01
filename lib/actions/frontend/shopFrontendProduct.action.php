<?php

class shopFrontendProductAction extends shopFrontendAction
{
    /** @var shopProductReviewsModel */
    protected $reviews_model;

    public function __construct($params = null)
    {
        $this->reviews_model = new shopProductReviewsModel();
        parent::__construct($params);
    }

    public function getBreadcrumbs(shopProduct $product, $product_link = false)
    {
        $breadcrumbs = $product->getBreadcrumbs($product_link);
        $root_category_id = $breadcrumbs ? key($breadcrumbs) : null;
        if ($breadcrumbs) {
            $this->view->assign('breadcrumbs', $breadcrumbs);
        }
        $this->view->assign('root_category_id', $root_category_id);
    }

    protected function prepareProduct(shopProduct $product, $selected_sku_id = null)
    {
        if (func_num_args() == 1) {
            $selected_sku_id = waRequest::get('sku', null, 'int');
        }
        if ($selected_sku_id) {
            if (isset($product->skus[$selected_sku_id])) {
                $s = $product->skus[$selected_sku_id];
                $product['sku_id'] = $selected_sku_id;
                $product['price'] = $s['price'];
                $product['compare_price'] = $s['compare_price'];

                if ($s['image_id'] && isset($product->images[$s['image_id']])) {
                    $product['image_id'] = $s['image_id'];
                    $product['image_filename'] = $product->images[$s['image_id']]['filename'];
                    $product['ext'] = $product->images[$s['image_id']]['ext'];
                }
            }
        }

        $skus = $product->skus;
        foreach ($skus as $sku_id => $sku) {
            if (empty($sku['status'])) {
                unset($skus[$sku_id]);
                continue;
            }
            // Compare price should be greater than price
            if ($sku['compare_price'] && ($sku['price'] >= $sku['compare_price'])) {
                $skus[$sku_id]['compare_price'] = 0.0;
            }
            if ($sku['count'] !== null) {
                $skus[$sku_id]['count'] = shopFrac::formatQuantityWithMultiplicity($sku['count'], $product['order_multiplicity_factor']);
            }
            foreach ($sku['stock'] as $stock_id => $stock_value) {
                if ($stock_value !== null) {
                    $skus[$sku_id]['stock'][$stock_id] = shopFrac::formatQuantityWithMultiplicity($stock_value, $product['order_multiplicity_factor']);
                }
            }
            // Public virtual stock counts for each SKU
            if (!empty($skus[$sku_id]['stock'])) {
                $skus[$sku_id]['stock'] = shopHelper::fillVirtulStock($skus[$sku_id]['stock']);
            }
        }

        if (wa('shop')->getSetting('limit_main_stock')) {
            $public_stocks = [waRequest::param('stock_id')];
        } else {
            $public_stocks = waRequest::param('public_stocks');
            if (!is_array($public_stocks)) {
                $public_stocks = $this->getVisibleStocks();
            }
        }

        if (!empty($public_stocks) && !$this->getConfig()->getGeneralSettings('ignore_stock_count')) {
            $count = $this->countOfSelectedStocks($public_stocks, $skus);
            if ($count === 0.0) {
                $product->status = 0;
            }
        }

        foreach ($skus as $sku_id => $sku) {
            $order_count_min = !empty($sku['order_count_min']) ? $sku['order_count_min'] : $product['order_count_min'];
            foreach ($sku['stock'] as $stock_id => $stock_value) {
                if ($stock_value !== null) {
                    if ($stock_value < $order_count_min) {
                        $skus[$sku_id]['stock'][$stock_id] = 0.0;
                    }
                }
            }
        }
        $product->skus = $skus;

        if ($this->appSettings('limit_main_stock')) {
            $stock_id = waRequest::param('stock_id');
            if ($stock_id) {
                $skus = $product->skus;
                $_update_flag = false;
                foreach ($skus as $sku_id => $sku) {
                    if (isset($sku['stock'][$stock_id])) {
                        $skus[$sku_id]['count'] = shopFrac::formatQuantityWithMultiplicity($sku['stock'][$stock_id], $product['order_multiplicity_factor']);
                        $_update_flag = true;
                    }
                }
                if ($_update_flag) {
                    $product['skus'] = $skus;
                }
            }
        }

        if (!isset($product->skus[$product->sku_id])) {
            $_skus = $product->skus;
            $product->sku_id = $_skus ? key($_skus) : null;
        }
        if (!$product->skus) {
            $product->skus = array(
                null => array(
                    'name'          => '',
                    'sku'           => '',
                    'id'            => null,
                    'available'     => false,
                    'status'        => false,
                    'count'         => 0,
                    'price'         => null,
                    'compare_price' => null,
                    'stock'         => array(),
                ),
            );
        }

        if ($this->getConfig()->getOption('can_use_smarty') && $product->description) {
            $view = wa()->getView();
            $view->assign('product', $product);
            $product->description = $view->fetch('string:'.$product->description);
        }


        if ((float)$product->compare_price <= (float)$product->price) {
            $product->compare_price = 0;
        }

        $skus = $product->skus;
        foreach ($skus as $s_id => $s) {
            $skus[$s_id]['original_price'] = $s['price'];
            $skus[$s_id]['original_compare_price'] = $s['compare_price'];
        }
        $product['original_price'] = $product['price'];
        $product['original_compare_price'] = $product['compare_price'];
        $event_params = [
            'products' => [$product->id => &$product],
            'skus'     => &$skus
        ];
        wa('shop')->event('frontend_products', $event_params);
        $product['skus'] = $skus;

        $product->tags = array_map('htmlspecialchars', $product->tags);
    }

    /**
     * Gets all the stocks visible to the storefront.
     * If all stocks are visible, then the recalculation is not needed
     * @return array|null
     */
    protected function getVisibleStocks()
    {
        $all_stocks = shopHelper::getStocks();
        $visible_stocks = [];
        $is_all_visible = true;

        if ($all_stocks) {
            foreach ($all_stocks as $id => $stock) {
                if ($stock['public'] == 0) {
                    $is_all_visible = false;
                    continue;
                }

                //if it is virtual stock
                if (!wa_is_int($id) && is_array($stock['substocks'])) {
                    $visible_stocks = array_merge($visible_stocks, $stock['substocks']);
                    continue;
                }
                $visible_stocks[] = $id;
            }

        }

        if ($is_all_visible) {
            return null;
        }

        return array_unique($visible_stocks);
    }

    protected function assignFeaturesSelectable(shopProduct $product)
    {
        if ($product->sku_type != shopProductModel::SKU_TYPE_SELECTABLE) {
            return;
        }

        $product_features_model = new shopProductFeaturesModel();
        $sku_features = $product_features_model->getSkuFeatures($product->id);

        $sku_features_keys = array_keys($sku_features);
        $product_sku_keys = array_keys($product->skus);
        $hidden_skus = array_diff($sku_features_keys, $product_sku_keys);
        $features_selectable = $product->features_selectable;
        foreach ($hidden_skus as $hidden_sku_id) {
            foreach ($sku_features[$hidden_sku_id] as $id => $sku_id) {
                $match = false;
                foreach ($product_sku_keys as $visible_sku_id) {
                    foreach ($sku_features[$visible_sku_id] as $v_id => $v_sku_id) {
                        if ($id == $v_id && $sku_id == $v_sku_id) {
                            $match = true;
                        }
                    }
                }
                if ($match == false
                    && isset($features_selectable[$id]['values'][$sku_id])
                    && count($features_selectable[$id]['values']) > 1
                ) {
                    unset($features_selectable[$id]['values'][$sku_id]);
                }
            }
        }

        $this->view->assign('features_selectable', $features_selectable);

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
            $order_count_min = !empty($sku['order_count_min']) ? $sku['order_count_min'] : $product['order_count_min'];
            $sku_selectable[$sku_f] = array(
                'id'        => $sku_id,
                'price'     => (float)shop_currency($sku['price'], $product['currency'], null, false),
                'available' => $product->status && $sku['available'] && $sku['status'] &&
                    ($this->getConfig()->getGeneralSettings('ignore_stock_count') || $sku['count'] === null || $sku['count'] >= $order_count_min),
                'image_id'  => (int)$sku['image_id'],
            );
            if ($sku['compare_price']) {
                $sku_selectable[$sku_f]['compare_price'] = (float)shop_currency($sku['compare_price'], $product['currency'], null, false);
            }
        }
        $product['sku_features'] = ifset($sku_features[$product->sku_id], array());
        $this->view->assign('sku_features_selectable', $sku_selectable);
    }

    public function execute()
    {
        $this->setLayout(new shopFrontendLayout());
        if ($this->params) {
            $products = [$this->params];
        } else {
            $product_model = new shopProductModel();
            $products = $product_model->getByField('url', waRequest::param('product_url'), true);
        }

        if (!$products) {
            throw new waException(_w('Product not found.'), 404);
        }

        $type_ids = waRequest::param('type_id');
        if (empty($type_ids)) {
            $product = reset($products);
        } else {
            foreach ($products as $prod) {
                if (in_array($prod['type_id'], (array) $type_ids)) {
                    $product = $prod;
                    break;
                }
            }
            if (empty($product)) {
                throw new waException(_w('Product not found.'), 404);
            }
        }

        $product = new shopProduct($product, true);
        self::setInvisibleUnfilledSkus($product);
        if ($product['status'] < 0) {
            // do the redirect when product is in "hidden and not available" status
            self::handleHiddenAndNotAvailable($product);
        }

        $is_cart = waRequest::get('cart');
        if ($is_cart) {
            $this->setLayout(null);
        }

        $this->ensureCanonicalUrl($product);
        $this->prepareProduct($product);

        $_active_sku_id = waRequest::request("sku", null);
        if (!empty($_active_sku_id) && !empty($product["skus"][$_active_sku_id])) {
            $product["sku_id"] = $_active_sku_id;
        }

        $this->view->assign('product', $product);

        $this->assignFeaturesSelectable($product);
        if (!$is_cart) {
            $this->getBreadcrumbs($product);
        }

        // get services
        list($services, $skus_services) = $this->getServiceVars($product);
        $this->view->assign('sku_services', $skus_services);
        $this->view->assign('services', $services);

        $compare = waRequest::cookie('shop_compare', array(), waRequest::TYPE_ARRAY_INT);
        $this->view->assign('compare', in_array($product['id'], $compare) ? $compare : array());

        if (!$is_cart) {
            $this->view->assign('reviews', $this->getTopReviews($product['id']));
            $this->view->assign('rates', $this->reviews_model->getProductRates($product['id']));
            $this->view->assign('reviews_total_count', $this->getReviewsTotalCount($product['id']));

            $meta_fields = $this->getMetafields($product);

            wa()->getResponse()->setTitle($meta_fields['meta_title']);
            wa()->getResponse()->setMeta('keywords', $meta_fields['meta_keywords']);
            wa()->getResponse()->setMeta('description', $meta_fields['meta_description']);
            foreach ($meta_fields['og'] as $property => $content) {
                $content && wa()->getResponse()->setOGMeta($property, $content);
            }

            /** yes, override previous og:video url */
            if ($product['video_url']) {
                wa()->getResponse()->setOGMeta('og:video', $product['video_url']);
            }

            /** Prepare feature-related variables.
              * (Template vars are named like that for legacy reasons.) */
            list($product_features, $used_features, $skus_with_features) = $this->prepareFeatureVars($product);
            $product->skus = $skus_with_features;
            $this->view->assign('features', $product_features);
            $this->view->assign('sku_features', $used_features);
        }


        $this->view->assign('currency_info', $this->getCurrencyInfo());
        $this->view->assign('stocks', shopHelper::getStocks(true));

        $units = shopHelper::getUnits();
        $this->view->assign('units', $units);
        $this->view->assign('formatted_units', shopFrontendProductAction::formatUnits($units));
        $this->view->assign('fractional_config', shopFrac::getFractionalConfig());

        /**
         * @event frontend_product
         * @param shopProduct $product
         * @return array[string][string]string $return[%plugin_id%]['menu'] html output
         * @return array[string][string]string $return[%plugin_id%]['cart'] html output
         * @return array[string][string]string $return[%plugin_id%]['block_aux'] html output
         * @return array[string][string]string $return[%plugin_id%]['block'] html output
         */
        $this->view->assign('frontend_product', wa()->event('frontend_product', $product, array('menu', 'cart', 'block_aux', 'block')));

        // default title and meta fields
        if (!$is_cart && !empty($meta_fields)) {
            if (!wa()->getResponse()->getTitle()) {
                wa()->getResponse()->setTitle($meta_fields['meta_title']);
            }
            if (!wa()->getResponse()->getMeta('keywords')) {
                wa()->getResponse()->setMeta('keywords', $meta_fields['meta_keywords']);
            }
            if (!wa()->getResponse()->getMeta('description')) {
                wa()->getResponse()->setMeta('description', $meta_fields['meta_description']);
            }
        }

        $this->getResponse()->setCanonical();

        $this->setThemeTemplate($is_cart ? 'product.cart.html' : 'product.html');
    }

    /**
     * @param array $units
     * @return array
     */
    public static function formatUnits($units) {
        $result = [];

        foreach ($units as $_unit) {
            $_unit_id = (string)$_unit["id"];
            $short_name = (!empty($_unit["storefront_name"]) ? $_unit["storefront_name"] : $_unit["short_name"]);

            $result[$_unit_id] = [
                "id" => $_unit_id,
                "name"  => $_unit["name"],
                "name_short" => $short_name
            ];
        }

        return $result;
    }

    /**
     * @param shopProduct $product
     * @throws waException
     */
    protected function ensureCanonicalUrl($product)
    {
        $root_url = ltrim(wa()->getRootUrl(false, true), '/');

        $canonical_url = $product->getProductUrl(true, true, false);
        $canonical_url = ltrim(substr($canonical_url, strlen($root_url)), '/');

        $actual_url = explode('?', wa()->getConfig()->getRequestUrl(), 2);
        $actual_url = ltrim(urldecode($actual_url[0]), '/');

        if ($canonical_url != $actual_url) {
            $q = waRequest::server('QUERY_STRING');
            $this->redirect('/'.$canonical_url.($q ? '?'.$q : ''), 301);
        }
    }

    /**
     * When product SKUs are selected by features, we should hide all SKUs that do not have all those features set.
     *
     * @param shopProduct $product
     * @throws waException
     */
    protected static function setInvisibleUnfilledSkus(&$product)
    {
        $skus = $product->getSkus();
        $count_selectable_features = is_array($product->features_selectable) ? count($product->features_selectable) : 0;
        if (!$skus || !$count_selectable_features || $product['sku_type'] == shopProductModel::SKU_TYPE_FLAT) {
            return;
        }

        $product_features_model = new shopProductFeaturesModel();
        $ungrouped_sku_features = $product_features_model->select('sku_id, feature_value_id')->where(
            'product_id = i:id AND sku_id IS NOT NULL AND feature_id IN (i:feature_id)', [
                'id' => $product->id,
                'feature_id' => array_column($product->features_selectable, 'id')
            ]
        )->fetchAll();

        $sku_features = [];
        foreach ($ungrouped_sku_features as $fields) {
            if (!isset($sku_features[$fields['sku_id']])) {
                $sku_features[$fields['sku_id']] = [];
            }
            $sku_features[$fields['sku_id']][] = $fields['feature_value_id'];
        }

        $hidden_modifications = [];
        $count_visible_modifications = 0;
        foreach ($skus as $sku_id => $sku) {
            $sku_feature = ifset($sku_features, $sku_id, []);
            if ($sku['status'] != 0) {
                $count_visible_modifications++;
                if ($count_selectable_features != count($sku_feature)) {
                    $hidden_modifications[] = $sku_id;
                }
            }
        }

        if (!$hidden_modifications) {
            return;
        }

        // SKUs that do not have all the features set, we have to either hide completely or make unavailable for order.
        // A product must have at least one SKU visible. When no SKUs are left, we make them unavailable for order (available=0).
        // Otherwise, we hide SKUs (status=0).
        $product_skus_model = new shopProductSkusModel();
        if (count($hidden_modifications) == $count_visible_modifications) {
            $should_update = false;
            foreach ($hidden_modifications as $sku_id) {
                if (!empty($skus[$sku_id]['available'])) {
                    $skus[$sku_id]['available'] = 0;
                    $should_update = true;
                }
            }
            if ($should_update) {
                $product_skus_model->updateByField('id', $hidden_modifications, ['available' => 0]);
            }
        } else {
            $should_update = false;
            $main_sku = $product->sku_id;
            foreach ($hidden_modifications as $sku_id) {
                if (!empty($skus[$sku_id]['status'])) {
                    $skus[$sku_id]['status'] = 0;
                    $should_update = true;
                }
                if (!empty($skus[$sku_id]['status'])) {
                    // Main SKU of a product can not be set invisible.
                    // First available SKU will become main SKU in this case.
                    $main_sku = $sku_id;
                }
            }
            if ($should_update) {
                $product_skus_model->updateByField('id', $hidden_modifications, ['status' => 0]);
                if (empty($skus[$product->sku_id]['status'])) {
                    $product->setData('sku_id', $main_sku);
                    $product_model = new shopProductModel();
                    $product_model->updateById($product->getId(), ['sku_id' => $main_sku]);
                }
            }
        }

        $product->setData('skus', $skus);
    }

    /**
     * Fetch feature values from DB: product features and SKU features.
     * Fetch feature settings from DB.
     * Filter features, removing those not visible in frontend.
     * Prepare arrays of feature values for product and each SKU,
     * sorting them in proper order according to product type settings.
     *
     * @param shopProduct $product
     * @return array
     * @throws waException
     */
    protected function prepareFeatureVars($product)
    {
        /** All features (settings) used in product type. In order as set in type settings.
          * This includes private features at this point (not visible in frontend). */
        $feature_model = new shopFeatureModel();
        $all_features  = $feature_model->getByType($product->type_id, 'code');

        /** Feature values of all SKUs and of the product */
        $sku_feature_values     = $product->getSkuFeatures();
        $product_feature_values = $product->features;

        /** Figure out which feature values are manually added to the product */
        $all_feature_codes  = $all_features;
        $all_feature_codes += $product_feature_values;
        foreach($sku_feature_values as $values) {
            $all_feature_codes += $values;
        }
        $manually_added_feature_codes = array_keys(array_diff_key($all_feature_codes, $all_features));
        unset($all_feature_codes);

        /** Fetch feature settings for features not attached to product type
          * but still manually added to this product. Sort order for them is undefined,
          * so just add them at the end of the feature list. */
        if ($manually_added_feature_codes) {
            $all_features += $feature_model->getByCode($manually_added_feature_codes);
        }

        /** Hide features not available in frontend */
        foreach($all_features as $code => $feature) {
            if ($feature['status'] === 'private') {
                unset($all_features[$code]);
            }
        }

        /** Add array of feature values to each SKU, in proper order.
         * Remove values of features that are not visible in frontend.
         * Collect feature codes used in product or any SKU. */
        $skus_with_features = [];
        $used_feature_codes = [];
        foreach ($product->skus as $sku_id => $sku) {
            $sku['features'] = [];
            foreach($all_features as $code => $feature) {
                if (isset($sku_feature_values[$sku_id][$code])) {
                    $sku['features'][$code]    = $sku_feature_values[$sku_id][$code];
                    $used_feature_codes[$code] = $code;
                } elseif (
                    isset($product_feature_values[$code])
                    || $feature['type'] === 'divider'
                ) {
                    $sku['features'][$code]    = $product_feature_values[$code];
                    $used_feature_codes[$code] = $code;
                } elseif ($feature['type'] === shopFeatureModel::TYPE_DIVIDER) {
                    $sku['features'][$code]    = null;
                    $used_feature_codes[$code] = $code;
                }
            }
            $skus_with_features[$sku_id] = $sku;
        }

        /** Settings of features that are used in product. This does not necessarily contains all features used in SKUs. */
        $product_features = array_intersect_key($all_features, $product_feature_values);
        $this->deleteLastDividers($product_features);

        /** Settings of all features used in product or SKUs */
        $used_features = array_intersect_key($all_features, $used_feature_codes);
        $this->deleteLastDividers($used_features);

        return [$product_features, $used_features, $skus_with_features];
    }

    /**
     * @param array $features
     */
    protected function deleteLastDividers(&$features)
    {
        if (is_array($features)) {
            $keys = array_keys($features);
            foreach ($keys as $i => $key) {
                if (isset($features[$key]['type']) && $features[$key]['type'] == shopFeatureModel::TYPE_DIVIDER) {
                    $next_key = isset($keys[$i+1]) ? $keys[$i+1] : '';
                    if (strlen($next_key) && isset($features[$next_key]['type']) && $features[$next_key]['type'] == shopFeatureModel::TYPE_DIVIDER) {
                        unset($features[$key]);
                    } else {
                        $last_element = end($features);
                        if (isset($last_element['type']) && $last_element['type'] == shopFeatureModel::TYPE_DIVIDER) {
                            array_pop($features);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $public_stocks
     * @param $skus
     * @return int|null
     */
    protected function countOfSelectedStocks($public_stocks, $skus)
    {
        $count = null;
        foreach ($skus as $sku) {
            if (empty($sku['stock'])) {
                return null;
            }
            foreach ($sku['stock'] as $key => $count_stock) {
                if (in_array($key, $public_stocks)) {
                    if ($count_stock === null) {
                        return null;
                    }
                    $count += $count_stock;
                }
            }
        }

        return $count;
    }

    protected function getServiceVars($product)
    {
        $type_services_model = new shopTypeServicesModel();
        $type_service_ids = $type_services_model->getServiceIds($product['type_id']);

        // Fetch services
        $service_model = new shopServiceModel();
        $product_services_model = new shopProductServicesModel();

        $product_service_ids = $product_services_model->getServiceIds($product['id'], array(
            'ignore' => array(
                'status' => shopProductServicesModel::STATUS_FORBIDDEN
            )
        ));

        $services = array_merge($type_service_ids, $product_service_ids);
        $services = array_unique($services);

        $services = $service_model->getById($services);

        $need_round_services = wa()->getSetting('round_services');
        if ($need_round_services) {
            shopRounding::roundServices($services);
        }

        // Convert service.price from default currency to service.currency
        foreach ($services as &$s) {
            $s['price'] = shop_currency($s['price'], null, $s['currency'], false);
        }
        unset($s);

        $enable_by_type = array_fill_keys($type_service_ids, true);

        // Fetch service variants
        $variants_model = new shopServiceVariantsModel();
        $rows = $variants_model->getByField('service_id', array_keys($services), true);

        if ($need_round_services) {
            shopRounding::roundServiceVariants($rows, $services);
        }

        foreach ($rows as $row) {
            if (!$row['price']) {
                $row['price'] = $services[$row['service_id']]['price'];
            } elseif ($services[$row['service_id']]['variant_id'] == $row['id']) {
                $services[$row['service_id']]['price'] = $row['price'];
            }
            $row['status'] = !empty($enable_by_type[$row['service_id']]);
            $services[$row['service_id']]['variants'][$row['id']] = $row;
        }

        // Fetch service prices for specific products and skus
        $rows = $product_services_model->getByField('product_id', $product['id'], true);

        if ($need_round_services) {
            shopRounding::roundServiceVariants($rows, $services);
        } else {
            foreach ($services as &$service) {
                if ($service['currency'] !== '%') {
                    $service['unconverted_currency'] = $service['currency'];
                    $service['currency'] = wa('shop')->getConfig()->getCurrency(false);
                    $service['price'] = shop_currency($service['price'], $service['unconverted_currency'], null, false);
                    foreach ($service['variants'] as $key => $variant) {
                        $service['variants'][$key]['price'] = shop_currency($variant['price'], $service['unconverted_currency'], null, false);
                    }
                }
            }
            unset($service);
        }

        // re-define statuses of service variants for that product
        foreach ($rows as $row) {
            if (!$row['sku_id']) {
                $services[$row['service_id']]['variants'][$row['service_variant_id']]['status'] = $row['status'];
            }
        }

        // Remove disable service variants
        foreach ($services as $service_id => $service) {
            if (isset($service['variants'])) {
                foreach ($service['variants'] as $variant_id => $variant) {
                    if (!$variant['status']) {
                        unset($services[$service_id]['variants'][$variant_id]);
                    }
                }
            }
        }

        // sku_id => [service_id => price]
        $skus_services = array();
        foreach ($product['skus'] as $sku) {
            $skus_services[$sku['id']] = array();
        }

        foreach ($rows as $row) {
            if (!$row['sku_id']) {

                if ($row['status'] && $row['price'] !== null) {
                    // update price for service variant, when it is specified for this product
                    if (isset($services[$row['service_id']]) && $services[$row['service_id']]['currency'] !== '%') {
                        $services[$row['service_id']]['variants'][$row['service_variant_id']]['price'] = shop_currency($row['price'], $services[$row['service_id']]['unconverted_currency'], null, false);
                    } else {
                        $services[$row['service_id']]['variants'][$row['service_variant_id']]['price'] = $row['price'];
                    }
                    // !!! also set other keys related to price
                }
                if ($row['status'] == shopProductServicesModel::STATUS_DEFAULT) {
                    // default variant is different for this product
                    $services[$row['service_id']]['variant_id'] = $row['service_variant_id'];
                }
            } else {
                if (!$row['status']) {
                    $skus_services[$row['sku_id']][$row['service_id']][$row['service_variant_id']] = false;
                } else {
                    if ($row['price'] !== null && isset($services[$row['service_id']]) && $services[$row['service_id']]['currency'] !== '%') {
                        $skus_services[$row['sku_id']][$row['service_id']][$row['service_variant_id']] = shop_currency($row['price'], $services[$row['service_id']]['unconverted_currency'], null, false);
                    } else {
                        $skus_services[$row['sku_id']][$row['service_id']][$row['service_variant_id']] = $row['price'];
                    }
                }
            }
        }

        // Fill in gaps in $skus_services
        foreach ($skus_services as $sku_id => &$sku_services) {
            if (!isset($product['skus'][$sku_id])) {
                continue;
            }
            $sku_price = $product['skus'][$sku_id]['price'];
            foreach ($services as $service_id => $service) {
                if (isset($sku_services[$service_id])) {
                    if ($sku_services[$service_id]) {
                        foreach ($service['variants'] as $v) {
                            if (!isset($sku_services[$service_id][$v['id']]) || $sku_services[$service_id][$v['id']] === null) {
                                $sku_services[$service_id][$v['id']] = array(
                                    $v['name'],
                                    $this->getPrice($v['price'], $service['currency'], $sku_price, $product['currency']),
                                );
                            } elseif ($sku_services[$service_id][$v['id']]) {
                                $sku_services[$service_id][$v['id']] = array(
                                    $v['name'],
                                    $this->getPrice($sku_services[$service_id][$v['id']], $service['currency'], $sku_price, $product['currency']),
                                );
                            }
                        }
                    }
                } else {
                    foreach ($service['variants'] as $v) {
                        $sku_services[$service_id][$v['id']] = array(
                            $v['name'],
                            $this->getPrice($v['price'], $service['currency'], $sku_price, $product['currency']),
                        );
                    }
                }
            }
        }
        unset($sku_services);

        // disable service if all variants are disabled
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

        // Calculate prices for %-based services,
        // and disable variants selector when there's only one value available.
        foreach ($services as $s_id => &$s) {
            if (!$s['variants']) {
                unset($services[$s_id]);
                continue;
            }
            if ($s['currency'] == '%') {
                $item = array(
                    'price'    => $product['skus'][$product['sku_id']]['price'],
                    'currency' => $product['currency'],
                );
                shopProductServicesModel::workupItemServices($s, $item);
            }


            if (count($s['variants']) == 1) {
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

        uasort($services, array('shopServiceModel', 'sortServices'));

        return array($services, $skus_services);
    }

    protected function getCurrencyInfo()
    {
        $currency = waCurrency::getInfo($this->getConfig()->getCurrency(false));
        $locale = waLocale::getInfo(wa()->getLocale());
        return array(
            'code'          => $currency['code'],
            'sign'          => $currency['sign'],
            'sign_html'     => !empty($currency['sign_html']) ? $currency['sign_html'] : $currency['sign'],
            'sign_position' => isset($currency['sign_position']) ? $currency['sign_position'] : 1,
            'sign_delim'    => isset($currency['sign_delim']) ? $currency['sign_delim'] : ' ',
            'decimal_point' => $locale['decimal_point'],
            'frac_digits'   => $locale['frac_digits'],
            'thousands_sep' => $locale['thousands_sep'],
        );
    }

    protected function getPrice($price, $currency, $product_price, $product_currency)
    {
        if ($currency == '%') {
            $round_services = wa()->getSetting('round_services');
            if ($round_services) {
                return shopRounding::roundCurrency($price * $product_price / 100, $product_currency);
            } else {
                return shop_currency($price * $product_price / 100, $product_currency, null, 0);
            }
        } else {
            return shop_currency($price, $currency, null, 0);
        }
    }

    protected function getReviewsTotalCount($product_id)
    {
        return $this->reviews_model->count($product_id, true);
    }

    protected function getTopReviews($product_id)
    {
        return $this->reviews_model->getReviews(
            $product_id,
            0,
            wa()->getConfig()->getOption('reviews_per_page_product'),
            'datetime DESC',
            array('escape' => true)
        );
    }

    protected function getMetafields($product)
    {
        $search = array('{$name}', '{$price}', '{$summary}');
        $replace = array();
        foreach ($search as $i => $s) {
            $r = substr($s, 2, -1);
            if (isset($product[$r])) {
                if ($r == 'price') {
                    $replace[] = shop_currency_html($product[$r], null, null, true);
                } else {
                    $replace[] = $product[$r];
                }
            } else {
                unset($search[$i]);
            }
        }
        $res = array();
        foreach (array('meta_title', 'meta_keywords', 'meta_description') as $f) {
            if (isset($product[$f])) {
                $res[$f] = str_replace($search, $replace, (string) $product[$f]);
            }
        }

        $res['meta_title'] = ifempty($res['meta_title'], shopProduct::getDefaultMetaTitle($product));
        $res['meta_keywords'] = ifempty($res['meta_keywords'], shopProduct::getDefaultMetaKeywords($product));
        $res['meta_description'] = ifempty($res['meta_description'], shopProduct::getDefaultMetaDescription($product));

        /** @var waViewHelper $shop */
        $helper = $this->view->getHelper();
        /** @var shopViewHelper $shop */
        $shop = $helper->shop;

        $image_url = $shop->imgUrl(array(
            'id'         => $product['image_id'],
            'product_id' => $product['id'],
            'filename'   => $product['image_filename'],
            'ext'        => $product['ext'],
        ), null, true);

        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();

        $res['og'] = array(
            'og:type'                => 'website',
            'og:title'               => $res['meta_title'],
            'og:description'         => $res['meta_description'],
            'og:image'               => $image_url,
            'og:url'                 => $config->getHostUrl().$config->getRequestUrl(false, true),
            'product:price:amount'   => shop_currency($product['price'], null, null, false),
            'product:price:currency' => $config->getCurrency(false),
        );
        foreach ($product['og'] as $k => $v) {
            $res['og']['og:'.$k] = $v;
        }
        return $res;
    }

    public static function handleHiddenAndNotAvailable($product)
    {
        $redirect_code = ifset($product, 'params', 'redirect_code', null);
        if (!$redirect_code) {
            throw new waException(_w('Product not found.'), 404);
        }

        $redirect_category_id = ifset($product, 'params', 'redirect_category_id', null);
        if ($redirect_category_id) {
            $category_model = new shopCategoryModel();
            $category = $category_model->getById($redirect_category_id);
            if (!$category) {
                throw new waException(_w('Product not found.'), 404);
            }
            if (waRequest::param('url_type', 1) == 1) {
                $category_url_part = $category['url'];
            } else {
                $category_url_part = $category['full_url'];
            }
            $redirect_url = wa()->getRouteUrl('shop/frontend/category', array('category_url' => $category_url_part));
        } else {
            $redirect_url = ifset($product, 'params', 'redirect_url', null);
        }
        if (!$redirect_url) {
            // shop home page
            $redirect_url = wa()->getRouteUrl('shop/frontend/');
        }
        wa()->getResponse()->redirect($redirect_url, $redirect_code == 301 ? 301 : 302);
    }
}
