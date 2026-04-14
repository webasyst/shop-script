<?php

class shopMigratePluginOzonSnapshotBuilder
{
    const SCRIPT_TIME_LIMIT_SECONDS = 600;
    const HARD_DEADLINE_SECONDS = 590;
    const MAX_INVALID_PAIRS_IN_META = 50;

    private $api;
    private $repository;
    private $settings;
    private $category_type_paths = array();
    private $invalid_attribute_pairs = array();
    private $first_invalid_attribute_error = '';
    private $hard_deadline_at = 0.0;

    public function __construct(shopMigratePluginOzonApiClient $api, shopMigratePluginOzonSnapshotRepository $repository, shopMigratePluginOzonSettings $settings)
    {
        $this->api = $api;
        $this->repository = $repository;
        $this->settings = $settings;
    }

    public function build(array $options = array())
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::SCRIPT_TIME_LIMIT_SECONDS);
        }
        $this->startHardDeadline(self::HARD_DEADLINE_SECONDS);
        $this->invalid_attribute_pairs = array();
        $this->first_invalid_attribute_error = '';
        $snapshot_id = $this->repository->createSnapshot();
        $this->repository->dropSnapshotData($snapshot_id);

        try {
            $this->ensureRuntimeNotExceeded('collect warehouses');
            $this->collectWarehouses($snapshot_id);
            $this->ensureRuntimeNotExceeded('collect products');
            $products = $this->collectProducts($snapshot_id);
            $this->ensureRuntimeNotExceeded('collect product details');
            $this->collectProductDetails($snapshot_id, $products);
            $this->ensureRuntimeNotExceeded('collect categories');
            $this->collectCategories($snapshot_id);
            $this->ensureRuntimeNotExceeded('collect category attributes');
            list($pairs, $type_paths) = $this->collectAttributes($snapshot_id, $products);
            $this->ensureRuntimeNotExceeded('collect product attributes');
            $this->collectProductAttributes($snapshot_id, array_keys($products));
            $this->ensureRuntimeNotExceeded('collect stocks');
            $this->collectStocks($snapshot_id, $products);
            $warehouses_count = count($this->repository->getWarehousesModel()->getAllBySnapshot($snapshot_id));

            $category_usage = array();
            foreach ($products as $product) {
                $this->ensureRuntimeNotExceeded('build snapshot meta');
                if (!empty($product['description_category_id'])) {
                    $category_usage[$product['description_category_id']] = true;
                }
            }

            $meta = array(
                'products'   => count($products),
                'categories' => count($category_usage),
                'warehouses' => $warehouses_count,
                'pairs'      => count($pairs),
                'stocks'     => true,
                'type_paths' => $type_paths,
            );
            if ($this->invalid_attribute_pairs) {
                $invalid_pairs = array_values($this->invalid_attribute_pairs);
                $meta['invalid_attribute_pairs'] = array_slice($invalid_pairs, 0, self::MAX_INVALID_PAIRS_IN_META);
                $meta['invalid_attribute_pairs_count'] = count($invalid_pairs);
                $meta['invalid_attribute_products_count'] = $this->countProductsInInvalidPairs();
                $meta['invalid_attribute_pairs_truncated'] = count($invalid_pairs) > self::MAX_INVALID_PAIRS_IN_META ? 1 : 0;
                if ($this->first_invalid_attribute_error !== '') {
                    $meta['invalid_attribute_error'] = $this->first_invalid_attribute_error;
                }
            }

            $this->repository->markReady($snapshot_id, $meta);
            $this->settings->setCurrentSnapshotId($snapshot_id);

            return $snapshot_id;
        } catch (Exception $e) {
            $this->repository->markFailed($snapshot_id, $e->getMessage());
            $this->settings->clearSnapshotReference();
            throw $e;
        }
    }

    private function collectWarehouses($snapshot_id)
    {
        $response = $this->api->listWarehouses();
        $items = ifset($response['result'], ifset($response['warehouses'], array()));
        if (isset($items['warehouses']) && is_array($items['warehouses'])) {
            $items = $items['warehouses'];
        }
        $warehouses = array();
        foreach ($items as $item) {
            $warehouses[] = array(
                'warehouse_id' => ifset($item['warehouse_id'], ifset($item['id'])),
                'name'         => ifset($item['name'], ''),
                'type'         => ifset($item['type'], ifset($item['warehouse_type'], '')),
            );
        }
        $this->repository->getWarehousesModel()->addBatch($snapshot_id, $warehouses);
        return $warehouses;
    }

    private function collectProducts($snapshot_id)
    {
        $products = array();
        $products_model = $this->repository->getProductsModel();
        $last_id = '';
        do {
            $this->ensureRuntimeNotExceeded('load product list page');
            $request_last_id = $last_id;
            $response = $this->api->listProducts($request_last_id);
            $result = ifset($response['result'], array());
            $items = ifset($result['items'], array());
            $formatted = array();
            foreach ($items as $item) {
                $product_id = ifset($item['product_id']);
                if (!$product_id) {
                    continue;
                }
                $sources = ifset($item['sources'], array());
                $sku = null;
                foreach ($sources as $source) {
                    if (!empty($source['sku'])) {
                        $sku = $source['sku'];
                        break;
                    }
                }
                $product = array(
                    'product_id'              => $product_id,
                    'offer_id'                => ifset($item['offer_id']),
                    'sku'                     => $sku,
                    'description_category_id' => ifset($item['description_category_id']),
                    'type_id'                 => ifset($item['type_id']),
                    'name'                    => ifset($item['name']),
                    'flags'                   => array(
                        'fbo' => !empty($item['has_fbo_sales']) || !empty($item['has_fbo_stocks']),
                        'fbs' => !empty($item['has_fbs_sales']) || !empty($item['has_fbs_stocks']),
                    ),
                );
                $products[$product_id] = $product;
                $formatted[] = $product;
            }
            $products_model->addBatch($snapshot_id, $formatted);
            $has_next = !empty($result['has_next']);
            $next_last_id = (string) ifset($result['last_id'], '');
            $should_continue = $has_next || $next_last_id !== '';

            // Defensive stop for unstable API pagination: avoid endless loop on repeated cursor.
            if ($should_continue && $next_last_id === $request_last_id) {
                break;
            }

            $last_id = $next_last_id;
        } while ($should_continue);

        return $products;
    }

    private function collectCategories($snapshot_id)
    {
        $response = $this->api->getDescriptionCategoryTree('RU');
        $tree = ifset($response['result'], array());
        $flat = array();
        $type_paths = array();
        $this->flattenCategories($tree, array(), $flat, 0, null, $type_paths, null);
        $this->category_type_paths = $type_paths;
        $this->repository->getCategoriesModel()->addBatch($snapshot_id, $flat);
        return $flat;
    }

    private function flattenCategories(array $nodes, array $path, array &$flat, $level, $parent_id, array &$type_paths, $current_category_id)
    {
        foreach ($nodes as $node) {
            $this->ensureRuntimeNotExceeded('flatten categories tree');
            $node_name = trim(ifset($node['category_name'], ifset($node['name'], ifset($node['type_name'], ''))));
            $description_category_id = ifset($node['description_category_id'], ifset($node['id']));
            if ($description_category_id) {
                $current_path = array_merge($path, array($node_name));
                $flat[] = array(
                    'description_category_id' => $description_category_id,
                    'parent_id'               => $parent_id,
                    'name'                    => $node_name,
                    'path'                    => implode(' / ', array_filter($current_path)),
                    'level'                   => $level,
                );
                if (!empty($node['children']) && is_array($node['children'])) {
                    $this->flattenCategories($node['children'], $current_path, $flat, $level + 1, $description_category_id, $type_paths, $description_category_id);
                }
                continue;
            }

            $type_id = ifset($node['type_id']);
            if ($type_id && $current_category_id) {
                $current_path = array_merge($path, array($node_name));
                $type_paths[$current_category_id.':'.$type_id] = implode(' / ', array_filter($current_path));
                if (!empty($node['children']) && is_array($node['children'])) {
                    $this->flattenCategories($node['children'], $current_path, $flat, $level, $parent_id, $type_paths, $current_category_id);
                }
                continue;
            }

            if (!empty($node['children']) && is_array($node['children'])) {
                $this->flattenCategories($node['children'], $path, $flat, $level, $parent_id, $type_paths, $current_category_id);
            }
        }
    }

    private function collectAttributes($snapshot_id, array $products)
    {
        $pairs = array();
        $pair_product_counts = array();
        foreach ($products as $product) {
            $category_id = ifset($product['description_category_id']);
            $type_id = ifset($product['type_id']);
            if (!$category_id || !$type_id) {
                continue;
            }
            $key = $category_id.':'.$type_id;
            $pairs[$key] = array(
                'description_category_id' => $category_id,
                'type_id'                 => $type_id,
            );
            if (!isset($pair_product_counts[$key])) {
                $pair_product_counts[$key] = 0;
            }
            $pair_product_counts[$key]++;
        }

        $pair_paths = array();
        foreach ($pairs as $key => $pair) {
            if (isset($this->category_type_paths[$key])) {
                $pair_paths[$key] = $this->category_type_paths[$key];
            }
        }
        $category_paths = $this->repository->getCategoriesModel()->getPathMap($snapshot_id);

        $attributes_model = $this->repository->getAttributesModel();
        foreach ($pairs as $key => $pair) {
            $this->ensureRuntimeNotExceeded('load attributes for category/type pairs');
            try {
                $response = $this->api->getAttributesForCategory($pair['description_category_id'], $pair['type_id']);
            } catch (Exception $e) {
                if ($this->isMissingCategoryTypePairError($e)) {
                    $path = ifset(
                        $pair_paths[$key],
                        ifset($category_paths[$pair['description_category_id']], '')
                    );
                    $this->invalid_attribute_pairs[$key] = array(
                        'description_category_id' => (int) $pair['description_category_id'],
                        'type_id'                 => (int) $pair['type_id'],
                        'path'                    => (string) $path,
                        'products_count'          => (int) ifset($pair_product_counts[$key], 0),
                    );
                    if ($this->first_invalid_attribute_error === '') {
                        $this->first_invalid_attribute_error = (string) $e->getMessage();
                    }
                    waLog::log(
                        sprintf(
                            '[OzonSnapshotBuilder] Category/type pair %s skipped. Products will be imported without features. %s',
                            $key,
                            $e->getMessage()
                        ),
                        shopMigratePluginOzonLogger::LOG_FILE
                    );
                    continue;
                }
                throw $e;
            }
            $items = ifset($response['result'], array());
            $formatted = array();
            foreach ($items as $item) {
                $formatted[] = array(
                    'description_category_id' => $pair['description_category_id'],
                    'type_id'                 => $pair['type_id'],
                    'attribute_id'            => ifset($item['id'], ifset($item['attribute_id'])),
                    'name'                    => ifset($item['name'], ''),
                    'type'                    => ifset($item['type'], ''),
                    'unit'                    => ifset($item['unit']),
                    'is_required'             => !empty($item['is_required']),
                    'is_collection'           => !empty($item['is_collection']),
                    'meta'                    => $item,
                );
            }
            $attributes_model->addBatch($snapshot_id, $formatted);
        }

        return array($pairs, $pair_paths);
    }

    private function isMissingCategoryTypePairError(Exception $e)
    {
        return (bool) preg_match('/category with level_3_id=\d+\s+and\s+type=\d+\s+is not found/i', $e->getMessage());
    }

    private function countProductsInInvalidPairs()
    {
        $count = 0;
        foreach ($this->invalid_attribute_pairs as $pair) {
            $count += (int) ifset($pair['products_count'], 0);
        }
        return $count;
    }

    private function collectProductDetails($snapshot_id, array &$products)
    {
        if (!$products) {
            return;
        }
        $product_ids = array_keys($products);
        $details = $this->api->getProductsInfoBatch($product_ids);
        $products_model = $this->repository->getProductsModel();
        foreach ($details as $item) {
            $this->ensureRuntimeNotExceeded('process product details');
            $product_id = ifset($item['id'], ifset($item['product_id']));
            if (!$product_id) {
                continue;
            }
            if (isset($products[$product_id])) {
                if (isset($item['description_category_id'])) {
                    $products[$product_id]['description_category_id'] = $item['description_category_id'];
                }
                if (isset($item['type_id'])) {
                    $products[$product_id]['type_id'] = $item['type_id'];
                }
                if (isset($item['name']) && $item['name'] !== '') {
                    $products[$product_id]['name'] = $item['name'];
                }
                if (empty($products[$product_id]['sku'])) {
                    $sources = ifset($item['sources'], array());
                    if (is_array($sources)) {
                        foreach ($sources as $source) {
                            if (!empty($source['sku'])) {
                                $products[$product_id]['sku'] = (string) $source['sku'];
                                break;
                            }
                        }
                    }
                    if (empty($products[$product_id]['sku']) && isset($item['sku']) && $item['sku'] !== '') {
                        $products[$product_id]['sku'] = (string) $item['sku'];
                    }
                }
            }
            $products_model->updateDetails($snapshot_id, $product_id, $item);
        }
    }

    private function collectProductAttributes($snapshot_id, array $product_ids)
    {
        if (!$product_ids) {
            return;
        }
        $attribute_values_model = $this->repository->getAttributeValuesModel();
        $batches = $this->api->getProductsAttributesBatch($product_ids);
        foreach ($batches as $item) {
            $this->ensureRuntimeNotExceeded('process product attributes');
            $product_id = ifset($item['product_id'], ifset($item['id']));
            if (!$product_id || empty($item['attributes'])) {
                continue;
            }
            $values = array();
            foreach ($item['attributes'] as $attribute) {
                $attribute_id = ifset($attribute['attribute_id'], ifset($attribute['id']));
                if (!$attribute_id) {
                    continue;
                }
                $position = 0;
                foreach (ifset($attribute['values'], array()) as $value) {
                    $values[] = array(
                        'product_id'         => $product_id,
                        'attribute_id'       => $attribute_id,
                        'dictionary_value_id'=> ifset($value['dictionary_value_id']),
                        'value'              => $this->sanitizeValue(ifset($value['value'])),
                        'position'           => $position++,
                    );
                }
            }
            $attribute_values_model->addBatch($snapshot_id, $values);
        }
    }

    private function collectStocks($snapshot_id, array $products)
    {
        if (!$products) {
            return;
        }
        $sku_index = array();
        $offer_index = array();
        foreach ($products as $product) {
            if (!empty($product['sku'])) {
                $sku_index[(string) $product['sku']] = $product['product_id'];
            }
            if (!empty($product['offer_id'])) {
                $offer_index[(string) $product['offer_id']] = $product['product_id'];
            }
        }
        $identifiers = array();
        if ($sku_index) {
            foreach (array_keys($sku_index) as $sku) {
                $identifiers[] = array('sku' => $sku);
            }
        } elseif ($offer_index) {
            foreach (array_keys($offer_index) as $offer_id) {
                $identifiers[] = array('offer_id' => $offer_id);
            }
        }
        if (!$identifiers) {
            return;
        }
        $responses = $this->api->getStocksByWarehouseFbsBatch($identifiers);
        $stocks = array();
        $existing_warehouses = $this->repository->getWarehousesModel()->getAllBySnapshot($snapshot_id);
        $existing_ids = array_fill_keys(array_keys($existing_warehouses), true);
        $new_warehouses = array();

        foreach ($responses as $item) {
            $this->ensureRuntimeNotExceeded('process warehouse stocks');
            $warehouse_id = ifset($item['warehouse_id']);
            if (!$warehouse_id) {
                continue;
            }
            if (!isset($existing_ids[$warehouse_id]) && !isset($new_warehouses[$warehouse_id])) {
                $warehouse_name = trim((string) ifset($item['warehouse_name'], ''));
                if ($warehouse_name === '') {
                    $warehouse_name = 'Ozon '.$warehouse_id;
                }
                $new_warehouses[$warehouse_id] = array(
                    'warehouse_id' => $warehouse_id,
                    'name'         => $warehouse_name,
                    'type'         => '',
                );
            }
            $product_id = null;
            if (!empty($item['sku']) && isset($sku_index[(string) $item['sku']])) {
                $product_id = $sku_index[(string) $item['sku']];
            }
            if (!$product_id && !empty($item['offer_id']) && isset($offer_index[(string) $item['offer_id']])) {
                $product_id = $offer_index[(string) $item['offer_id']];
            }
            if (!$product_id && !empty($item['product_id']) && isset($products[(int) $item['product_id']])) {
                $product_id = (int) $item['product_id'];
            }
            if (!$product_id) {
                continue;
            }
            $product = ifset($products[$product_id], array());
            $offer_id = ifset($product['offer_id'], ifset($item['offer_id'], ifset($item['sku'])));
            $stocks[] = array(
                'product_id'  => (int) $product_id,
                'offer_id'    => (string) $offer_id,
                'warehouse_id'=> $warehouse_id,
                'quantity'    => ifset($item['present'], ifset($item['quantity'], ifset($item['free_stock'], 0))),
            );
        }
        $this->repository->getStocksModel()->addBatch($snapshot_id, $stocks);
        if ($new_warehouses) {
            $this->repository->getWarehousesModel()->addBatch($snapshot_id, array_values($new_warehouses));
        }
    }

    private function startHardDeadline($seconds)
    {
        $seconds = max(1, (int) $seconds);
        $this->hard_deadline_at = microtime(true) + $seconds;
    }

    private function ensureRuntimeNotExceeded($phase)
    {
        if ($this->hard_deadline_at <= 0) {
            return;
        }
        if (microtime(true) <= $this->hard_deadline_at) {
            return;
        }
        throw new waException(sprintf(
            'Snapshot build exceeded %d seconds at phase: %s',
            self::HARD_DEADLINE_SECONDS,
            (string) $phase
        ));
    }

    private function sanitizeValue($value)
    {
        if (is_array($value) || is_object($value)) {
            return $this->encodeComplexValue($value);
        }
        if (!is_string($value)) {
            return $value;
        }
        $value = trim($value);
        if ($value === '' || $value === '[object Object]') {
            return '';
        }
        // Remove 4-byte UTF-8 sequences (emojis) that cannot be stored in utf8 columns.
        return preg_replace('%[\xF0-\xF7][\x80-\xBF]{3}%', '', $value);
    }

    private function encodeComplexValue($value)
    {
        if ($value instanceof Traversable) {
            $value = iterator_to_array($value);
        }
        if (is_object($value)) {
            $value = (array) $value;
        }
        $normalized = array();
        foreach ($value as $item) {
            if (is_array($item) || is_object($item)) {
                $item = $this->encodeComplexValue($item);
            } else {
                $item = $this->sanitizeValue($item);
            }
            if ($item === null || $item === '' || $item === array()) {
                continue;
            }
            $normalized[] = $item;
        }
        if (!$normalized) {
            return '';
        }
        if (count($normalized) === 1 && !is_array($normalized[0])) {
            return $normalized[0];
        }
        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '' : '__json__:'.$encoded;
    }
}
