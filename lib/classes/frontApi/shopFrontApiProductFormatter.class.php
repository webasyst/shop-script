<?php
/*
 * Formatter for fontend API. Takes data from ProductsCollection and prepares into strict API format.
 */
class shopFrontApiProductFormatter extends shopFrontApiFormatter
{
    public $fields;
    public $options;
    protected $product_fields = null;

    public function __construct(array $options=[])
    {
        parent::__construct($options);
        $this->setFields(ifset($options, 'fields', []));

        if (empty($this->options['public_stocks']) || !is_array($this->options['public_stocks'])) {
            if (wa('shop')->getSetting('limit_main_stock') && waRequest::param('stock_id')) {
                $this->options['public_stocks'] = [waRequest::param('stock_id')];
            } else {
                $this->options['public_stocks'] = waRequest::param('public_stocks');
            }
        }
        if (empty($this->options['public_stocks']) || !is_array($this->options['public_stocks'])) {
            $this->options['public_stocks'] = $this->getVisibleStocks();
        }
        if (!array_key_exists('ignore_stock_count', $this->options)) {
            $this->options['ignore_stock_count'] = wa('shop')->getConfig()->getGeneralSettings('ignore_stock_count');
        }
    }

    public function setFields(array $fields)
    {
        if (!$fields) {
            $fields = ['*'];
        }
        $this->fields = array_map('trim', $fields);
        return $this;
    }

    protected function getProductFields()
    {
        if ($this->product_fields === null) {
            $default_fields = 'id,name,summary,status,type_id,image_id,image_filename,ext,video_url,sku_id,url,rating,currency,count,count_denominator,order_multiplicity_factor,order_count_min,order_count_step,stock_unit_id,base_unit_id,stock_base_ratio,rating_count,category_id,badge,sku_type,sku_count,sku_filtered';
            $default_fields = array_fill_keys(explode(',', $default_fields), true);
            $allowed_fields = 'description,meta_title,meta_keywords,meta_description,images,images2x,image,image_crop_small,image_count,skus,skus_filtered,stock_counts,skus_image,frontend_url,reviews_count,categories,category_ids,tags,tag_ids,features,sku_features,sku_selection,services';
            $allowed_fields = array_fill_keys(explode(',', $allowed_fields), true);

            $fields = array_fill_keys($this->fields, true);
            if (isset($fields['*'])) {
                $fields += $default_fields;
            }
            //if (isset($fields['!!'])) {
            //    $fields += $allowed_fields;
            //}

            $fields = array_intersect_key($fields, $default_fields + $allowed_fields);
            foreach ($this->fields as $f) {
                if (substr($f, 0, 8) === 'feature_') {
                    $fields[$f] = true;
                }
            }
            $this->product_fields = $fields;
        }

        return $this->product_fields;
    }

    public function getCollectionFields()
    {
        return join(',', array_keys($this->getProductFields()));
    }

    public function removeUnwantedFields($product)
    {
        $fields = $this->getProductFields();
        if (isset($fields['skus_filtered']) || isset($fields['sku_filtered'])) {
            unset($fields['skus_filtered'], $fields['sku_filtered']);
            $fields['skus'] = true;
        }
        return array_intersect_key($product, $fields);
    }

