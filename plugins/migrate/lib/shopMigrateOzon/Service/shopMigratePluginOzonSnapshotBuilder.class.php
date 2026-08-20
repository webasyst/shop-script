<?php

class shopMigratePluginOzonSnapshotBuilder
{
    const SCRIPT_TIME_LIMIT_SECONDS = 30;
    const HARD_DEADLINE_SECONDS = 24;
    const MAX_STEPS_PER_REQUEST = 2;
    const NEXT_STEP_RESERVE_SECONDS = 14;
    const PRODUCT_DETAILS_BATCH_SIZE = 25;
    const PRODUCT_ATTRIBUTES_BATCH_SIZE = 25;
    const STOCKS_BATCH_SIZE = 25;
    const CATEGORY_PAIRS_BATCH_SIZE = 1;
    const MAX_INVALID_PAIRS_IN_META = 50;
    const MAX_TYPE_PATHS_IN_META = 500;

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
        $snapshot_id = 0;
        do {
            $result = $this->advance($snapshot_id);
            $snapshot_id = (int) $result['snapshot_id'];
        } while (empty($result['done']));

        return $snapshot_id;
    }

    public function advance($snapshot_id = 0)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::SCRIPT_TIME_LIMIT_SECONDS);
        }
        $this->startHardDeadline(self::HARD_DEADLINE_SECONDS);

        $snapshot_id = $this->resolveBuildingSnapshotId($snapshot_id);
        $snapshot = $this->repository->getSnapshotsModel()->getByIdSafe($snapshot_id);
        if (!$snapshot) {
            throw new waException('Snapshot is not found');
        }
        if ($snapshot['status'] === 'ready') {
            $this->settings->setCurrentSnapshotId($snapshot_id);
            $this->settings->clearBuildingSnapshotReference();
            return $this->buildBatchResponse($snapshot_id, array('phase' => 'done'), true);
        }
        if (!in_array($snapshot['status'], array('building', 'draft'), true)) {
            throw new waException('Snapshot cannot be resumed because its status is '.$snapshot['status']);
        }

        $meta = $this->repository->getSnapshotsModel()->decodeMeta($snapshot);
        $state = ifset($meta['build'], array());
        if (!is_array($state) || empty($state['phase'])) {
            $state = $this->createInitialBuildState();
        }

        $done = false;
        $steps = 0;
        while (!$done
            && $steps < self::MAX_STEPS_PER_REQUEST
            && $this->hasRuntimeBudget(self::NEXT_STEP_RESERVE_SECONDS)
        ) {
            $done = $this->advancePhase($snapshot_id, $state);
            $steps++;
        }

        if (!$done) {
            $meta['build'] = $state;
            $this->repository->saveBuildState($snapshot_id, $meta);
        }

        $this->logBatchMemory($snapshot_id, (string) ifset($state['phase'], 'done'));
        return $this->buildBatchResponse($snapshot_id, $state, $done);
    }

    private function resolveBuildingSnapshotId($snapshot_id)
    {
        $snapshot_id = (int) $snapshot_id;
        if ($snapshot_id > 0) {
            return $snapshot_id;
        }

        $saved_id = $this->settings->getBuildingSnapshotId();
        if ($saved_id > 0) {
            $saved = $this->repository->getSnapshotsModel()->getByIdSafe($saved_id);
            if ($saved && in_array($saved['status'], array('building', 'draft'), true)) {
                return $saved_id;
            }
            if ($saved && $saved['status'] === 'ready') {
                $this->settings->setCurrentSnapshotId($saved_id);
                $this->settings->clearBuildingSnapshotReference();
                return $saved_id;
            }
            $this->settings->clearBuildingSnapshotReference();
        }

        $state = $this->createInitialBuildState();
        $snapshot_id = $this->repository->createBuildingSnapshot(array('build' => $state));
        $this->repository->dropSnapshotData($snapshot_id);
        $this->settings->setBuildingSnapshotId($snapshot_id);
        waLog::log(
            sprintf('[OzonSnapshotBuilder] Started resumable snapshot #%d', $snapshot_id),
            shopMigratePluginOzonLogger::LOG_FILE
        );
        return $snapshot_id;
    }

    private function createInitialBuildState()
    {
        return array(
            'version'                           => 1,
            'phase'                             => 'warehouses',
            'started_at'                        => date('Y-m-d H:i:s'),
            'product_last_id'                   => '',
            'products_loaded'                   => 0,
            'products_expected'                 => 0,
            'details_cursor'                    => 0,
            'details_processed'                 => 0,
            'category_pair_offset'              => 0,
            'category_pairs_total'              => 0,
            'product_attributes_cursor'         => 0,
            'product_attributes_processed'      => 0,
            'stocks_cursor'                     => 0,
            'stocks_processed'                  => 0,
            'type_paths'                        => array(),
            'type_paths_count'                  => 0,
            'invalid_attribute_pairs'           => array(),
            'invalid_attribute_pairs_count'     => 0,
            'invalid_attribute_products_count'  => 0,
            'invalid_attribute_error'           => '',
        );
    }

    private function advancePhase($snapshot_id, array &$state)
    {
        switch ((string) ifset($state['phase'], 'warehouses')) {
            case 'warehouses':
                $this->collectWarehouses($snapshot_id);
                $state['phase'] = 'products';
                return false;

            case 'products':
                $this->collectProductsPage($snapshot_id, $state);
                return false;

            case 'details':
                $this->collectProductDetailsPage($snapshot_id, $state);
                return false;

            case 'categories':
                $this->collectCategories($snapshot_id);
                $state['type_paths'] = array_slice($this->category_type_paths, 0, self::MAX_TYPE_PATHS_IN_META, true);
                $state['type_paths_count'] = count($this->category_type_paths);
                $state['category_pairs_total'] = $this->repository->getProductsModel()->countCategoryTypePairs($snapshot_id);
                $state['phase'] = 'category_attributes';
                return false;

            case 'category_attributes':
                $this->collectCategoryAttributesPage($snapshot_id, $state);
                return false;

            case 'product_attributes':
                $this->collectProductAttributesPage($snapshot_id, $state);
                return false;

            case 'stocks':
                $this->collectStocksPage($snapshot_id, $state);
                return false;

            case 'finalize':
                $this->finalizeSnapshot($snapshot_id, $state);
                $state['phase'] = 'done';
                return true;

            case 'done':
                return true;
        }

        throw new waException('Unknown snapshot build phase: '.(string) $state['phase']);
    }

    private function collectProductsPage($snapshot_id, array &$state)
    {
        $request_last_id = (string) ifset($state['product_last_id'], '');
        $response = $this->api->listProducts($request_last_id);
        $result = ifset($response['result'], array());
        $items = ifset($result['items'], array());
        $formatted = array();

        foreach ((array) $items as $item) {
            $product_id = (int) ifset($item['product_id']);
            if ($product_id <= 0) {
                continue;
            }
            $formatted[] = array(
                'product_id'              => $product_id,
                'offer_id'                => ifset($item['offer_id']),
                'sku'                     => $this->extractSkuFromApiProduct($item),
                'description_category_id' => ifset($item['description_category_id']),
                'type_id'                 => ifset($item['type_id']),
                'name'                    => ifset($item['name']),
                'flags'                   => array(
                    'fbo' => !empty($item['has_fbo_sales']) || !empty($item['has_fbo_stocks']),
                    'fbs' => !empty($item['has_fbs_sales']) || !empty($item['has_fbs_stocks']),
                ),
            );
        }
        $this->repository->getProductsModel()->addBatch($snapshot_id, $formatted);

        if (!empty($result['total'])) {
            $state['products_expected'] = (int) $result['total'];
        }
        $state['products_loaded'] = (int) ifset($state['products_loaded'], 0) + count($formatted);
        $next_last_id = (string) ifset($result['last_id'], '');
        $has_next = !empty($result['has_next']);
        $can_continue = ($has_next || $next_last_id !== '')
            && $next_last_id !== $request_last_id
            && !empty($items);

        if ($can_continue) {
            $state['product_last_id'] = $next_last_id;
            return;
        }

        $state['products_loaded'] = $this->repository->getProductsModel()->countBySnapshot($snapshot_id);
        if (empty($state['products_expected'])) {
            $state['products_expected'] = $state['products_loaded'];
        }
        $state['phase'] = 'details';
    }

    private function collectProductDetailsPage($snapshot_id, array &$state)
    {
        $products_model = $this->repository->getProductsModel();
        $ids = $products_model->getIdsAfter(
            $snapshot_id,
            (int) ifset($state['details_cursor'], 0),
            self::PRODUCT_DETAILS_BATCH_SIZE
        );
        if (!$ids) {
            $state['phase'] = 'categories';
            return;
        }

        $this->api->eachProductsInfoBatch($ids, function (array $details) use ($products_model, $snapshot_id) {
            foreach ($details as $item) {
                $product_id = (int) ifset($item['id'], ifset($item['product_id']));
                if ($product_id > 0) {
                    $products_model->updateDetails($snapshot_id, $product_id, $item);
                }
            }
        });
        $state['details_cursor'] = (int) max($ids);
        $state['details_processed'] = (int) ifset($state['details_processed'], 0) + count($ids);
    }

    private function collectCategoryAttributesPage($snapshot_id, array &$state)
    {
        $offset = (int) ifset($state['category_pair_offset'], 0);
        $pairs = $this->repository->getProductsModel()->getCategoryTypePairs(
            $snapshot_id,
            $offset,
            self::CATEGORY_PAIRS_BATCH_SIZE
        );
        if (!$pairs) {
            $state['phase'] = 'product_attributes';
            return;
        }

        $category_paths = $this->repository->getCategoriesModel()->getPathMap($snapshot_id);
        foreach ($pairs as $pair) {
            $this->collectCategoryAttributePair($snapshot_id, $pair, $category_paths, $state);
        }
        $state['category_pair_offset'] = $offset + count($pairs);
        if (count($pairs) < self::CATEGORY_PAIRS_BATCH_SIZE
            || $state['category_pair_offset'] >= (int) ifset($state['category_pairs_total'], 0)
        ) {
            $state['phase'] = 'product_attributes';
        }
    }

    private function collectCategoryAttributePair($snapshot_id, array $pair, array $category_paths, array &$state)
    {
        $category_id = (int) ifset($pair['description_category_id']);
        $type_id = (int) ifset($pair['type_id']);
        $key = $category_id.':'.$type_id;
        try {
            $response = $this->api->getAttributesForCategory($category_id, $type_id);
        } catch (Exception $e) {
            if (!$this->isMissingCategoryTypePairError($e)) {
                throw $e;
            }
            $type_paths = (array) ifset($state['type_paths'], array());
            $path = isset($type_paths[$key])
                ? $type_paths[$key]
                : ifset($category_paths[$category_id], '');
            $this->recordInvalidAttributePair($state, array(
                'description_category_id' => $category_id,
                'type_id'                 => $type_id,
                'path'                    => (string) $path,
                'products_count'          => (int) ifset($pair['products_count'], 0),
            ), $e->getMessage());
            waLog::log(
                sprintf('[OzonSnapshotBuilder] Category/type pair %s skipped: %s', $key, $e->getMessage()),
                shopMigratePluginOzonLogger::LOG_FILE
            );
            return;
        }

        $formatted = array();
        foreach ((array) ifset($response['result'], array()) as $item) {
            $formatted[] = array(
                'description_category_id' => $category_id,
                'type_id'                 => $type_id,
                'attribute_id'            => ifset($item['id'], ifset($item['attribute_id'])),
                'name'                    => ifset($item['name'], ''),
                'type'                    => ifset($item['type'], ''),
                'unit'                    => ifset($item['unit']),
                'is_required'             => !empty($item['is_required']),
                'is_collection'           => !empty($item['is_collection']),
                'meta'                    => $item,
            );
        }
        $this->repository->getAttributesModel()->addBatch($snapshot_id, $formatted);
    }

    private function recordInvalidAttributePair(array &$state, array $pair, $message)
    {
        $state['invalid_attribute_pairs_count'] = (int) ifset($state['invalid_attribute_pairs_count'], 0) + 1;
        $state['invalid_attribute_products_count'] = (int) ifset($state['invalid_attribute_products_count'], 0)
            + (int) ifset($pair['products_count'], 0);
        if (count((array) ifset($state['invalid_attribute_pairs'], array())) < self::MAX_INVALID_PAIRS_IN_META) {
            $state['invalid_attribute_pairs'][] = $pair;
        }
        if (empty($state['invalid_attribute_error'])) {
            $state['invalid_attribute_error'] = (string) $message;
        }
    }

    private function collectProductAttributesPage($snapshot_id, array &$state)
    {
        $ids = $this->repository->getProductsModel()->getIdsAfter(
            $snapshot_id,
            (int) ifset($state['product_attributes_cursor'], 0),
            self::PRODUCT_ATTRIBUTES_BATCH_SIZE
        );
        if (!$ids) {
            $state['phase'] = 'stocks';
            return;
        }
        $this->collectProductAttributes($snapshot_id, $ids);
        $state['product_attributes_cursor'] = (int) max($ids);
        $state['product_attributes_processed'] = (int) ifset($state['product_attributes_processed'], 0) + count($ids);
    }

    private function collectStocksPage($snapshot_id, array &$state)
    {
        $rows = $this->repository->getProductsModel()->getStockRowsAfter(
            $snapshot_id,
            (int) ifset($state['stocks_cursor'], 0),
            self::STOCKS_BATCH_SIZE
        );
        if (!$rows) {
            $state['phase'] = 'finalize';
            return;
        }
        foreach ($rows as &$row) {
            $row['sku'] = ifset($row['ozon_sku']);
        }
        unset($row);
        $this->collectStocks($snapshot_id, $rows);
        $state['stocks_cursor'] = (int) max(array_keys($rows));
        $state['stocks_processed'] = (int) ifset($state['stocks_processed'], 0) + count($rows);
    }

    private function finalizeSnapshot($snapshot_id, array $state)
    {
        $products_model = $this->repository->getProductsModel();
        $type_paths = (array) ifset($state['type_paths'], array());
        $meta = array(
            'products'   => $products_model->countBySnapshot($snapshot_id),
            'categories' => $products_model->countCategoriesBySnapshot($snapshot_id),
            'warehouses' => count($this->repository->getWarehousesModel()->getAllBySnapshot($snapshot_id)),
            'pairs'      => $products_model->countCategoryTypePairs($snapshot_id),
            'stocks'     => true,
        );
        if ($type_paths) {
            $meta['type_paths'] = $type_paths;
            $meta['type_paths_count'] = (int) ifset($state['type_paths_count'], count($type_paths));
            $meta['type_paths_truncated'] = $meta['type_paths_count'] > count($type_paths) ? 1 : 0;
        }
        $invalid_count = (int) ifset($state['invalid_attribute_pairs_count'], 0);
        if ($invalid_count > 0) {
            $meta['invalid_attribute_pairs'] = array_values((array) ifset($state['invalid_attribute_pairs'], array()));
            $meta['invalid_attribute_pairs_count'] = $invalid_count;
            $meta['invalid_attribute_products_count'] = (int) ifset($state['invalid_attribute_products_count'], 0);
            $meta['invalid_attribute_pairs_truncated'] = $invalid_count > count($meta['invalid_attribute_pairs']) ? 1 : 0;
            if (!empty($state['invalid_attribute_error'])) {
                $meta['invalid_attribute_error'] = (string) $state['invalid_attribute_error'];
            }
        }

        $this->repository->markReady($snapshot_id, $meta);
        $this->settings->setCurrentSnapshotId($snapshot_id);
        $this->settings->clearBuildingSnapshotReference();
        waLog::log(
            sprintf('[OzonSnapshotBuilder] Snapshot #%d is ready: %d products', $snapshot_id, $meta['products']),
            shopMigratePluginOzonLogger::LOG_FILE
        );
    }

    private function extractSkuFromApiProduct(array $item)
    {
        if (isset($item['sku']) && $item['sku'] !== '') {
            return (string) $item['sku'];
        }
        foreach ((array) ifset($item['sources'], array()) as $source) {
            if (isset($source['sku']) && $source['sku'] !== '') {
                return (string) $source['sku'];
            }
        }
        return null;
    }

    private function buildBatchResponse($snapshot_id, array $state, $done)
    {
        $progress = $done ? 100.0 : $this->calculateBuildProgress($state);
        return array(
            'snapshot_id' => (int) $snapshot_id,
            'done'        => (bool) $done,
            'phase'       => (string) ifset($state['phase'], 'done'),
            'progress'    => $progress,
            'processed'   => $this->getBuildProcessed($state),
            'total'       => (int) ifset($state['products_expected'], ifset($state['products_loaded'], 0)),
            'message'     => $this->getBuildPhaseMessage($state, $progress),
        );
    }

    private function calculateBuildProgress(array $state)
    {
        $phase = (string) ifset($state['phase'], 'warehouses');
        $ranges = array(
            'warehouses'          => array(0, 2),
            'products'            => array(2, 20),
            'details'             => array(20, 40),
            'categories'          => array(40, 45),
            'category_attributes' => array(45, 60),
            'product_attributes'  => array(60, 80),
            'stocks'              => array(80, 98),
            'finalize'            => array(98, 100),
        );
        if (!isset($ranges[$phase])) {
            return 0.0;
        }
        list($start, $end) = $ranges[$phase];
        $ratio = 0.0;
        $products_total = max(1, (int) ifset($state['products_expected'], ifset($state['products_loaded'], 1)));
        if ($phase === 'products') {
            $ratio = min(0.95, (int) ifset($state['products_loaded'], 0) / $products_total);
        } elseif ($phase === 'details') {
            $ratio = min(1, (int) ifset($state['details_processed'], 0) / $products_total);
        } elseif ($phase === 'category_attributes') {
            $ratio = min(1, (int) ifset($state['category_pair_offset'], 0) / max(1, (int) ifset($state['category_pairs_total'], 1)));
        } elseif ($phase === 'product_attributes') {
            $ratio = min(1, (int) ifset($state['product_attributes_processed'], 0) / $products_total);
        } elseif ($phase === 'stocks') {
            $ratio = min(1, (int) ifset($state['stocks_processed'], 0) / $products_total);
        }
        return round($start + ($end - $start) * $ratio, 1);
    }

    private function getBuildProcessed(array $state)
    {
        switch ((string) ifset($state['phase'], '')) {
            case 'products':
                return (int) ifset($state['products_loaded'], 0);
            case 'details':
                return (int) ifset($state['details_processed'], 0);
            case 'category_attributes':
                return (int) ifset($state['category_pair_offset'], 0);
            case 'product_attributes':
                return (int) ifset($state['product_attributes_processed'], 0);
            case 'stocks':
                return (int) ifset($state['stocks_processed'], 0);
        }
        return 0;
    }

    private function getBuildPhaseMessage(array $state, $progress)
    {
        $labels = array(
            'warehouses'          => _wp('warehouses'),
            'products'            => _wp('product list'),
            'details'             => _wp('product details'),
            'categories'          => _wp('categories'),
            'category_attributes' => _wp('category attributes'),
            'product_attributes'  => _wp('product attributes'),
            'stocks'              => _wp('stocks'),
            'finalize'            => _wp('finalization'),
            'done'                => _wp('complete'),
        );
        $phase = (string) ifset($state['phase'], 'done');
        return sprintf(
            '%s: %s%% (%s)',
            _wp('Collecting Ozon data before import'),
            $progress,
            ifset($labels[$phase], $phase)
        );
    }

    private function hasRuntimeBudget($reserve_seconds)
    {
        return $this->hard_deadline_at <= 0
            || microtime(true) + max(0, (int) $reserve_seconds) < $this->hard_deadline_at;
    }

    private function logBatchMemory($snapshot_id, $phase)
    {
        waLog::log(
            sprintf(
                '[OzonSnapshotBuilder] Batch #%d phase=%s memory=%.1fM peak=%.1fM',
                (int) $snapshot_id,
                (string) $phase,
                memory_get_usage(true) / 1048576,
                memory_get_peak_usage(true) / 1048576
            ),
            shopMigratePluginOzonLogger::LOG_FILE
        );
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
        $products_model = $this->repository->getProductsModel();
        $this->api->eachProductsInfoBatch($product_ids, function (array $details) use (&$products, $products_model, $snapshot_id) {
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
        });
    }

    private function collectProductAttributes($snapshot_id, array $product_ids)
    {
        if (!$product_ids) {
            return;
        }
        $attribute_values_model = $this->repository->getAttributeValuesModel();
        $this->api->eachProductsAttributesBatch($product_ids, function (array $batches) use ($attribute_values_model, $snapshot_id) {
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
        });
    }

    private function collectStocks($snapshot_id, array $products)
    {
        if (!$products) {
            return;
        }
        $sku_index = array();
        $offer_index = array();
        $identifiers = array();
        foreach ($products as $product) {
            if (!empty($product['sku'])) {
                $sku_index[(string) $product['sku']] = $product['product_id'];
                $identifiers[] = array('sku' => (string) $product['sku']);
            } elseif (!empty($product['offer_id'])) {
                $identifiers[] = array('offer_id' => (string) $product['offer_id']);
            }
            if (!empty($product['offer_id'])) {
                $offer_index[(string) $product['offer_id']] = $product['product_id'];
            }
        }
        if (!$identifiers) {
            return;
        }
        $stocks = array();
        $existing_warehouses = $this->repository->getWarehousesModel()->getAllBySnapshot($snapshot_id);
        $existing_ids = array_fill_keys(array_keys($existing_warehouses), true);
        $new_warehouses = array();
        $stocks_model = $this->repository->getStocksModel();
        $flush_stocks = function () use (&$stocks, $stocks_model, $snapshot_id) {
            if (!$stocks) {
                return;
            }
            $stocks_model->addBatch($snapshot_id, $stocks);
            $stocks = array();
        };

        $this->api->eachStocksByWarehouseFbsBatch($identifiers, function (array $responses) use (&$stocks, &$new_warehouses, $existing_ids, &$products, &$sku_index, &$offer_index, $flush_stocks) {
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
                if (count($stocks) >= 500) {
                    call_user_func($flush_stocks);
                }
            }
        });
        call_user_func($flush_stocks);
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
