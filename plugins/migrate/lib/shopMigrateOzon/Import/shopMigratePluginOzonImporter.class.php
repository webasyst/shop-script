<?php

class shopMigratePluginOzonImporter
{
    private $repository;
    private $settings;
    private $type_mapper;
    private $category_mapper;
    private $stock_mapper;
    private $feature_mapper;

    private $product_model;
    private $product_skus_model;
    private $category_products_model;
    private $product_stocks_model;
    private $product_map_model;
    private $product_features_model;
    private $tag_model;
    private $product_tags_model;
    private $product_images_model;
    private $feature_model;
    private $temp_image_dir;
    private $image_cache = array();
    private $product_default_sku = array();

    public function __construct(
        shopMigratePluginOzonSnapshotRepository $repository,
        shopMigratePluginOzonSettings $settings,
        shopMigratePluginOzonTypeMapper $type_mapper,
        shopMigratePluginOzonCategoryMapper $category_mapper,
        shopMigratePluginOzonStockMapper $stock_mapper,
        shopMigratePluginOzonFeatureMapper $feature_mapper
    ) {
        $this->repository = $repository;
        $this->settings = $settings;
        $this->type_mapper = $type_mapper;
        $this->category_mapper = $category_mapper;
        $this->stock_mapper = $stock_mapper;
        $this->feature_mapper = $feature_mapper;

        $this->product_model = new shopProductModel();
        $this->product_skus_model = new shopProductSkusModel();
        $this->category_products_model = new shopCategoryProductsModel();
        $this->product_stocks_model = new shopProductStocksModel();
        $this->product_map_model = $repository->getProductMapModel();
        $this->product_features_model = new shopProductFeaturesModel();
        $this->tag_model = new shopTagModel();
        $this->product_tags_model = new shopProductTagsModel();
        $this->product_images_model = new shopProductImagesModel();
        $this->feature_model = new shopFeatureModel();
        $this->temp_image_dir = rtrim(wa()->getTempPath('plugins/migrate/ozon/', 'shop'), '/\\').DIRECTORY_SEPARATOR;
    }

    public function import($snapshot_id)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $snapshot = $this->repository->getSnapshotsModel()->getByIdSafe($snapshot_id);
        if (!$snapshot || $snapshot['status'] !== 'ready') {
            throw new waException('Snapshot is not ready for import');
        }

        $products = $this->repository->getProductsModel()->getAllBySnapshot($snapshot_id);
        if (!$products) {
            return array('created' => 0, 'updated' => 0, 'skipped' => 0);
        }

        $attribute_values = $this->groupValuesByProduct($snapshot_id, array_keys($products));
        $stocks = $this->groupStocksByOffer($snapshot_id);

        $this->type_mapper->warmup($snapshot_id);
        $this->category_mapper->warmup($snapshot_id);
        $this->stock_mapper->warmup($snapshot_id);
        $this->feature_mapper->warmup($snapshot_id);

        $result = array('created' => 0, 'updated' => 0, 'skipped' => 0);

        list($grouped_products, $single_products) = $this->partitionProductsByModelInfo($products);

        foreach ($grouped_products as $group) {
            try {
                $is_new = $this->importGroupedProduct($snapshot_id, $group, $attribute_values, $stocks);
                if ($is_new === null) {
                    $result['skipped']++;
                } elseif ($is_new) {
                    $result['created']++;
                } else {
                    $result['updated']++;
                }
            } catch (Exception $e) {
                waLog::log('[OzonImporter] '.$e->getMessage(), shopMigratePluginOzonLogger::LOG_FILE);
                $result['skipped']++;
            }
        }

        foreach ($single_products as $item) {
            $product = $item['product'];
            try {
                $is_new = $this->importProduct($snapshot_id, $product, $attribute_values, $stocks);
                if ($is_new === null) {
                    $result['skipped']++;
                } elseif ($is_new) {
                    $result['created']++;
                } else {
                    $result['updated']++;
                }
            } catch (Exception $e) {
                waLog::log('[OzonImporter] '.$e->getMessage(), shopMigratePluginOzonLogger::LOG_FILE);
                $result['skipped']++;
            }
        }