    public function format(array $products)
    {
        $unset_fields = array_fill_keys(explode(',', 'unconverted_currency,unconverted_price,frontend_price,unconverted_compare_price,frontend_compare_price,rating_html,frontend_url,original_price,original_compare_price'), true);

        $result = [];
        foreach ($products as $p) {
            $p = array_diff_key($p, $unset_fields);
            //$p = self::formatPriceField($p, ['price', 'base_price', 'compare_price'], $p['currency']);
            $p = self::formatFieldsToType($p, [
                "id" => "integer",
                "status" => "integer",
                "type_id" => "integer",
                "image_id" => "integer",
                "sku_id" => "integer",
                "rating" => "integer",
                //"price" => "number",
                //"base_price" => "number",
                //"compare_price" => "number",
                "count" => "integer",
                "count_denominator" => "integer",
                "order_multiplicity_factor" => "number",
                "order_count_min" => "number",
                "order_count_step" => "number",
                "stock_unit_id" => "integer",
                "base_unit_id" => "integer",
                "stock_base_ratio" => "number",
                "rating_count" => "integer",
                "category_id" => "integer",
                "sku_type" => "integer",
                "sku_count" => "integer",
                "sku_selection" => "object",
                "images" => "array",
                "image" => "object",
                "image_count" => "integer",
                "skus" => "array",
                'category_ids' => 'array',
                'reviews_count' => 'integer',
                'features' => 'array',
                'services' => 'object',
                'tags' => [
                    '_multiple' => true,
                    '_type' => 'string',
                ],
                'tag_ids' => [
                    '_multiple' => true,
                    '_type' => 'integer',
                ],
            ]);
            $p = $this->removeUnwantedFields($p);

            if (!empty($p['skus'])) {
                if (!empty($this->options['public_stocks'])) {
                    $public_stocks_assoc = array_fill_keys($this->options['public_stocks'], 1);
                }
                foreach ($p['skus'] as $i => &$s) {
                    if (empty($s['status'])) {
                        unset($p['skus'][$i]);
                        continue;
                    }
                    if (!empty($sku['stock'])) {
                        $order_count_min = !empty($sku['order_count_min']) ? $sku['order_count_min'] : $p['order_count_min'];
                        foreach ($sku['stock'] as $stock_id => &$stock_value) {
                            if ($stock_value !== null) {
                                $stock_value = shopFrac::formatQuantityWithMultiplicity($stock_value, $p['order_multiplicity_factor']);
                                if ($stock_value < $order_count_min) {
                                    $stock_value = 0.0;
                                }
                            }
                        }
                        unset($stock_value);
                        $sku['stock'] = shopHelper::fillVirtulStock($sku['stock']);
                        if (!empty($public_stocks_assoc)) {
                            $sku['stock'] = array_intersect_key($sku['stock'], $public_stocks_assoc);
                        }
                    }
                    if (!empty($this->options['public_stocks'])) {
                        $s['count'] = $this->skuCountOfSelectedStocks($this->options['public_stocks'], $s);
                    }
                    $s = $this->getSkuFormatter()->format($s, $p);

                    if (!empty($s['features'])) {
                        foreach ($s['features'] as &$_f) {
                            $_f = $this->getFatureFormatter()->format($_f);
                        }
                        unset($_f);
                        $s['features'] = array_values($s['features']);
                    }
                    if (!empty($s['services'])) {
                        $s_services = [];
                        $_formatter = $this->getServiceFormatter();
                        foreach ($s['services'] as $_ser_id => $_sku_service) {
                            foreach ($_sku_service as $_variant_id => $_sku_variant) {
                                $s_services[] = [
                                    'service_id' => $_ser_id,
                                    'variant_id' => $_variant_id,
                                    'currency' => $p['currency'],
                                ] + $_sku_variant;
                            }
                        }
                        $s['services'] = array_map([$_formatter, 'skuService'], $s_services);
                    }
                }
                unset($s);
                if (!empty($this->options['public_stocks']) && array_key_exists('count', $p)) {
                    $p['count'] = $this->countOfSelectedStocks($this->options['public_stocks'], $p['skus']);
                    if ($p['count'] === 0.0 && !empty($p['status']) && !$this->options['ignore_stock_count']) {
                        $p['status'] = 0;
                    }
                }
                $p['skus'] = array_values($p['skus']);
            }
            if (!empty($p['images'])) {
                foreach ($p['images'] as &$img) {
                    $img = $this->getImageFormatter()->format($img);
                }
                unset($img);
                $p['images'] = array_values($p['images']);
            }
            if (!empty($p['category_ids'])) {
                $p['category_ids'] = array_map('intval', $p['category_ids']);
            }
            if (!empty($p['categories'])) {
                foreach ($p['categories'] as &$c) {
                    $c = $this->getCategoryFormatter()->format($c);
                }
                unset($c);
                $p['categories'] = array_values($p['categories']);
            }
            if (!empty($p['features'])) {
                foreach ($p['features'] as $key => &$_f) {
                    if ($_f['status'] === shopFeatureModel::STATUS_PRIVATE) {
                        unset($p['features'][$key]);
                    } else {
                        $_f = $this->getFatureFormatter()->format($_f);
                    }
                }
                unset($_f);
                $p['features'] = array_values($p['features']);
            }

            if (!empty($p['services'])) {
                $_formatter = $this->getServiceFormatter();
                foreach ($p['services'] as &$_service) {
                    if (!empty($_service['variants'])) {
                        foreach ($_service['variants'] as &$_sku_variant) {
                            $_sku_variant = $_formatter->formatVariant($_sku_variant + ['currency' => $_service['currency']]);
                        }
                        unset($_sku_variant);
                    }
                    $_service = $_formatter->formatService($_service);
                }
                unset($_service);
                $p['services'] = array_values($p['services']);
            }

            if (!empty($p['sku_selection'])) {
                foreach ($p['sku_selection']['features_selectable'] as &$_f) {
                    $_f = $this->getFatureFormatter()->format($_f);
                }
                foreach ($p['sku_selection']['sku_features_selectable'] as &$_f) {
                    $_f = $this->getFatureFormatter()->formatSelectable($_f);
                }
                unset($_f);

                $p['sku_selection']['features_selectable'] = array_values($p['sku_selection']['features_selectable']);
            }

            $result[$p['id']] = $p;
        }
        return $result;
    }

    protected function getSkuFormatter()
    {
        return new shopFrontApiProductSkuFormatter();
    }

    protected function getImageFormatter()
    {
        return new shopFrontApiProductImageFormatter();
    }

    protected function getCategoryFormatter()
    {
        return new shopFrontApiCategoryFormatter([
            'without_meta' => true,
        ]);
    }

    protected function getFatureFormatter()
    {
        return new shopFrontApiFeatureFormatter();
    }

    protected function getServiceFormatter()
    {
        return new shopFrontApiServiceFormatter();
    }

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

    protected function countOfSelectedStocks($public_stocks, $skus)
    {
        $count = null;
        foreach ($skus as $sku) {
            $sku_count = $this->skuCountOfSelectedStocks($public_stocks, $sku);
            if ($sku_count === null) {
                return null;
            }
        }

        return $count;
    }

    protected function skuCountOfSelectedStocks($public_stocks, $sku)
    {
        if (empty($sku['stock'])) {
            return null;
        }
        $count = null;
        foreach ($sku['stock'] as $key => $count_stock) {
            if (in_array($key, $public_stocks)) {
                if ($count_stock === null) {
                    return null;
                }
                $count = ifset($count, 0) + $count_stock;
            }
        }

        return $count;
    }

}