        return $result;
    }

    private function partitionProductsByModelInfo(array $products)
    {
        $grouped = array();
        $singles = array();
        foreach ($products as $product_id => $product) {
            $details = $product['details'] ? json_decode($product['details'], true) : array();
            $model_info = ifset($details['model_info'], array());
            $model_id = (int) ifset($model_info['model_id'], 0);
            $model_count = (int) ifset($model_info['count'], 0);
            if ($model_id > 0 && $model_count > 1) {
                if (!isset($grouped[$model_id])) {
                    $grouped[$model_id] = array(
                        'model_id' => $model_id,
                        'items'    => array(),
                    );
                }
                $grouped[$model_id]['items'][$product_id] = array(
                    'product' => $product,
                    'details' => $details,
                );
            } else {
                $singles[$product_id] = array('product' => $product);
            }
        }
        $result_groups = array();
        foreach ($grouped as $group) {
            if (count($group['items']) < 2) {
                foreach ($group['items'] as $product_id => $item) {
                    $singles[$product_id] = array('product' => $item['product']);
                }
                continue;
            }
            $group['base_product_id'] = min(array_keys($group['items']));
            $result_groups[] = $group;
        }

        return array($result_groups, $singles);
    }

    private function importProduct($snapshot_id, array $product, array $attribute_values, array $stocks_by_offer)
    {
        $type_id = $this->type_mapper->resolve(
            $snapshot_id,
            ifset($product['description_category_id']),
            ifset($product['type_id'])
        );
        $category_id = $this->category_mapper->resolve($snapshot_id, ifset($product['description_category_id']));
        if (!$type_id || !$category_id) {
            return null;
        }

        $existing_map = null;
        if (!empty($product['offer_id'])) {
            $existing_map = $this->product_map_model->getByOffer($product['offer_id']);
        }

        $details = $product['details'] ? json_decode($product['details'], true) : array();
        $product_data = $this->buildProductData($product, $details, $type_id, $category_id);

        if ($existing_map) {
            $product_id = (int) $existing_map['shop_product_id'];
            $product_data['edit_datetime'] = date('Y-m-d H:i:s');
            unset($product_data['create_datetime']);
            $this->product_model->updateById($product_id, $product_data);
        } else {
            $product_data['url'] = shopHelper::genUniqueUrl(
                shopHelper::transliterate($product_data['name']),
                $this->product_model
            );
            $product_data['create_datetime'] = date('Y-m-d H:i:s');
            $product_id = $this->product_model->insert($product_data);
        }

        $this->assignCategory($product_id, $category_id);

        $sku_id = $this->ensureSku($product_id, $product['offer_id'], $product, $details);
        $this->ensureDefaultSkuAssigned($product_id, $sku_id);

        if ($this->settings->getFeatureImportMode() === shopMigratePluginOzonSettings::FEATURE_MODE_AUTO) {
            $product_attributes = ifset($attribute_values[$product['product_id']], array());
            $this->assignFeatures($snapshot_id, $product_id, $type_id, $attribute_values, $product['product_id']);
            $this->assignCollectedTags($product_id, $product_attributes);
        }

        $this->assignStocks($snapshot_id, $product_id, $sku_id, $product['offer_id'], $stocks_by_offer);

        if (!empty($product['offer_id'])) {
            $this->product_map_model->linkOffer($product['offer_id'], $product_id, $sku_id, $product['product_id']);
        }

        $this->finalizeProductCounters($product_id);

        $primary_image_url = $this->resolvePrimaryImageUrl($details);
        $image_map = $this->synchronizeProductImages(
            $product_id,
            $primary_image_url ? array($primary_image_url) : array(),
            $this->collectImageUrls($details)
        );
        $sku_image_id = $this->getImageIdFromMap($product_id, $primary_image_url, $image_map);
        $this->product_skus_model->updateById($sku_id, array('image_id' => $sku_image_id));

        return $existing_map ? false : true;
    }

    private function findExistingMapInGroup(array $items)
    {
        foreach ($items as $item) {
            $product = $item['product'];
            if (empty($product['offer_id'])) {
                continue;
            }
            $existing = $this->product_map_model->getByOffer($product['offer_id']);
            if ($existing) {
                return $existing;
            }
        }
        return null;
    }

    private function importGroupedProduct($snapshot_id, array $group, array $attribute_values, array $stocks_by_offer)
    {
        if (empty($group['items'])) {
            return null;
        }
        $items = $group['items'];
        $product_ids = array_keys($items);
        $base_product_id = isset($group['base_product_id']) ? $group['base_product_id'] : reset($product_ids);
        if (!isset($items[$base_product_id])) {
            $base_product_id = reset($product_ids);
        }
        $base_item = $items[$base_product_id];
        $base_product = $base_item['product'];
        $base_details = $base_item['details'];

        $type_id = $this->type_mapper->resolve(
            $snapshot_id,
            ifset($base_product['description_category_id']),
            ifset($base_product['type_id'])
        );
        $category_id = $this->category_mapper->resolve($snapshot_id, ifset($base_product['description_category_id']));
        if (!$type_id || !$category_id) {
            return null;
        }

        $existing_map = $this->findExistingMapInGroup($items);
        $product_data = $this->buildProductData(
            $base_product,
            $base_details,
            $type_id,
            $category_id,
            shopProductModel::SKU_TYPE_SELECTABLE
        );

        if ($existing_map) {
            $product_id = (int) $existing_map['shop_product_id'];
            $product_data['edit_datetime'] = date('Y-m-d H:i:s');
            unset($product_data['create_datetime']);
            $this->product_model->updateById($product_id, $product_data);
        } else {
            $product_data['url'] = shopHelper::genUniqueUrl(
                shopHelper::transliterate($product_data['name']),
                $this->product_model
            );
            $product_data['create_datetime'] = date('Y-m-d H:i:s');
            $product_id = $this->product_model->insert($product_data);
        }

        $this->assignCategory($product_id, $category_id);

        $primary_urls = array();
        $additional_urls = array();
        $variant_primary_urls = array();
        foreach ($items as $item_product_id => $item) {
            $primary = $this->resolvePrimaryImageUrl($item['details']);
            if ($primary) {
                $primary_urls[] = $primary;
            }
            $variant_primary_urls[$item_product_id] = $primary;
            $additional_urls = array_merge($additional_urls, $this->collectImageUrls($item['details']));
        }

        $active_offer_ids = array();
        $sku_ids = array();
        $tag_mode = $this->resolveTagImportMode();

        if ($this->settings->getFeatureImportMode() === shopMigratePluginOzonSettings::FEATURE_MODE_AUTO) {
            list($common_attributes, $variant_attributes) = $this->splitAttributesByVariance($product_ids, $attribute_values);
            $this->applyFeatureAttributes($snapshot_id, $product_id, $type_id, $common_attributes);
            if ($this->shouldAssignTagsToProduct($tag_mode)) {
                $this->assignCollectedTags($product_id, $this->collectAttributesByProductIds($product_ids, $attribute_values));
            }
        } else {
            $variant_attributes = array_fill_keys($product_ids, array());
        }

        foreach ($items as $item_product_id => $item) {
            $variant_product = $item['product'];
            $variant_details = $item['details'];
            $sku_name = $this->resolveSkuName($product_data['name'], $variant_product, $variant_details);
            $sku_id = $this->ensureSku($product_id, $variant_product['offer_id'], $variant_product, $variant_details, array(
                'name'      => $sku_name,
            ));
            $this->ensureDefaultSkuAssigned($product_id, $sku_id);
            $sku_ids[$item_product_id] = $sku_id;
            if ($this->settings->getFeatureImportMode() === shopMigratePluginOzonSettings::FEATURE_MODE_AUTO) {
                $attributes = ifset($variant_attributes[$item_product_id], array());
                if (!$this->shouldAssignTagsToSku($tag_mode)) {
                    $attributes = $this->filterTagAttributes($attributes);
                }
                $this->assignSkuFeaturesFromAttributes($snapshot_id, $product_id, $type_id, $sku_id, $attributes);
            } else {
                $this->product_features_model->deleteByField(array(
                    'product_id' => $product_id,
                    'sku_id'     => $sku_id,
                ));
            }

            $this->assignStocks($snapshot_id, $product_id, $sku_id, $variant_product['offer_id'], $stocks_by_offer);
            if (!empty($variant_product['offer_id'])) {
                $active_offer_ids[] = (string) $variant_product['offer_id'];
                $this->product_map_model->linkOffer($variant_product['offer_id'], $product_id, $sku_id, $variant_product['product_id']);
            }
        }

        $this->cleanupObsoleteSkus($product_id, $active_offer_ids);
        $this->finalizeProductCounters($product_id);

        $image_map = $this->synchronizeProductImages($product_id, $primary_urls, $additional_urls);
        foreach ($sku_ids as $item_product_id => $sku_id) {
            $primary_image_url = ifset($variant_primary_urls[$item_product_id]);
            $sku_image_id = $this->getImageIdFromMap($product_id, $primary_image_url, $image_map);
            $this->product_skus_model->updateById($sku_id, array('image_id' => $sku_image_id));
        }

        return $existing_map ? false : true;
    }

    private function buildProductData(array $product, array $details, $type_id, $category_id)
    {
        $name = ifset($details['name'], ifset($product['name'], sprintf('Ozon %s', $product['product_id'])));
        $currency = $this->resolveProductCurrency($details);
        $price = $this->extractPrice($details);
        $compare_price = $this->extractComparePrice($details, $price);

        return array(
            'type_id'        => (int) $type_id,
            'category_id'    => (int) $category_id,
            'name'           => $name,
            'summary'        => ifset($details['description_short'], ''),
            'description'    => ifset($details['description'], ''),
            'sku_type'       => shopProductModel::SKU_TYPE_FLAT,
            'price'          => $price,
            'compare_price'  => $compare_price,
            'status'         => 1,
            'currency'       => $currency,
            'edit_datetime'  => date('Y-m-d H:i:s'),
        );
    }

    private function assignCategory($product_id, $category_id)
    {
        $this->category_products_model->deleteByField('product_id', $product_id);
        $this->category_products_model->insert(array(
            'product_id'  => $product_id,
            'category_id' => $category_id,
        ));
    }

    private function ensureSku($product_id, $offer_id, array $product, array $details, array $options = array())
    {
        $sku_name = isset($options['name']) ? $options['name'] : ifset($product['name'], 'Ozon SKU');
        $price = array_key_exists('price', $options) ? $options['price'] : $this->extractPrice($details);
        $compare_price = array_key_exists('compare_price', $options) ? $options['compare_price'] : $this->extractComparePrice($details, $price);
        $purchase_price = array_key_exists('purchase_price', $options) ? $options['purchase_price'] : $this->extractPurchasePrice($details);

        $sku_data = array(
            'product_id'    => $product_id,
            'sku'           => $offer_id ?: ('ozon-'.$product['product_id']),
            'name'          => $sku_name,
            'price'         => $price,
            'compare_price' => $compare_price,
            'purchase_price'=> $purchase_price,
            'count'         => null,
            'available'     => 1,
            'status'        => 1,
        );
        if (array_key_exists('image_id', $options)) {
            $sku_data['image_id'] = $options['image_id'];
        }

        $existing = $offer_id ? $this->product_skus_model->getByField(array(
            'product_id' => $product_id,
            'sku'        => $sku_data['sku'],
        )) : null;

        if ($existing) {
            $this->product_skus_model->updateById($existing['id'], $sku_data);
            return (int) $existing['id'];
        }

        return (int) $this->product_skus_model->insert($sku_data);
    }

    private function assignFeatures($snapshot_id, $product_id, $shop_type_id, array $values, $ozon_product_id)
    {
        if (empty($values[$ozon_product_id])) {
            return;
        }
        list($payload) = $this->buildFeaturePayload($values[$ozon_product_id], $snapshot_id, $shop_type_id, $product_id);
        $this->saveProductFeaturePayload($product_id, $payload);
    }

    private function applyFeatureAttributes($snapshot_id, $product_id, $shop_type_id, array $attributes)
    {
        if (!$attributes) {
            return;
        }
        list($payload) = $this->buildFeaturePayload($attributes, $snapshot_id, $shop_type_id, $product_id);
        $this->saveProductFeaturePayload($product_id, $payload);
    }

    private function assignSkuFeaturesFromAttributes($snapshot_id, $product_id, $shop_type_id, $sku_id, array $attributes)
    {
        $this->product_features_model->deleteByField(array(
            'product_id' => $product_id,
            'sku_id'     => $sku_id,
        ));
        if (!$attributes) {
            return;
        }
        list($payload, $feature_refs) = $this->buildFeaturePayload($attributes, $snapshot_id, $shop_type_id);
        $this->saveSkuFeaturePayload($product_id, $sku_id, $payload, $feature_refs);
    }

    private function buildFeaturePayload(array $attributes, $snapshot_id, $shop_type_id, $product_id_for_side_effects = null)
    {
        $payload = array();
        $feature_refs = array();
        foreach ($attributes as $attribute) {
            $attribute_name = $this->getNormalizedAttributeName($attribute);
            if ($product_id_for_side_effects !== null && $this->isAnnotationAttribute($attribute_name)) {
                $this->appendAnnotationToProductDescription($product_id_for_side_effects, $attribute['value']);
                continue;
            }
            if ($product_id_for_side_effects !== null && $this->isTagAttribute($attribute)) {
                continue;
            }
            $feature = $this->feature_mapper->resolve($snapshot_id, $attribute['attribute_id'], $attribute['meta'], $shop_type_id);
            if (!$feature) {
                continue;
            }
            $code = $feature['code'];
            if (!isset($payload[$code])) {
                $payload[$code] = array();
            }
            $feature_refs[$code] = $feature;

            $value = $attribute['value'];
            $feature_type = (string) ifset($feature['type'], '');
            $is_dimension_feature = $this->isDimensionFeatureType($feature_type);
            $is_range_feature = $this->isRangeFeatureType($feature_type);
            $list_values = null;
            if (!$is_dimension_feature && !$is_range_feature && $feature_type !== 'double') {
                $list_values = $this->extractListValuesFromString($value);
            }
            if ($list_values) {
                $value = count($list_values) > 1 ? $list_values : reset($list_values);
                $this->ensureFeatureSelectable($feature, count($list_values) > 1);
            } elseif ($feature_type === 'double' && is_string($value)) {
                $value = str_replace(',', '.', $value);
            }
            $value = $this->prepareFeatureValueForSave($feature, $attribute, $value);
            $value = $this->normalizeFeatureValueUnits($feature, $value);
            if (!empty($feature['multiple'])) {
                if ($this->isSequentialArray($value)) {
                    foreach ($value as $item_value) {
                        $payload[$code][] = $item_value;
                    }
                } else {
                    $payload[$code][] = $value;
                }
            } else {
                $payload[$code] = is_array($value) && $this->isSequentialArray($value) ? reset($value) : $value;
            }
        }

        return array($payload, $feature_refs);
    }

    private function saveProductFeaturePayload($product_id, array $payload)
    {
        if (!$payload) {
            return;
        }
        $product = new shopProduct($product_id);
        $product->features = $payload;
        $product->save();
    }

    private function saveSkuFeaturePayload($product_id, $sku_id, array $payload, array $feature_refs)
    {
        if (!$payload) {
            return;
        }
        $rows = array();
        $row_index = array();
        foreach ($payload as $code => $value) {
            if (!isset($feature_refs[$code]) || empty($feature_refs[$code]['id'])) {
                continue;
            }
            $feature = $feature_refs[$code];
            $values = $this->flattenFeatureValues($feature, $value);
            foreach ($values as $item_value) {
                $value_id = $this->feature_model->getValueId($feature, $item_value, true);
                if (!$value_id) {
                    continue;
                }
                $row_key = implode('-', array((int) $product_id, (int) $sku_id, (int) $feature['id'], (int) $value_id));
                if (isset($row_index[$row_key])) {
                    continue;
                }
                $row_index[$row_key] = true;
                $rows[] = array(
                    'product_id'       => $product_id,
                    'sku_id'           => $sku_id,
                    'feature_id'       => $feature['id'],
                    'feature_value_id' => $value_id,
                );
            }
        }
        if ($rows) {
            $this->product_features_model->multipleInsert($rows, waModel::INSERT_IGNORE);
        }
    }

    private function flattenFeatureValues(array $feature, $value)
    {
        if (!empty($feature['multiple'])) {
            if ($this->isSequentialArray($value)) {
                return $value;
            }
            return array($value);
        }
        return array($value);
    }

    private function assignStocks($snapshot_id, $product_id, $sku_id, $offer_id, array $stocks_by_offer)
    {
        if (!$offer_id || empty($stocks_by_offer[$offer_id])) {
            return;
        }

        $stock_payload = array();
        foreach ($stocks_by_offer[$offer_id] as $stock_row) {
            $shop_stock_id = $this->stock_mapper->resolve($snapshot_id, $stock_row['warehouse_id']);
            if ($shop_stock_id) {
                if (!isset($stock_payload[$shop_stock_id])) {
                    $stock_payload[$shop_stock_id] = 0;
                }
                $stock_payload[$shop_stock_id] += (float) $stock_row['quantity'];
            }
        }

        if (!$stock_payload) {
            return;
        }

        foreach ($stock_payload as $stock_id => $quantity) {
            $this->product_stocks_model->deleteById(array($sku_id, $stock_id));
            $this->product_stocks_model->insert(array(
                'product_id' => $product_id,
                'sku_id'     => $sku_id,
                'stock_id'   => $stock_id,
                'count'      => $quantity,
            ));
        }
        $total = array_sum($stock_payload);
        $this->product_skus_model->updateById($sku_id, array('count' => $total));
        $this->product_model->updateById($product_id, array('count' => $total));
    }

    private function synchronizeProductImages($product_id, array $primary_urls, array $additional_urls = array())
    {
        $ordered = array();
        $all_urls = array_merge($primary_urls, $additional_urls);
        foreach ($all_urls as $url) {
            $normalized = $this->normalizeImageUrl($url);
            if ($normalized) {
                $ordered[] = $normalized;
            }
        }
        $ordered = array_values(array_unique($ordered));
        $limit = 20;
        if (count($ordered) > $limit) {
            $ordered = array_slice($ordered, 0, $limit);
        }

        $this->product_images_model->deleteByProducts(array($product_id), true);
        $map = array();

        foreach ($ordered as $url) {
            $file = $this->downloadImage($url);
            if (!$file) {
                continue;
            }
            try {
                $data = $this->product_images_model->addImage($file, $product_id, $this->resolveImageFilename($url));
                if (!empty($data['id'])) {
                    $map[$url] = (int) $data['id'];
                }
            } catch (Exception $e) {
                waLog::log('[OzonImporter] Image import failed: '.$e->getMessage(), shopMigratePluginOzonLogger::LOG_FILE);
            }
            waFiles::delete($file);
        }

        $this->image_cache[$product_id] = $map;
        return $map;
    }

    private function collectImageUrls(array $details)
    {
        $sources = array('primary_image', 'images', 'images360', 'color_image');
        $urls = array();
        foreach ($sources as $key) {
            if (empty($details[$key])) {
                continue;
            }
            $urls = array_merge($urls, $this->extractImageUrls($details[$key]));
        }
        $urls = array_filter(array_unique($urls));
        $result = array();
        foreach ($urls as $url) {
            if (is_string($url) && preg_match('~^https?://~i', $url)) {
                $result[] = $url;
            }
        }
        return $result;
    }

    private function resolvePrimaryImageUrl(array $details)
    {
        $candidates = array(
            ifset($details['primary_image']),
            ifset($details['color_image']),
            ifset($details['images']),
            ifset($details['images360']),
        );
        foreach ($candidates as $candidate) {
            if (!$candidate) {
                continue;
            }
            $urls = $this->extractImageUrls($candidate);
            foreach ($urls as $url) {
                $normalized = $this->normalizeImageUrl($url);
                if ($normalized) {
                    return $normalized;
                }
            }
        }
        $fallbacks = $this->collectImageUrls($details);
        return $fallbacks ? reset($fallbacks) : null;
    }

    private function normalizeImageUrl($url)
    {
        if (!is_string($url)) {
            return null;
        }
        $url = trim($url);
        if ($url === '' || !preg_match('~^https?://~i', $url)) {
            return null;
        }
        return $url;
    }

    private function getImageIdFromMap($product_id, $url, array $image_map)
    {
        if ($url && isset($image_map[$url])) {
            return $image_map[$url];
        }
        if ($url && isset($this->image_cache[$product_id][$url])) {
            return $this->image_cache[$product_id][$url];
        }
        if ($image_map) {
            $first = reset($image_map);
            return $first ?: null;
        }
        return null;
    }

    private function resolveSkuName($base_name, array $product, array $details)
    {
        if (!empty($details['name']) && is_string($details['name'])) {
            return $details['name'];
        }
        if (!empty($product['name'])) {
            return $product['name'];
        }
        return $base_name ?: 'Ozon SKU';
    }

    private function extractImageUrls($value)
    {
        $result = array();
        if (is_string($value)) {
            $result[] = $value;
        } elseif (is_array($value)) {
            foreach ($value as $item) {
                if (is_string($item)) {
                    $result[] = $item;
                } elseif (is_array($item)) {
                    if (!empty($item['url'])) {
                        $result[] = $item['url'];
                    } elseif (!empty($item['value'])) {
                        $result[] = $item['value'];
                    } elseif (!empty($item['source'])) {
                        $result[] = $item['source'];
                    }
                }
            }
        }
        return $result;
    }

    private function downloadImage($url)
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        $net = new waNet(array(
            'timeout'            => 10,
            'format'             => waNet::FORMAT_RAW,
            'expected_http_code' => null,
        ));
        try {
            $content = $net->query($url);
        } catch (Exception $e) {
            waLog::log('[OzonImporter] Image download failed ('.$url.'): '.$e->getMessage(), shopMigratePluginOzonLogger::LOG_FILE);
            return null;
        }
        if ($content === false || $content === null) {
            return null;
        }
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!$extension) {
            $mime = strtolower((string) $net->getResponseHeader('content_type'));
            $extension = $this->guessMimeExtension($mime);
        }
        if (!$extension) {
            $extension = 'jpg';
        }
        $path = $this->temp_image_dir.uniqid('ozon_img_', true).'.'.$extension;
        if (file_put_contents($path, $content) === false) {
            return null;
        }
        return $path;
    }

    private function resolveImageFilename($url)
    {
        $name = basename(parse_url($url, PHP_URL_PATH));
        $name = preg_replace('/[^a-z0-9\._-]+/i', '_', $name);
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'ozon-image.jpg';
        }
        if (!pathinfo($name, PATHINFO_EXTENSION)) {
            $name .= '.jpg';
        }
        return $name;
    }

    private function guessMimeExtension($mime)
    {
        $map = array(
            'image/jpeg' => 'jpg',
            'image/pjpeg'=> 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        );
        return ifset($map[$mime]);
    }

    private function extractPrice(array $details)
    {
        $candidates = array();
        if (isset($details['price'])) {
            $price_block = $details['price'];
            $candidates[] = $price_block;
            if (is_array($price_block)) {
                if (isset($price_block['price'])) {
                    $candidates[] = $price_block['price'];
                }
                if (isset($price_block['value'])) {
                    $candidates[] = $price_block['value'];
                }
            }
        }
        if (isset($details['min_price'])) {
            $candidates[] = $details['min_price'];
        }

        foreach ($candidates as $value) {
            $price = $this->normalizePriceValue($value);
            if ($price !== null) {
                return $price;
            }
        }

        return 0.0;
    }

    private function extractPurchasePrice(array $details)
    {
        if (isset($details['price']) && is_array($details['price']) && isset($details['price']['premium_price'])) {
            $price = $this->normalizePriceValue($details['price']['premium_price']);
            if ($price !== null) {
                return $price;
            }
        }
        if (isset($details['premium_price'])) {
            $price = $this->normalizePriceValue($details['premium_price']);
            if ($price !== null) {
                return $price;
            }
        }
        return null;
    }

    private function extractComparePrice(array $details, $price = null)
    {
        $candidates = array();
        if (isset($details['old_price'])) {
            $candidates[] = $details['old_price'];
        }
        if (isset($details['oldPrice'])) {
            $candidates[] = $details['oldPrice'];
        }
        if (isset($details['price']) && is_array($details['price'])) {
            if (isset($details['price']['old_price'])) {
                $candidates[] = $details['price']['old_price'];
            }
            if (isset($details['price']['oldPrice'])) {
                $candidates[] = $details['price']['oldPrice'];
            }
        }

        foreach ($candidates as $value) {
            $compare = $this->normalizePriceValue($value);
            if ($compare === null) {
                continue;
            }
            if ($compare <= 0) {
                return null;
            }

            $base_price = $price !== null ? (float) $price : $this->extractPrice($details);
            if ($base_price > 0 && $compare <= $base_price) {
                return null;
            }

            return $compare;
        }

        return null;
    }

    private function normalizePriceValue($value)
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
            $value = preg_replace('/\s+/', '', $value);
            $value = str_replace(',', '.', $value);
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        return null;
    }

    private function groupValuesByProduct($snapshot_id, array $product_ids)
    {
        $rows = $this->repository->getAttributeValuesModel()->getForProducts($snapshot_id, $product_ids);
        $result = array();
        foreach ($rows as $row) {
            $product_id = $row['product_id'];
            if (!isset($result[$product_id])) {
                $result[$product_id] = array();
            }
            if (!isset($result[$product_id][$row['attribute_id']])) {
                $result[$product_id][$row['attribute_id']] = array(
                    'attribute_id' => $row['attribute_id'],
                    'meta'         => array(),
                    'values'       => array(),
                );
            }
            $result[$product_id][$row['attribute_id']]['values'][] = array(
                'value'              => $row['value'],
                'dictionary_value_id'=> $row['dictionary_value_id'],
            );
        }

        $attributes = $this->repository->getAttributesModel()->getBySnapshot($snapshot_id);
        foreach ($result as $product_id => &$attributes_group) {
            foreach ($attributes_group as $attribute_id => &$data) {
                if (isset($attributes[$attribute_id])) {
                    $data['meta'] = $attributes[$attribute_id];
                }
            }
            unset($data);
        }
        unset($attributes_group);

        $flattened = array();
        foreach ($result as $product_id => $group) {
            foreach ($group as $attribute_id => $data) {
                foreach ($data['values'] as $value) {
                    $flattened[$product_id][] = array(
                        'attribute_id' => $attribute_id,
                        'value'        => $this->decodeAttributeValue($value['value']),
                        'meta'         => $data['meta'],
                    );
                }
            }
        }

        return $flattened;
    }

    private function groupStocksByOffer($snapshot_id)
    {
        $rows = $this->repository->getStocksModel()->getAllBySnapshot($snapshot_id);
        $result = array();
        foreach ($rows as $row) {
            $offer_id = $row['offer_id'];
            if (!$offer_id) {
                continue;
            }
            if (!isset($result[$offer_id])) {
                $result[$offer_id] = array();
            }
            $result[$offer_id][] = $row;
        }
        return $result;
    }

    private function splitAttributesByVariance(array $product_ids, array $attribute_values)
    {
        $groups = array();
        foreach ($product_ids as $product_id) {
            if (empty($attribute_values[$product_id])) {
                continue;
            }
            foreach ($attribute_values[$product_id] as $attribute) {
                $attribute_id = $attribute['attribute_id'];
                if (!isset($groups[$attribute_id])) {
                    $groups[$attribute_id] = array(
                        'values' => array(),
                        'raw'    => array(),
                    );
                }
                if (!isset($groups[$attribute_id]['values'][$product_id])) {
                    $groups[$attribute_id]['values'][$product_id] = array();
                    $groups[$attribute_id]['raw'][$product_id] = array();
                }
                $groups[$attribute_id]['values'][$product_id][] = $this->normalizeAttributeValueForComparison($attribute['value']);
                $groups[$attribute_id]['raw'][$product_id][] = array(
                    'attribute_id' => $attribute_id,
                    'value'        => $attribute['value'],
                    'meta'         => ifset($attribute['meta'], array()),
                );
            }
        }

        $common = array();
        $per_product = array();
        foreach ($product_ids as $product_id) {
            $per_product[$product_id] = array();
        }

        if (!$groups) {
            return array($common, $per_product);
        }

        foreach ($groups as $attribute_id => $data) {
            $is_tag_attribute = false;
            foreach ($product_ids as $product_id) {
                $entries = ifset($data['raw'][$product_id], array());
                if ($entries) {
                    $is_tag_attribute = $this->isTagAttribute(reset($entries));
                    break;
                }
            }
            if ($is_tag_attribute) {
                foreach ($product_ids as $product_id) {
                    if (empty($data['raw'][$product_id])) {
                        continue;
                    }
                    foreach ($data['raw'][$product_id] as $attribute_entry) {
                        $per_product[$product_id][] = $attribute_entry;
                    }
                }
                continue;
            }
            $hashes = array();
            foreach ($product_ids as $product_id) {
                $values = ifset($data['values'][$product_id], array());
                if ($values) {
                    sort($values);
                }
                $hashes[$product_id] = json_encode($values);
            }
            $unique_hashes = array_values(array_unique($hashes));
            if (count($unique_hashes) <= 1 && $unique_hashes && $unique_hashes[0] !== '[]') {
                $first_id = $product_ids[0];
                foreach (ifset($data['raw'][$first_id], array()) as $attribute_entry) {
                    $common[] = $attribute_entry;
                }
            } else {
                foreach ($product_ids as $product_id) {
                    if (empty($data['raw'][$product_id])) {
                        continue;
                    }
                    foreach ($data['raw'][$product_id] as $attribute_entry) {
                        $per_product[$product_id][] = $attribute_entry;
                    }
                }
            }
        }

        return array($common, $per_product);
    }

    private function normalizeAttributeValueForComparison($value)
    {
        if (is_array($value)) {
            $normalized = array();
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeAttributeValueForComparison($item);
            }
            ksort($normalized);
            return $normalized;
        }
        if (is_string($value)) {
            return trim($value);
        }
        return $value;
    }

    private function normalizeFeatureValueUnits(array $feature, $value)
    {
        $feature_type = (string) ifset($feature['type'], '');
        if (!$this->isDimensionFeatureType($feature_type) && !$this->isRangeFeatureType($feature_type)) {
            return $value;
        }
        if ($this->isSequentialArray($value)) {
            foreach ($value as &$item) {
                $item = $this->normalizeFeatureValueUnits($feature, $item);
            }
            unset($item);
            return $value;
        }
        if (!is_array($value)) {
            return $value;
        }
        $dimension = shopDimension::getInstance();
        $dimension_type = substr($feature_type, strpos($feature_type, '.') + 1);
        $dimension_meta = $dimension->getDimension($dimension_type);
        $target_unit = '';
        if (!empty($feature['default_unit'])) {
            $target_unit = $dimension->fixUnit($dimension_type, $feature['default_unit']);
        } elseif ($dimension_meta && !empty($dimension_meta['base_unit'])) {
            $target_unit = $dimension_meta['base_unit'];
        }
        if (!$target_unit) {
            return $value;
        }
        $current_unit = isset($value['unit']) ? $value['unit'] : '';
        $current_unit = $dimension->fixUnit($dimension_type, $current_unit);
        if (!$current_unit && $dimension_meta && !empty($dimension_meta['base_unit'])) {
            $current_unit = '';
        }
        if ($current_unit === '' || $current_unit === $target_unit) {
            $value['unit'] = $target_unit;
            return $value;
        }

        if ($this->isRangeFeatureType($feature_type) && isset($value['value']) && is_array($value['value'])) {
            if (isset($value['value']['begin'])) {
                $value['value']['begin'] = $this->convertDimensionScalar($value['value']['begin'], $dimension_type, $target_unit, $current_unit);
            }
            if (isset($value['value']['end'])) {
                $value['value']['end'] = $this->convertDimensionScalar($value['value']['end'], $dimension_type, $target_unit, $current_unit);
            }
        } elseif (isset($value['value'])) {
            $value['value'] = $this->convertDimensionScalar($value['value'], $dimension_type, $target_unit, $current_unit);
        }
        $value['unit'] = $target_unit;
        return $value;
    }

    private function convertDimensionScalar($raw_value, $dimension_type, $target_unit, $current_unit)
    {
        if ($raw_value === '' || $raw_value === null) {
            return $raw_value;
        }
        if (is_string($raw_value)) {
            $normalized = str_replace(',', '.', $raw_value);
            if (!is_numeric($normalized)) {
                return $raw_value;
            }
            $raw_value = (float) $normalized;
        }
        if (!is_numeric($raw_value)) {
            return $raw_value;
        }
        $dimension = shopDimension::getInstance();
        $base_value = $dimension->convert((float) $raw_value, $dimension_type, null, $current_unit ?: null);
        return $dimension->convert($base_value, $dimension_type, $target_unit, null);
    }

    private function resolveProductCurrency(array $details)
    {
        $config = wa('shop')->getConfig();
        $currency = '';
        if (!empty($details['currency_code']) && is_string($details['currency_code'])) {
            $currency = strtoupper(trim($details['currency_code']));
        }
        if ($currency === '') {
            $resolved = $config->getCurrency(true);
            if (is_string($resolved) && $resolved !== '') {
                $currency = $resolved;
            }
        }
        if ($currency === '') {
            $resolved = $config->getCurrency();
            if (is_string($resolved) && $resolved !== '') {
                $currency = $resolved;
            }
        }
        if ($currency === '') {
            $currency = 'RUB';
        }
        return $currency;
    }

    private function cleanupObsoleteSkus($product_id, array $active_offer_ids)
    {
        if (!$product_id) {
            return;
        }
        $active_offer_ids = array_map('strval', array_filter($active_offer_ids, static function ($value) {
            return $value !== '';
        }));
        $active_map = array_fill_keys($active_offer_ids, true);
        $mappings = $this->product_map_model->getByShopProductId($product_id);
        if (!$mappings) {
            return;
        }
        foreach ($mappings as $mapping) {
            $offer_id = (string) ifset($mapping['offer_id'], '');
            if ($offer_id === '' || isset($active_map[$offer_id])) {
                continue;
            }
            $sku_id = (int) ifset($mapping['shop_sku_id']);
            if ($sku_id) {
                $this->product_skus_model->delete($sku_id);
            }
            $this->product_map_model->deleteById($mapping['id']);
        }
    }

    private function finalizeProductCounters($product_id)
    {
        if (!$product_id) {
            return;
        }
        $product = $this->product_model->getById($product_id);
        if (!$product) {
            return;
        }
        $this->repairProductDefaultSku($product_id);
        $sku_exists = $this->product_skus_model->select('COUNT(*) AS cnt')
            ->where('product_id = i:pid', array('pid' => $product_id))
            ->fetchField('cnt');
        if (!$sku_exists) {
            $this->product_model->updateById($product_id, array(
                'sku_count' => 0,
                'count'     => 0,
            ));
            return;
        }
        if (empty($product['currency'])) {
            $fallback_currency = $this->resolveProductCurrency(array());
            $this->product_model->updateById($product_id, array('currency' => $fallback_currency));
        }
        $this->product_model->correct($product_id);
    }

    private function ensureDefaultSkuAssigned($product_id, $sku_id)
    {
        if (!$product_id || !$sku_id) {
            return;
        }
        if (!array_key_exists($product_id, $this->product_default_sku)) {
            $product = $this->product_model->getById($product_id);
            $this->product_default_sku[$product_id] = $product ? (int) ifset($product['sku_id'], 0) : 0;
        }
        if (!$this->product_default_sku[$product_id]) {
            $this->product_model->updateById($product_id, array('sku_id' => $sku_id));
            $this->product_default_sku[$product_id] = $sku_id;
        }
    }

    private function repairProductDefaultSku($product_id)
    {
        if (!$product_id) {
            return;
        }
        $product = $this->product_model->getById($product_id);
        if (!$product) {
            return;
        }
        $current_sku_id = (int) ifset($product['sku_id'], 0);
        if ($current_sku_id) {
            $sku = $this->product_skus_model->getById($current_sku_id);
            if ($sku) {
                return;
            }
        }
        $replacement_id = $this->product_skus_model->select('id')
            ->where('product_id = i:pid', array('pid' => $product_id))
            ->order('id')
            ->limit(1)
            ->fetchField();
        if ($replacement_id) {
            $this->product_model->updateById($product_id, array('sku_id' => (int) $replacement_id));
            $this->product_default_sku[$product_id] = (int) $replacement_id;
        }
    }

    private function isTagAttribute(array $attribute)
    {
        if (empty($attribute['meta']['name'])) {
            return false;
        }
        $name = mb_strtolower($attribute['meta']['name']);
        if (strpos($name, '#') !== false) {
            return true;
        }
        if (strpos($name, 'хештег') !== false || strpos($name, 'хэштег') !== false) {
            return true;
        }
        return false;
    }

    private function resolveTagImportMode()
    {
        $mode = $this->settings->getEffectiveTagImportMode();
        $allowed = array(
            shopMigratePluginOzonSettings::TAG_MODE_PRODUCT_ONLY,
            shopMigratePluginOzonSettings::TAG_MODE_PRODUCT_AND_SKU,
            shopMigratePluginOzonSettings::TAG_MODE_SKU_ONLY,
        );
        return in_array($mode, $allowed, true) ? $mode : shopMigratePluginOzonSettings::TAG_MODE_PRODUCT_ONLY;
    }

    private function shouldAssignTagsToProduct($mode)
    {
        return in_array($mode, array(
            shopMigratePluginOzonSettings::TAG_MODE_PRODUCT_ONLY,
            shopMigratePluginOzonSettings::TAG_MODE_PRODUCT_AND_SKU,
        ), true);
    }

    private function shouldAssignTagsToSku($mode)
    {
        return in_array($mode, array(
            shopMigratePluginOzonSettings::TAG_MODE_PRODUCT_AND_SKU,
            shopMigratePluginOzonSettings::TAG_MODE_SKU_ONLY,
        ), true);
    }

    private function filterTagAttributes(array $attributes)
    {
        $result = array();
        foreach ($attributes as $attribute) {
            if ($this->isTagAttribute($attribute)) {
                continue;
            }
            $result[] = $attribute;
        }
        return $result;
    }

    private function assignCollectedTags($product_id, array $attributes)
    {
        if (!$product_id || !$attributes) {
            return;
        }

        $collected_tags = array();
        foreach ($attributes as $attribute) {
            if (!$this->isTagAttribute($attribute)) {
                continue;
            }
            $value = ifset($attribute['value'], '');
            if (!is_string($value) || $value === '') {
                continue;
            }
            foreach ($this->extractTags($value) as $tag) {
                $collected_tags[$tag] = $tag;
            }
        }

        if (!$collected_tags) {
            return;
        }

        $tag_ids = $this->resolveUniqueTagIds(array_values($collected_tags));
        if (!$tag_ids) {
            return;
        }
        $this->product_tags_model->assign((int) $product_id, $tag_ids);
    }

    private function collectAttributesByProductIds(array $product_ids, array $attribute_values)
    {
        $result = array();
        foreach ($product_ids as $product_id) {
            if (empty($attribute_values[$product_id])) {
                continue;
            }
            foreach ($attribute_values[$product_id] as $attribute) {
                $result[] = $attribute;
            }
        }
        return $result;
    }

    private function resolveUniqueTagIds(array $tags)
    {
        $tag_ids = $this->tag_model->getIds($tags);
        if (!$tag_ids) {
            return array();
        }
        $result = array();
        foreach ($tag_ids as $tag_id) {
            $tag_id = (int) $tag_id;
            if ($tag_id > 0) {
                $result[$tag_id] = $tag_id;
            }
        }
        return array_values($result);
    }

    private function extractTags($string)
    {
        if (!is_string($string) || $string === '') {
            return array();
        }
        $tags = array();
        if (preg_match_all('/#([\p{L}\p{N}_-]+)/u', $string, $matches)) {
            foreach ($matches[1] as $tag) {
                $tag = trim(str_replace('_', ' ', $tag));
                if ($tag !== '') {
                    $tags[] = $tag;
                }
            }
        } else {
            $parts = preg_split('/[\s,]+/u', $string);
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $tags[] = ltrim($part, '#');
                }
            }
        }
        return array_values(array_unique($tags));
    }
    private function extractListValuesFromString($value)
    {
        if (!is_string($value) || strpos($value, ',') === false) {
            return null;
        }
        if (!preg_match('/,\s*\S/u', $value)) {
            return null;
        }
        $parts = array_map('trim', explode(',', $value));
        $parts = array_filter($parts, static function ($part) {
            return $part !== '';
        });
        if (count($parts) < 2) {
            return null;
        }
        return array_values(array_unique($parts));
    }

    private function prepareFeatureValueForSave(array $feature, array $attribute, $value)
    {
        if ($this->isSequentialArray($value)) {
            $result = array();
            foreach ($value as $item) {
                $result[] = $this->prepareFeatureValueForSave($feature, $attribute, $item);
            }
            return $result;
        }
        $type = (string) ifset($feature['type'], '');
        if ($this->isDimensionFeatureType($type)) {
            $numeric = $this->castFeatureNumericValue($value);
            if (!is_array($value) || !array_key_exists('value', $value)) {
                $value = array('value' => $numeric);
            } else {
                $value['value'] = $numeric;
            }
            $unit = $this->feature_mapper->detectAttributeUnit($attribute, $feature);
            if ($unit && empty($value['unit'])) {
                $value['unit'] = $unit;
            }
            $value['type'] = substr($type, strpos($type, '.') + 1);
        } elseif ($this->isRangeFeatureType($type)) {
            if (!is_array($value) || !isset($value['value'])) {
                $value = array('value' => $value);
            }
            $unit = $this->feature_mapper->detectAttributeUnit($attribute, $feature);
            if ($unit && empty($value['unit'])) {
                $value['unit'] = $unit;
            }
            $value['type'] = substr($type, strpos($type, '.') + 1);
        }
        return $value;
    }

    private function castFeatureNumericValue($value)
    {
        if (is_numeric($value)) {
            return $value + 0;
        }
        if (is_string($value)) {
            $normalized = str_replace(',', '.', $value);
            if (is_numeric($normalized)) {
                return (float) $normalized;
            }
        }
        return $value;
    }

    private function isDimensionFeatureType($type)
    {
        return is_string($type) && strpos($type, 'dimension.') === 0;
    }

    private function isRangeFeatureType($type)
    {
        return is_string($type) && strpos($type, 'range.') === 0;
    }

    private function isSequentialArray($value)
    {
        if (!is_array($value)) {
            return false;
        }
        if (!$value) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function ensureFeatureSelectable(array &$feature, $is_multiple)
    {
        $updates = array();
        if (empty($feature['selectable'])) {
            $updates['selectable'] = 1;
            $feature['selectable'] = 1;
        }
        $desired_multiple = $is_multiple ? 1 : 0;
        if (!isset($feature['multiple']) || (int) $feature['multiple'] !== $desired_multiple) {
            $updates['multiple'] = $desired_multiple;
            $feature['multiple'] = $desired_multiple;
        }
        if (!empty($feature['selectable']) && ifset($feature['type']) === 'text') {
            $updates['type'] = 'varchar';
            $feature['type'] = 'varchar';
        }
        if ($updates && !empty($feature['id'])) {
            $this->feature_mapper->updateFeatureFlags($feature['id'], $updates);
        }
    }

    private function decodeAttributeValue($value)
    {
        if (!is_string($value)) {
            return $value;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        if (strpos($trimmed, '__json__:') === 0) {
            $decoded = json_decode(substr($trimmed, 9), true);
            return (json_last_error() === JSON_ERROR_NONE) ? $decoded : '';
        }
        $first = $trimmed[0];
        if (($first === '[' || $first === '{')) {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        return $value;
    }

    private function getNormalizedAttributeName(array $attribute)
    {
        $name = '';
        if (!empty($attribute['meta']['name'])) {
            $name = $attribute['meta']['name'];
        } elseif (!empty($attribute['name'])) {
            $name = $attribute['name'];
        }
        if (!is_string($name)) {
            return '';
        }
        $name = mb_strtolower(trim($name), 'UTF-8');
        return str_replace('ё', 'е', $name);
    }

    private function isAnnotationAttribute($normalized_name)
    {
        return $normalized_name === 'аннотация';
    }

    private function appendAnnotationToProductDescription($product_id, $value)
    {
        $text = $this->extractPlainStringValue($value);
        if ($text === '') {
            return;
        }
        $product = $this->product_model->getById($product_id);
        if (!$product) {
            return;
        }
        $description = (string) ifset($product['description'], '');
        if ($description !== '') {
            $description .= "\n\n";
        }
        $description .= $text;
        $this->product_model->updateById($product_id, array('description' => $description));
    }

    private function extractPlainStringValue($value)
    {
        if (is_array($value)) {
            if (isset($value['value'])) {
                $value = $value['value'];
            } else {
                $value = reset($value);
            }
        }
        $value = trim((string) $value);
        return $value;
    }
}
