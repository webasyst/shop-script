<?php

class shopMigratePluginWbSnapshotBuilder
{
    const STATE_VERSION = 3;
    const SCRIPT_TIME_LIMIT_SECONDS = 30;
    const CARDS_PAGE_SIZE = 100;
    const SUBJECTS_PAGE_SIZE = 1000;
    const PRICES_BATCH_SIZE = 500;
    const SELLER_STOCKS_BATCH_SIZE = 500;
    const FBW_PRODUCTS_BATCH_SIZE = 500;
    const MAX_FBW_RETRIES = 2;
    const MAX_WARNINGS = 50;
    const MAX_PHASE_RETRIES = 3;
    const MAX_PHASE_BACKOFF_SECONDS = 8;
    const MAX_RETRY_AFTER_SECONDS = 60;
    // Stable importer-owned IDs, kept below JavaScript's safe integer limit
    // and far outside WB's current characteristic ID range.
    const SYNTHETIC_BRAND_ID = 900000000000001;
    const SYNTHETIC_LENGTH_ID = 900000000000002;
    const SYNTHETIC_WIDTH_ID = 900000000000003;
    const SYNTHETIC_HEIGHT_ID = 900000000000004;
    const SYNTHETIC_WEIGHT_ID = 900000000000005;

    private $api;
    private $repository;
    private $settings;
    private $logger;
    private $operation_lock;

    public function __construct(
        shopMigratePluginWbApiClient $api,
        shopMigratePluginWbSnapshotRepository $repository,
        shopMigratePluginWbSettings $settings
    ) {
        $this->api = $api;
        $this->repository = $repository;
        $this->settings = $settings;
        $mode = method_exists($settings, 'getLogMode') ? $settings->getLogMode() : shopMigratePluginWbLogger::MODE_ERRORS;
        $this->logger = new shopMigratePluginWbLogger($mode);
        $this->operation_lock = new shopMigratePluginWbOperationLock();
    }

    public function build(array $options = array())
    {
        $snapshot_id = 0;
        do {
            $result = $this->advance($snapshot_id, $options);
            $snapshot_id = (int) $result['snapshot_id'];
            if (empty($result['done']) && !empty($result['retry_after_ms'])) {
                usleep(min(1000000, max(1000, (int) $result['retry_after_ms'] * 1000)));
            }
        } while (empty($result['done']));
        return $snapshot_id;
    }

    /**
     * Performs exactly one bounded snapshot unit. A completed snapshot is immutable:
     * passing its ID only returns the final status and never touches its source rows.
     */
    public function advance($snapshot_id = 0, array $options = array())
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::SCRIPT_TIME_LIMIT_SECONDS);
        }

        if (!$this->operation_lock->acquire('global')) {
            throw new waException(_wp('Another Wildberries operation is in progress. Try again.'));
        }
        try {
            $this->assertCollectionRevision($options);
            return $this->advanceLocked($snapshot_id);
        } finally {
            $this->operation_lock->release('global');
        }
    }

    private function advanceLocked($snapshot_id = 0)
    {
        $snapshot_id = $this->resolveBuildingSnapshotId($snapshot_id);
        $snapshots_model = $this->repository->getSnapshotsModel();
        $snapshot = $snapshots_model->getByIdSafe($snapshot_id);
        if (!$snapshot) {
            throw new waException(_wp('Wildberries snapshot was not found.'));
        }

        $meta = $snapshots_model->decodeMeta($snapshot);
        $state = isset($meta['build']) && is_array($meta['build'])
            ? $meta['build']
            : $this->createInitialState();
        $this->ensureServerCapacity($state);
        $this->ensurePermissionState($state);
        $this->assertTokenFingerprint($state);

        // Old ready snapshots remain immutable. Building snapshots can resume
        // their seller stages and then collect FBW inventory separately.
        if ((int) ifset($state['version'], 1) < self::STATE_VERSION) {
            $state['version'] = self::STATE_VERSION;
            $supported_phases = array(
                'cards', 'parents', 'subjects', 'subject_characteristics', 'prices',
                'seller_warehouses', 'seller_stocks', 'fbw_stocks', 'finalize', 'done',
            );
            if (!in_array((string) ifset($state['phase'], ''), $supported_phases, true)) {
                $state['phase'] = 'finalize';
            }
            if ((string) $snapshot['status'] === 'building' && $state['phase'] === 'finalize') {
                $state['phase'] = 'fbw_stocks';
            }
        }

        if ((string) $snapshot['status'] === 'ready') {
            $state['phase'] = 'done';
            $this->publishReadySnapshot($snapshot_id, $state);
            return $this->buildResponse($snapshot_id, $state, true);
        }
        if ((string) $snapshot['status'] !== 'building') {
            throw new waException(sprintf(
                _wp('Wildberries snapshot cannot be resumed because its status is %s.'),
                (string) $snapshot['status']
            ));
        }
        if (empty($state['phase'])) {
            $state = $this->createInitialState();
        }
        $retry_at = (int) ifset($state['retry_next_request_at'], 0);
        if ($retry_at > time()) {
            return $this->buildResponse($snapshot_id, $state, false);
        }

        try {
            $done = $this->advancePhase($snapshot_id, $state, $meta);
        } catch (Throwable $e) {
            $this->logger->logError('Wildberries snapshot batch failed.', array(
                'snapshot_id' => (int) $snapshot_id,
                'phase'       => (string) ifset($state['phase'], ''),
                'exception'   => get_class($e),
                'message'     => $e->getMessage(),
            ));
            // Keep the last committed cursor. Upserts make a transiently failed
            // unit safe to retry in another short AJAX request.
            $retry_count = (int) ifset($state['phase_retry_count'], 0);
            if ($this->isRetryableSnapshotError($e) && $retry_count < self::MAX_PHASE_RETRIES) {
                $retry_count++;
                $state['phase_retry_count'] = $retry_count;
                $delay_seconds = min(
                    self::MAX_PHASE_BACKOFF_SECONDS,
                    1 << max(0, $retry_count - 1)
                );
                if (method_exists($e, 'getRetryAfterMs')) {
                    $retry_after_ms = $e->getRetryAfterMs();
                    if ($retry_after_ms !== null) {
                        $delay_seconds = max(
                            $delay_seconds,
                            min(
                                self::MAX_RETRY_AFTER_SECONDS,
                                (int) ceil(max(0, (int) $retry_after_ms) / 1000)
                            )
                        );
                    }
                }
                $state['retry_next_request_at'] = time() + $delay_seconds;
                $meta['build'] = $state;
                $this->repository->saveBuildState($snapshot_id, (string) $state['phase'], $meta);
                return $this->buildResponse($snapshot_id, $state, false);
            }

            $message = _wp('The Wildberries snapshot could not be completed. Review the import log and start a new snapshot.');
            $state['phase'] = 'failed';
            $state['failed_at'] = date('Y-m-d H:i:s');
            $meta['build'] = $state;
            $this->repository->markFailed($snapshot_id, $message, $meta);
            $this->clearBuildingSnapshotReference();
            $response = $this->buildResponse($snapshot_id, $state, false);
            $response['status'] = 'failed';
            $response['message'] = $message;
            return $response;
        }

        $state['phase_retry_count'] = 0;
        $state['retry_next_request_at'] = 0;

        if (!$done) {
            $meta['build'] = $state;
            $this->repository->saveBuildState($snapshot_id, (string) $state['phase'], $meta);
        }
        $this->logger->logInfo('Wildberries snapshot batch completed.', array(
            'snapshot_id' => (int) $snapshot_id,
            'phase'       => (string) ifset($state['phase'], 'done'),
            'memory_mb'   => round(memory_get_usage(true) / 1048576, 1),
            'peak_mb'     => round(memory_get_peak_usage(true) / 1048576, 1),
        ));
        return $this->buildResponse($snapshot_id, $state, $done);
    }

    private function resolveBuildingSnapshotId($snapshot_id)
    {
        $snapshot_id = (int) $snapshot_id;
        if ($snapshot_id > 0) {
            $building_snapshot_id = (int) $this->settings->getBuildingSnapshotId();
            if ($building_snapshot_id <= 0 || $snapshot_id !== $building_snapshot_id) {
                throw new waException(_wp('The Wildberries snapshot has changed. Reload the page and try again.'));
            }
            return $snapshot_id;
        }

        if (method_exists($this->settings, 'getBuildingSnapshotId')) {
            $saved_id = (int) $this->settings->getBuildingSnapshotId();
            if ($saved_id > 0) {
                $saved = $this->repository->getSnapshotsModel()->getByIdSafe($saved_id);
                if ($saved && (string) $saved['status'] === 'building') {
                    return $saved_id;
                }
                if ($saved && (string) $saved['status'] === 'ready'
                    && (int) $this->settings->getCurrentSnapshotId() !== $saved_id
                ) {
                    // Recover a crash between marking ready and publishing the
                    // current snapshot reference.
                    return $saved_id;
                }
                $this->clearBuildingSnapshotReference();
            }
        }

        $state = $this->createInitialState();
        $snapshot_id = $this->repository->createBuildingSnapshot(array('build' => $state), 'cards');
        $this->repository->dropSnapshotData($snapshot_id);
        $this->setBuildingSnapshotReference($snapshot_id);
        $this->logger->logInfo('Wildberries snapshot started.', array('snapshot_id' => $snapshot_id));
        return $snapshot_id;
    }

    private function createInitialState()
    {
        $capacity = $this->settings->refreshServerCapacity();
        $state = array(
            'version'                         => self::STATE_VERSION,
            'phase'                           => 'cards',
            'token_fingerprint'               => $this->getTokenFingerprint(),
            'started_at'                      => date('Y-m-d H:i:s'),
            'server_memory_limit_mb'          => (int) $capacity['memory_limit_mb'],
            'server_memory_unlimited'         => !empty($capacity['memory_limit_unlimited']),
            'product_batch_size'              => (int) $capacity['batch_size'],
            'cards_cursor'                    => array(),
            'cards_loaded'                    => 0,
            'subjects_offset'                 => 0,
            'subjects_loaded'                 => 0,
            'subjects_total'                  => 0,
            'subject_cursor'                  => 0,
            'characteristic_schemas_loaded'   => 0,
            'price_cursor'                    => 0,
            'prices_loaded'                   => 0,
            'prices_status'                   => 'pending',
            'seller_warehouse_offset'         => 0,
            'seller_stock_chrt_cursor'        => 0,
            'seller_stocks_loaded'            => 0,
            'seller_stocks_status'            => 'pending',
            'fbw_stocks_status'               => 'pending',
            'fbw_nm_cursor'                   => 0,
            'fbw_offset'                      => 0,
            'fbw_retry_count'                 => 0,
            'fbw_next_request_at'             => 0,
            'phase_retry_count'                => 0,
            'retry_next_request_at'            => 0,
            'warnings'                        => array(),
        );
        $this->ensurePermissionState($state);
        return $state;
    }

    private function ensureServerCapacity(array &$state)
    {
        if (isset($state['server_memory_limit_mb'], $state['server_memory_unlimited'], $state['product_batch_size'])) {
            return;
        }
        $capacity = $this->settings->refreshServerCapacity();
        $state['server_memory_limit_mb'] = (int) $capacity['memory_limit_mb'];
        $state['server_memory_unlimited'] = !empty($capacity['memory_limit_unlimited']);
        $state['product_batch_size'] = (int) $capacity['batch_size'];
    }

    private function advancePhase($snapshot_id, array &$state, array &$meta)
    {
        switch ((string) ifset($state['phase'], 'cards')) {
            case 'cards':
                $this->collectCardsPage($snapshot_id, $state);
                return false;
            case 'parents':
                $this->collectParents($snapshot_id, $state);
                return false;
            case 'subjects':
                $this->collectSubjectsPage($snapshot_id, $state);
                return false;
            case 'subject_characteristics':
                $this->collectSubjectCharacteristics($snapshot_id, $state);
                return false;
            case 'prices':
                if ($this->isPermissionUnavailable($state, shopMigratePluginWbTokenPermissions::PRICES)) {
                    $state['prices_status'] = 'unavailable';
                    $this->clearPriceData($snapshot_id, $state);
                    $state['phase'] = 'seller_warehouses';
                    return false;
                }
                $this->collectPricesPage($snapshot_id, $state);
                return false;
            case 'seller_warehouses':
                if ($this->isPermissionUnavailable($state, shopMigratePluginWbTokenPermissions::MARKETPLACE)) {
                    $state['seller_stocks_status'] = 'unavailable';
                    $this->clearMarketplaceData($snapshot_id, $state);
                    $state['phase'] = 'fbw_stocks';
                    return false;
                }
                $this->collectSellerWarehouses($snapshot_id, $state);
                return false;
            case 'seller_stocks':
                if ($this->isPermissionUnavailable($state, shopMigratePluginWbTokenPermissions::MARKETPLACE)) {
                    $state['seller_stocks_status'] = 'unavailable';
                    $this->clearMarketplaceData($snapshot_id, $state);
                    $state['phase'] = 'fbw_stocks';
                    return false;
                }
                $this->collectSellerStocksPage($snapshot_id, $state);
                return false;
            case 'fbw_stocks':
                $this->collectWbStocksPage($snapshot_id, $state);
                return false;
            case 'finalize':
                $this->finalizeSnapshot($snapshot_id, $state, $meta);
                return true;
            case 'done':
                $this->publishReadySnapshot($snapshot_id, $state);
                return true;
        }
        throw new waException(sprintf(
            _wp('Unknown Wildberries snapshot phase: %s.'),
            (string) ifset($state['phase'], '')
        ));
    }

    private function collectCardsPage($snapshot_id, array &$state)
    {
        $request_cursor = $this->normalizeCardsCursor(ifset($state['cards_cursor'], array()));
        $response = $this->api->getCardsList(self::CARDS_PAGE_SIZE, $request_cursor);
        $this->ensureSuccessfulEnvelope($response);
        $items = $this->extractCollection($response, array('cards', 'data.cards', 'data.items', 'items', 'data'));

        $cards = array();
        $sizes = array();
        $values = array();
        $synthetic_attributes = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $nm_id = (int) $this->pick($item, array('nmID', 'nmId', 'nm_id', 'id'), 0);
            if ($nm_id <= 0) {
                continue;
            }
            $subject_id = (int) $this->pick($item, array('subjectID', 'subjectId', 'subject_id', 'objectID'), 0);
            $cards[] = array(
                'nm_id'         => $nm_id,
                'imt_id'        => (int) $this->pick($item, array('imtID', 'imtId', 'imt_id'), 0),
                'nm_uuid'       => $this->pick($item, array('nmUUID', 'nmUuid', 'nm_uuid'), null),
                'subject_id'    => $subject_id,
                'parent_id'     => (int) $this->pick($item, array('parentID', 'parentId', 'parent_id'), 0),
                'vendor_code'   => $this->pick($item, array('vendorCode', 'vendor_code', 'article'), ''),
                'brand'         => $this->pick($item, array('brand'), ''),
                'name'          => $this->pick($item, array('title', 'name'), ''),
                'description'   => $this->pick($item, array('description'), null),
                // Raw card data intentionally keeps all photo, video and tag URLs.
                'details'       => $item,
                'wb_created_at' => $this->pick($item, array('createdAt', 'created_at'), null),
                'wb_updated_at' => $this->pick($item, array('updatedAt', 'updated_at'), null),
            );

            foreach ((array) $this->pick($item, array('sizes'), array()) as $size) {
                if (!is_array($size)) {
                    continue;
                }
                $sizes[] = array(
                    'nm_id'     => $nm_id,
                    'chrt_id'   => (int) $this->pick($size, array('chrtID', 'chrtId', 'chrt_id', 'sizeID'), 0),
                    'tech_size' => $this->pick($size, array('techSize', 'tech_size', 'techSizeName'), ''),
                    'wb_size'   => $this->pick($size, array('wbSize', 'wb_size'), ''),
                    'barcodes'  => $this->pick($size, array('skus', 'barcodes'), array()),
                    'details'   => $size,
                );
            }

            foreach ((array) $this->pick($item, array('characteristics', 'charcs'), array()) as $characteristic) {
                if (!is_array($characteristic)) {
                    continue;
                }
                $values[] = array(
                    'nm_id'             => $nm_id,
                    'subject_id'        => $subject_id,
                    'characteristic_id' => (int) $this->pick($characteristic, array('id', 'charcID', 'charcId', 'characteristicID'), 0),
                    'name'              => $this->pick($characteristic, array('name'), ''),
                    'value'             => array_key_exists('value', $characteristic) ? $characteristic['value'] : null,
                );
            }

            $brand = trim((string) $this->pick($item, array('brand'), ''));
            $this->appendSyntheticCharacteristic(
                $synthetic_attributes,
                $values,
                $subject_id,
                $nm_id,
                self::SYNTHETIC_BRAND_ID,
                _wp('Brand'),
                'string',
                null,
                $brand,
                'wb_brand',
                'card.brand'
            );
            $dimensions = $this->pick($item, array('dimensions'), array());
            $dimensions = is_array($dimensions) ? $dimensions : array();
            foreach (array(
                array(self::SYNTHETIC_LENGTH_ID, _wp('Package length, cm'), 'length', 'wb_package_length'),
                array(self::SYNTHETIC_WIDTH_ID, _wp('Package width, cm'), 'width', 'wb_package_width'),
                array(self::SYNTHETIC_HEIGHT_ID, _wp('Package height, cm'), 'height', 'wb_package_height'),
                array(self::SYNTHETIC_WEIGHT_ID, _wp('Gross weight, kg'), 'weightBrutto', 'wb_package_weight'),
            ) as $dimension) {
                $dimension_value = $this->pick($dimensions, array($dimension[2]), null);
                if (!is_numeric($dimension_value) || (float) $dimension_value <= 0) {
                    continue;
                }
                $this->appendSyntheticCharacteristic(
                    $synthetic_attributes,
                    $values,
                    $subject_id,
                    $nm_id,
                    $dimension[0],
                    $dimension[1],
                    'number',
                    $dimension[2] === 'weightBrutto' ? 'kg' : 'cm',
                    $dimension_value,
                    $dimension[3],
                    'card.dimensions.'.$dimension[2]
                );
            }
        }

        $this->repository->getCardsModel()->addBatch($snapshot_id, $cards);
        $this->repository->getSizesModel()->addBatch($snapshot_id, $sizes);
        $this->repository->getAttributesModel()->addBatch($snapshot_id, array_values($synthetic_attributes));
        $this->repository->getAttributeValuesModel()->addBatch($snapshot_id, $values);
        $state['cards_loaded'] = $this->repository->getCardsModel()->countBySnapshot($snapshot_id);

        $response_cursor = $this->extractAssoc($response, array('cursor', 'data.cursor'));
        $next_cursor = $this->normalizeCardsCursor($response_cursor);
        $response_total = (int) $this->pick($response_cursor, array('total'), count($items));
        $last_page = count($items) < self::CARDS_PAGE_SIZE || $response_total < self::CARDS_PAGE_SIZE;
        if (!$items) {
            $last_page = true;
        }
        if (!$last_page && $next_cursor === $request_cursor) {
            throw new waException(
                _wp('Wildberries returned the same product page again. The plugin will retry automatically.'),
                503
            );
        }
        if ($last_page) {
            $state['phase'] = 'parents';
            return;
        }
        $state['cards_cursor'] = $next_cursor;
    }

    private function appendSyntheticCharacteristic(
        array &$attributes,
        array &$values,
        $subject_id,
        $nm_id,
        $characteristic_id,
        $name,
        $type,
        $unit,
        $value,
        $code,
        $source
    ) {
        $subject_id = (int) $subject_id;
        $nm_id = (int) $nm_id;
        $characteristic_id = (int) $characteristic_id;
        if ($subject_id <= 0 || $nm_id <= 0 || $characteristic_id <= 0) {
            return;
        }
        if (is_string($value) && trim($value) === '') {
            return;
        }
        if ($value === null || is_array($value) || is_object($value)) {
            return;
        }
        $key = $subject_id.':'.$characteristic_id;
        $attributes[$key] = array(
            'subject_id'        => $subject_id,
            'characteristic_id' => $characteristic_id,
            'name'              => (string) $name,
            'type'              => (string) $type,
            'unit'              => $unit,
            'is_required'       => 0,
            'max_count'         => 1,
            'meta'              => array(
                'synthetic' => true,
                'code'      => (string) $code,
                'source'    => (string) $source,
            ),
        );
        $values[] = array(
            'nm_id'             => $nm_id,
            'subject_id'        => $subject_id,
            'characteristic_id' => $characteristic_id,
            'name'              => (string) $name,
            'value'             => $value,
        );
    }

    private function collectParents($snapshot_id, array &$state)
    {
        $response = $this->api->getParentCategories();
        $this->ensureSuccessfulEnvelope($response);
        $items = $this->extractCollection($response, array('data', 'items', 'parents', 'data.items'));
        $rows = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rows[] = array(
                'parent_id'  => (int) $this->pick($item, array('id', 'parentID', 'parentId'), 0),
                'name'       => $this->pick($item, array('name'), ''),
                'is_visible' => $this->normalizeBoolean($this->pick($item, array('isVisible', 'is_visible', 'visible'), true)),
            );
        }
        $this->repository->getParentsModel()->addBatch($snapshot_id, $rows);
        $state['phase'] = 'subjects';
    }

    private function collectSubjectsPage($snapshot_id, array &$state)
    {
        $offset = max(0, (int) ifset($state['subjects_offset'], 0));
        $response = $this->api->getSubjects(self::SUBJECTS_PAGE_SIZE, $offset);
        $this->ensureSuccessfulEnvelope($response);
        $items = $this->extractCollection($response, array('data', 'items', 'subjects', 'data.items'));
        $rows = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rows[] = array(
                'subject_id'    => (int) $this->pick($item, array('subjectID', 'subjectId', 'subject_id', 'objectID', 'id'), 0),
                'parent_id'     => (int) $this->pick($item, array('parentID', 'parentId', 'parent_id'), 0),
                'name'          => $this->pick($item, array('subjectName', 'objectName', 'name'), ''),
                'parent_name'   => $this->pick($item, array('parentName', 'parent_name'), ''),
                'products_count'=> 0,
            );
        }
        $this->repository->getSubjectsModel()->addBatch($snapshot_id, $rows);
        $state['subjects_loaded'] = $this->repository->getSubjectsModel()->countBySnapshot($snapshot_id);
        $reported_total = (int) $this->pick($response, array('total', 'totalCount'), 0);
        if (!$reported_total) {
            $data = $this->extractAssoc($response, array('data'));
            $reported_total = (int) $this->pick($data, array('total', 'totalCount'), 0);
        }
        if ($reported_total > 0) {
            $state['subjects_total'] = $reported_total;
        }
        $state['subjects_offset'] = $offset + count($items);

        if (count($items) < self::SUBJECTS_PAGE_SIZE) {
            $this->repository->getCardsModel()->syncParentIds($snapshot_id);
            $this->repository->getSubjectsModel()->refreshProductCounts($snapshot_id);
            $state['phase'] = 'subject_characteristics';
        }
    }

    private function collectSubjectCharacteristics($snapshot_id, array &$state)
    {
        $ids = $this->repository->getSubjectsModel()->getIdsAfter(
            $snapshot_id,
            (int) ifset($state['subject_cursor'], 0),
            1
        );
        if (!$ids) {
            $state['phase'] = 'prices';
            return;
        }
        $subject_id = (int) reset($ids);
        $response = $this->api->getSubjectCharacteristics($subject_id);
        $this->ensureSuccessfulEnvelope($response);
        $items = $this->extractCollection($response, array('data', 'items', 'characteristics', 'data.items'));
        $rows = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rows[] = array(
                'subject_id'        => $subject_id,
                'characteristic_id' => (int) $this->pick($item, array('charcID', 'charcId', 'id', 'characteristicID'), 0),
                'name'              => $this->pick($item, array('name'), ''),
                'type'              => $this->pick($item, array('charcType', 'type'), ''),
                'unit'              => $this->pick($item, array('unitName', 'unit'), null),
                'is_required'       => $this->normalizeBoolean($this->pick($item, array('required', 'isRequired', 'is_required'), false)),
                'max_count'         => (int) $this->pick($item, array('maxCount', 'max_count'), 1),
                'meta'              => $item,
            );
        }
        $this->repository->getAttributesModel()->addBatch($snapshot_id, $rows);
        $state['subject_cursor'] = $subject_id;
        $state['characteristic_schemas_loaded'] = (int) ifset($state['characteristic_schemas_loaded'], 0) + 1;
    }

    private function collectPricesPage($snapshot_id, array &$state)
    {
        $nm_ids = $this->repository->getCardsModel()->getNmIdsAfter(
            $snapshot_id,
            (int) ifset($state['price_cursor'], 0),
            self::PRICES_BATCH_SIZE
        );
        if (!$nm_ids) {
            $state['prices_status'] = 'complete';
            $state['phase'] = 'seller_warehouses';
            return;
        }
        try {
            $response = $this->api->getPricesByNmIds($nm_ids);
        } catch (Throwable $e) {
            if ($this->getHttpStatus($e) === 403) {
                $this->markPermissionUnavailable(
                    $state,
                    shopMigratePluginWbTokenPermissions::PRICES
                );
                $this->clearPriceData($snapshot_id, $state);
                $state['phase'] = 'seller_warehouses';
                return;
            }
            throw $e;
        }
        $this->ensureSuccessfulEnvelope($response);
        $items = $this->extractCollection($response, array('data.listGoods', 'listGoods', 'data.items', 'items', 'data'));
        $rows = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $nm_id = (int) $this->pick($item, array('nmID', 'nmId', 'nm_id'), 0);
            $currency = $this->pick($item, array('currencyIsoCode4217', 'currencyIsoCode', 'currency'), 'RUB');
            $sizes = (array) $this->pick($item, array('sizes'), array());
            if (!$sizes) {
                $sizes = array($item);
            }
            foreach ($sizes as $size) {
                if (!is_array($size)) {
                    continue;
                }
                $rows[] = array(
                    'nm_id'                 => $nm_id,
                    'chrt_id'               => (int) $this->pick($size, array('chrtID', 'chrtId', 'sizeID', 'sizeId'), 0),
                    'tech_size'             => $this->pick($size, array('techSizeName', 'techSize', 'tech_size'), ''),
                    'currency'              => $currency,
                    'price'                 => $this->pick($size, array('price'), $this->pick($item, array('price'), null)),
                    'discounted_price'      => $this->pick($size, array('discountedPrice', 'discounted_price'), $this->pick($item, array('discountedPrice'), null)),
                    'club_discounted_price' => $this->pick($size, array('clubDiscountedPrice', 'club_discounted_price'), null),
                    'details'               => array('good' => $item, 'size' => $size),
                );
            }
        }
        $this->repository->getPricesModel()->addBatch($snapshot_id, $rows);
        $state['price_cursor'] = (int) end($nm_ids);
        $state['prices_loaded'] = $this->repository->getPricesModel()->countBySnapshot($snapshot_id);
        $state['prices_status'] = 'loading';
    }

    private function collectSellerWarehouses($snapshot_id, array &$state)
    {
        try {
            $response = $this->api->getSellerWarehouses();
        } catch (Throwable $e) {
            if ($this->getHttpStatus($e) === 403) {
                $this->markPermissionUnavailable(
                    $state,
                    shopMigratePluginWbTokenPermissions::MARKETPLACE
                );
                $this->clearMarketplaceData($snapshot_id, $state);
                $state['phase'] = 'fbw_stocks';
                return;
            }
            throw $e;
        }
        $this->ensureSuccessfulEnvelope($response);
        $items = $this->extractCollection($response, array('warehouses', 'data.warehouses', 'data.items', 'items', 'data'));
        $rows = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $warehouse_id = (int) $this->pick($item, array('id', 'warehouseID', 'warehouseId', 'officeId'), 0);
            if ($warehouse_id <= 0) {
                continue;
            }
            $rows[] = array(
                'warehouse_key' => 'seller:'.$warehouse_id,
                'source'        => 'seller',
                'warehouse_id'  => $warehouse_id,
                'name'          => $this->pick($item, array('name', 'warehouseName'), ''),
                'details'       => $item,
            );
        }
        $this->repository->getWarehousesModel()->addBatch($snapshot_id, $rows);
        $state['seller_stocks_status'] = 'loading';
        $state['phase'] = 'seller_stocks';
    }

    private function collectSellerStocksPage($snapshot_id, array &$state)
    {
        $offset = max(0, (int) ifset($state['seller_warehouse_offset'], 0));
        $warehouse = $this->repository->getWarehousesModel()->getSourceAtOffset($snapshot_id, 'seller', $offset);
        if (!$warehouse) {
            $state['seller_stocks_status'] = 'complete';
            $state['phase'] = 'fbw_stocks';
            return;
        }
        $chrt_ids = $this->repository->getSizesModel()->getChrtIdsAfter(
            $snapshot_id,
            (int) ifset($state['seller_stock_chrt_cursor'], 0),
            self::SELLER_STOCKS_BATCH_SIZE
        );
        if (!$chrt_ids) {
            $state['seller_warehouse_offset'] = $offset + 1;
            $state['seller_stock_chrt_cursor'] = 0;
            return;
        }

        try {
            $response = $this->api->getSellerStocks((int) $warehouse['warehouse_id'], $chrt_ids);
        } catch (Throwable $e) {
            if ($this->getHttpStatus($e) === 403) {
                $this->markPermissionUnavailable(
                    $state,
                    shopMigratePluginWbTokenPermissions::MARKETPLACE
                );
                $this->clearMarketplaceData($snapshot_id, $state);
                $state['phase'] = 'fbw_stocks';
                return;
            }
            throw $e;
        }
        $this->ensureSuccessfulEnvelope($response);
        $items = $this->extractCollection($response, array('stocks', 'data.stocks', 'data.items', 'items', 'data'));
        $nm_map = $this->repository->getSizesModel()->getNmMapForChrtIds($snapshot_id, $chrt_ids);
        $rows = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $chrt_id = (int) $this->pick($item, array('chrtID', 'chrtId', 'chrt_id', 'sizeID'), 0);
            if ($chrt_id <= 0) {
                continue;
            }
            $rows[] = array(
                'warehouse_key'     => (string) $warehouse['warehouse_key'],
                'nm_id'             => (int) $this->pick($item, array('nmID', 'nmId', 'nm_id'), ifset($nm_map[$chrt_id], 0)),
                'chrt_id'           => $chrt_id,
                'quantity'          => $this->pick($item, array('amount', 'quantity', 'stockCount', 'count'), 0),
                'in_way_to_client'   => $this->pick($item, array('inWayToClient', 'in_way_to_client'), 0),
                'in_way_from_client' => $this->pick($item, array('inWayFromClient', 'in_way_from_client'), 0),
                'details'           => $item,
            );
        }
        $this->repository->getStocksModel()->addBatch($snapshot_id, $rows);
        $state['seller_stock_chrt_cursor'] = (int) end($chrt_ids);
        $state['seller_stocks_loaded'] = $this->repository->getStocksModel()->countBySnapshot($snapshot_id);
    }

    private function collectWbStocksPage($snapshot_id, array &$state)
    {
        try {
            if (empty($state['fbw_stocks_status']) || $state['fbw_stocks_status'] === 'pending') {
                // Do not adopt legacy, possibly partial FBW rows.
                $this->clearWbWarehouseData($snapshot_id);
                $report = (new shopMigratePluginWbTokenPermissions($this->settings->getToken()))->inspect();
                if (empty($report['permissions'][shopMigratePluginWbTokenPermissions::ANALYTICS])) {
                    $state['api_permissions'][shopMigratePluginWbTokenPermissions::ANALYTICS] = 'unavailable';
                    $this->skipWbWarehouseData($snapshot_id, $state, _wp('WB warehouse stocks were not collected because Analytics access is unavailable. Other data will still be imported.'));
                    return;
                }
                $state['fbw_stocks_status'] = 'loading';
            }
            $nm_ids = $this->repository->getCardsModel()->getNmIdsAfter(
                $snapshot_id, (int) ifset($state['fbw_nm_cursor'], 0), self::FBW_PRODUCTS_BATCH_SIZE
            );
            if (!$nm_ids) {
                $state['fbw_stocks_status'] = 'complete';
                $state['fbw_next_request_at'] = 0;
                $state['phase'] = 'finalize';
                return;
            }
            if ((int) ifset($state['fbw_next_request_at'], 0) > time()) {
                return;
            }
            $offset = (int) ifset($state['fbw_offset'], 0);
            $limit = shopMigratePluginWbApiClient::MAX_FBW_PAGE_SIZE;
            $response = $this->api->getWbStocksPage($nm_ids, $limit, $offset);
            $this->ensureSuccessfulEnvelope($response);
            $items = $this->getPath($response, 'data.items');
            if (!is_array($items) || !$this->isList($items) || count($items) > $limit) {
                throw new waException(_wp('WB warehouse API returned an invalid inventory response.'), 422);
            }
            $signature = hash('sha256', json_encode($items));
            if ($items && $offset > 0 && $signature === (string) ifset($state['fbw_page_signature'], '')) {
                throw new waException(_wp('WB warehouse API repeated the same inventory page.'), 422);
            }
            list($warehouses, $stocks) = $this->normalizeWbStocks($snapshot_id, $nm_ids, $items);
            $this->repository->getWarehousesModel()->addBatch($snapshot_id, $warehouses);
            $this->repository->getStocksModel()->addBatch($snapshot_id, $stocks);
            $state['fbw_retry_count'] = 0;
            $state['fbw_page_signature'] = $signature;
            $state['fbw_next_request_at'] = time() + shopMigratePluginWbApiClient::FBW_REQUEST_INTERVAL;
            if (count($items) < $limit) {
                $state['fbw_nm_cursor'] = (int) end($nm_ids);
                $state['fbw_offset'] = 0;
                $state['fbw_page_signature'] = '';
            } else {
                $state['fbw_offset'] = $offset + count($items);
            }
        } catch (Throwable $e) {
            $this->logger->logWarning('WB warehouse inventory batch failed.', array(
                'snapshot_id' => (int) $snapshot_id,
                'message' => $e->getMessage(),
            ));
            $attempts = (int) ifset($state['fbw_retry_count'], 0);
            if ($this->isRetryableSnapshotError($e) && $attempts < self::MAX_FBW_RETRIES) {
                $delay = shopMigratePluginWbApiClient::FBW_REQUEST_INTERVAL;
                if (method_exists($e, 'getRetryAfterMs')) {
                    $delay = max($delay, (int) ceil((int) $e->getRetryAfterMs() / 1000));
                }
                // A long service cooldown must not hold up the entire catalog.
                if ($delay <= self::MAX_RETRY_AFTER_SECONDS) {
                    $state['fbw_retry_count'] = $attempts + 1;
                    $state['fbw_next_request_at'] = time() + $delay;
                    return;
                }
            }
            $message = in_array($this->getHttpStatus($e), array(401, 402, 403), true)
                ? _wp('WB warehouse stocks are unavailable for this token or account. Other data will still be imported.')
                : _wp('WB warehouse stocks could not be collected. Partial WB warehouse data was discarded; other data will still be imported.');
            $this->skipWbWarehouseData($snapshot_id, $state, $message);
        }
    }

    private function normalizeWbStocks($snapshot_id, array $nm_ids, array $items)
    {
        $chrt_ids = array();
        $parsed = array();
        $allowed_nm_ids = array_fill_keys($nm_ids, true);
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new waException(_wp('WB warehouse API returned an invalid inventory response.'), 422);
            }
            $nm_id = $this->pick($item, array('nmId', 'nmID'), null);
            $chrt_id = $this->pick($item, array('chrtId', 'chrtID'), null);
            $warehouse_id = $this->pick($item, array('warehouseId', 'warehouseID'), null);
            $quantity = ifset($item['quantity'], null);
            if (!is_numeric($nm_id) || (int) $nm_id <= 0 || !isset($allowed_nm_ids[(int) $nm_id])
                || !is_numeric($chrt_id) || (int) $chrt_id <= 0
                || !is_numeric($warehouse_id) || (int) $warehouse_id === 0
                || !is_numeric($quantity) || !is_finite((float) $quantity) || (float) $quantity < 0
            ) {
                throw new waException(_wp('WB warehouse API returned an invalid inventory response.'), 422);
            }
            $key = 'wb:'.(int) $warehouse_id;
            $parsed[] = array('item' => $item, 'key' => $key, 'nm_id' => (int) $nm_id, 'chrt_id' => (int) $chrt_id, 'warehouse_id' => (int) $warehouse_id);
            $chrt_ids[] = (int) $chrt_id;
        }
        $nm_map = $this->repository->getSizesModel()->getNmMapForChrtIds($snapshot_id, $chrt_ids);
        $warehouses = array();
        $stocks = array();
        foreach ($parsed as $row) {
            // A size added in WB after the cards were collected is not part of
            // this snapshot. Never guess its SKU by name, barcode or position.
            if (!isset($nm_map[$row['chrt_id']])) {
                continue;
            }
            if ((int) $nm_map[$row['chrt_id']] !== $row['nm_id']) {
                throw new waException(_wp('WB warehouse API returned an invalid inventory response.'), 422);
            }
            $item = $row['item'];
            $name = isset($item['warehouseName']) && is_scalar($item['warehouseName']) ? trim((string) $item['warehouseName']) : '';
            $warehouses[$row['key']] = array(
                'warehouse_key' => $row['key'],
                'source' => 'wb',
                // The schema is unsigned. Signed/grouped WB identifiers remain
                // intact in the stable warehouse_key and the source details.
                'warehouse_id' => max(0, $row['warehouse_id']),
                'name' => 'WB / '.($name !== '' ? $name : (string) $row['warehouse_id']),
                'details' => array('warehouseId' => $row['warehouse_id'], 'warehouseName' => $name, 'regionName' => ifset($item['regionName'], '')),
            );
            $stocks[] = array(
                'warehouse_key' => $row['key'],
                'nm_id' => $row['nm_id'],
                'chrt_id' => $row['chrt_id'],
                // In-transit goods are not available inventory.
                'quantity' => $item['quantity'],
                'in_way_to_client' => is_numeric(ifset($item['inWayToClient'])) ? $item['inWayToClient'] : 0,
                'in_way_from_client' => is_numeric(ifset($item['inWayFromClient'])) ? $item['inWayFromClient'] : 0,
                'details' => $item,
            );
        }
        return array(array_values($warehouses), $stocks);
    }

    private function clearWbWarehouseData($snapshot_id)
    {
        $this->repository->getStocksModel()->deleteBySource($snapshot_id, 'wb');
        $this->repository->getWarehousesModel()->deleteBySource($snapshot_id, 'wb');
    }

    private function skipWbWarehouseData($snapshot_id, array &$state, $message)
    {
        $state['fbw_stocks_status'] = 'unavailable';
        $state['fbw_next_request_at'] = 0;
        $this->clearWbWarehouseData($snapshot_id);
        $this->appendWarning($state, $message);
        $state['phase'] = 'finalize';
    }

    private function finalizeSnapshot($snapshot_id, array &$state, array &$meta)
    {
        $this->assertTokenFingerprint($state);
        $previous_snapshot_id = (int) $this->settings->getCurrentSnapshotId();
        if ($previous_snapshot_id > 0 && $previous_snapshot_id !== (int) $snapshot_id) {
            $this->repository->copyMappings($previous_snapshot_id, $snapshot_id);
        }
        $this->carryForwardWbWarehouseHistory($previous_snapshot_id, $snapshot_id, $state);
        $state['phase'] = 'done';
        $state['finished_at'] = date('Y-m-d H:i:s');
        $used_subjects = (int) $this->repository->getSubjectsModel()
            ->select('COUNT(*)')
            ->where('snapshot_id = ? AND products_count > 0', (int) $snapshot_id)
            ->fetchField();
        $used_parents = (int) $this->repository->getSubjectsModel()
            ->select('COUNT(DISTINCT parent_id)')
            ->where('snapshot_id = ? AND products_count > 0', (int) $snapshot_id)
            ->fetchField();
        $counts = array(
            'parents'          => $this->repository->getParentsModel()->countBySnapshot($snapshot_id),
            'subjects'         => $this->repository->getSubjectsModel()->countBySnapshot($snapshot_id),
            'cards'            => $this->repository->getCardsModel()->countBySnapshot($snapshot_id),
            'product_groups'   => $this->repository->getCardsModel()->countGroupsBySnapshot($snapshot_id),
            'used_parents'     => $used_parents,
            'used_subjects'    => $used_subjects,
            'import_categories'=> $used_parents + $used_subjects,
            'sizes'            => $this->repository->getSizesModel()->countBySnapshot($snapshot_id),
            'attributes'       => $this->repository->getAttributesModel()->countBySnapshot($snapshot_id),
            'attribute_values' => $this->repository->getAttributeValuesModel()->countBySnapshot($snapshot_id),
            'prices'           => $this->repository->getPricesModel()->countBySnapshot($snapshot_id),
            'warehouses'       => $this->repository->getWarehousesModel()->countBySnapshot($snapshot_id),
            'stocks'           => $this->repository->getStocksModel()->countBySnapshot($snapshot_id),
        );
        $meta['build'] = $state;
        $meta['counts'] = $counts;
        $meta['warnings'] = array_values((array) ifset($state['warnings'], array()));
        $this->repository->markReady($snapshot_id, $meta, 'done');
        $this->publishReadySnapshot($snapshot_id, $state);
        try {
            $this->repository->pruneSnapshots(array($snapshot_id), 2);
        } catch (Throwable $e) {
            // Retention is maintenance after a successfully published
            // snapshot; a cleanup failure must not invalidate source data.
            $this->logger->logWarning(_wp('Old Wildberries snapshots could not be removed.'), array(
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
            ));
        }
        $this->logger->logInfo('Wildberries snapshot is ready.', array(
            'snapshot_id' => (int) $snapshot_id,
            'counts'      => $counts,
        ));
    }

    private function carryForwardWbWarehouseHistory($previous_snapshot_id, $snapshot_id, array &$state)
    {
        $state['fbw_warehouse_history'] = (string) ifset($state['fbw_stocks_status'], '') === 'complete';
        if ((int) $previous_snapshot_id <= 0 || (int) $previous_snapshot_id === (int) $snapshot_id) {
            return;
        }
        $snapshots_model = $this->repository->getSnapshotsModel();
        $previous = $snapshots_model->getByIdSafe($previous_snapshot_id);
        if (!$previous || (string) $previous['status'] !== 'ready') {
            return;
        }
        $previous_meta = $snapshots_model->decodeMeta($previous);
        $previous_build = (array) ifset($previous_meta['build'], array());
        $fingerprint = (string) ifset($state['token_fingerprint'], '');
        if ($fingerprint === ''
            || !hash_equals($fingerprint, (string) ifset($previous_build['token_fingerprint'], ''))
            || ((string) ifset($previous_build['fbw_stocks_status'], '') !== 'complete'
                && empty($previous_build['fbw_warehouse_history']))
        ) {
            return;
        }

        // Carry only the verified warehouse directory, never old quantities.
        // Preserve it even when FBW is unavailable so repeated failures and
        // snapshot pruning cannot lose warehouses that later need zeroing.
        // Import and UI still use FBW rows only when its current status is complete.
        $this->repository->getWarehousesModel()->copyMissingWbWarehouses($previous_snapshot_id, $snapshot_id);
        $state['fbw_warehouse_history'] = true;
    }

    private function publishReadySnapshot($snapshot_id, array &$state)
    {
        $this->assertTokenFingerprint($state);
        if (method_exists($this->settings, 'setCurrentSnapshotId')) {
            $this->settings->setCurrentSnapshotId((int) $snapshot_id);
        }
        $this->clearBuildingSnapshotReference();
    }

    private function assertTokenFingerprint(array &$state)
    {
        $current = $this->getTokenFingerprint();
        $expected = (string) ifset($state['token_fingerprint'], '');
        if ($expected === '') {
            // Backward-compatible adoption for an in-progress development
            // snapshot that predates token-bound state.
            $state['token_fingerprint'] = $current;
            return;
        }
        if (!hash_equals($expected, $current)) {
            throw new waException(_wp('The Wildberries API token changed while the snapshot was loading. Start a new snapshot.'));
        }
    }

    private function getTokenFingerprint()
    {
        return hash('sha256', (string) $this->settings->getToken());
    }

    private function assertCollectionRevision(array $options)
    {
        if (!array_key_exists('collection_revision', $options)) {
            return;
        }
        $expected = max(0, (int) $options['collection_revision']);
        // waAppSettingsModel::get() has a process-local static cache. A
        // collection request may outlive a concurrent Save request, therefore
        // this decisive check under the operation lock must read the DB.
        if ($expected !== $this->settings->getFreshCollectionRevision()) {
            throw new waException(_wp('Wildberries data collection was stopped. Start it again.'));
        }
    }

    private function setBuildingSnapshotReference($snapshot_id)
    {
        if (method_exists($this->settings, 'setBuildingSnapshotId')) {
            $this->settings->setBuildingSnapshotId((int) $snapshot_id);
        }
    }

    private function clearBuildingSnapshotReference()
    {
        if (method_exists($this->settings, 'clearBuildingSnapshotReference')) {
            $this->settings->clearBuildingSnapshotReference();
        } elseif (method_exists($this->settings, 'setBuildingSnapshotId')) {
            $this->settings->setBuildingSnapshotId(0);
        }
    }

    private function ensureSuccessfulEnvelope($response)
    {
        if (!is_array($response)) {
            throw new waException(_wp('Wildberries API returned an invalid response.'));
        }
        if (!empty($response['error'])) {
            $message = $this->pick($response, array('errorText', 'message', 'detail'), _wp('Wildberries API returned an error.'));
            throw new waException((string) $message);
        }
    }

    private function extractCollection(array $response, array $paths)
    {
        foreach ($paths as $path) {
            $value = $this->getPath($response, $path);
            if (is_array($value) && $this->isList($value)) {
                return $value;
            }
        }
        return $this->isList($response) ? $response : array();
    }

    private function extractAssoc(array $response, array $paths)
    {
        foreach ($paths as $path) {
            $value = $this->getPath($response, $path);
            if (is_array($value)) {
                return $value;
            }
        }
        return array();
    }

    private function getPath(array $source, $path)
    {
        $value = $source;
        foreach (explode('.', (string) $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    private function isList(array $value)
    {
        if (!$value) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function pick(array $source, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                return $source[$key];
            }
        }
        return $default;
    }

    private function normalizeCardsCursor($cursor)
    {
        if (!is_array($cursor)) {
            return array();
        }
        $result = array();
        $updated_at = $this->pick($cursor, array('updatedAt', 'updated_at'), null);
        $nm_id = (int) $this->pick($cursor, array('nmID', 'nmId', 'nm_id'), 0);
        if ($updated_at !== null && $updated_at !== '') {
            $result['updatedAt'] = (string) $updated_at;
        }
        if ($nm_id > 0) {
            $result['nmID'] = $nm_id;
        }
        return $result;
    }

    private function normalizeBoolean($value)
    {
        if (is_string($value)) {
            return !in_array(strtolower(trim($value)), array('', '0', 'false', 'no', 'off'), true);
        }
        return (bool) $value;
    }

    private function ensurePermissionState(array &$state)
    {
        if (!isset($state['api_permissions']) || !is_array($state['api_permissions'])) {
            $state['api_permissions'] = array();
        }
        foreach (array(
            shopMigratePluginWbTokenPermissions::CONTENT,
            shopMigratePluginWbTokenPermissions::PRICES,
            shopMigratePluginWbTokenPermissions::MARKETPLACE,
            shopMigratePluginWbTokenPermissions::ANALYTICS,
        ) as $code) {
            if (!isset($state['api_permissions'][$code])) {
                $state['api_permissions'][$code] = 'available';
            }
        }
        if (!isset($state['prices_status'])) {
            $state['prices_status'] = in_array(
                (string) ifset($state['phase'], ''),
                array('seller_warehouses', 'seller_stocks', 'fbw_stocks', 'finalize', 'done'),
                true
            ) ? 'complete' : 'pending';
        }
    }

    private function isPermissionUnavailable(array $state, $code)
    {
        return isset($state['api_permissions'][$code])
            && (string) $state['api_permissions'][$code] === 'unavailable';
    }

    private function markPermissionUnavailable(array &$state, $code)
    {
        $this->ensurePermissionState($state);
        $state['api_permissions'][$code] = 'unavailable';
        if ($code === shopMigratePluginWbTokenPermissions::PRICES) {
            $state['prices_status'] = 'unavailable';
            $this->appendWarning(
                $state,
                _wp('Wildberries prices were not collected because the API token does not allow access to prices and discounts.')
            );
        } elseif ($code === shopMigratePluginWbTokenPermissions::MARKETPLACE) {
            $state['seller_stocks_status'] = 'unavailable';
            $this->appendWarning(
                $state,
                _wp('Wildberries warehouses and stocks were not collected because the API token does not allow access to Marketplace data.')
            );
        }
    }

    private function clearPriceData($snapshot_id, array &$state)
    {
        // A partial price set must never look authoritative during product updates.
        $this->repository->getPricesModel()->deleteBySnapshot($snapshot_id);
        $state['price_cursor'] = 0;
        $state['prices_loaded'] = 0;
    }

    private function clearMarketplaceData($snapshot_id, array &$state)
    {
        // Seller warehouses and their stocks form one unit. If access is lost,
        // omit the whole unit instead of exposing a partial warehouse mapping.
        $this->repository->getStocksModel()->deleteBySource($snapshot_id, 'seller');
        $this->repository->getWarehousesModel()->deleteBySource($snapshot_id, 'seller');
        $state['seller_warehouse_offset'] = 0;
        $state['seller_stock_chrt_cursor'] = 0;
        $state['seller_stocks_loaded'] = 0;
    }

    private function getHttpStatus(Throwable $e)
    {
        $code = (int) $e->getCode();
        if ($code >= 100 && $code <= 599) {
            return $code;
        }
        if (preg_match('/HTTP(?:\s+status)?\s*(\d{3})/i', $e->getMessage(), $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }

    private function isRetryableSnapshotError(Throwable $e)
    {
        $status = $this->getHttpStatus($e);
        return $status === 0
            || $status === 408
            || $status === 429
            || ($status >= 500 && $status <= 599);
    }

    private function appendWarning(array &$state, $message)
    {
        if (!isset($state['warnings']) || !is_array($state['warnings'])) {
            $state['warnings'] = array();
        }
        $message = trim((string) $message);
        if ($message === '' || in_array($message, $state['warnings'], true)) {
            return;
        }
        if (count($state['warnings']) < self::MAX_WARNINGS) {
            $state['warnings'][] = $message;
        }
        $this->logger->logWarning($message);
    }

    private function buildResponse($snapshot_id, array $state, $done)
    {
        $progress = $done ? 100.0 : $this->calculateProgress($snapshot_id, $state);
        list($processed, $total) = $this->getProcessedAndTotal($snapshot_id, $state);
        $response = array(
            'snapshot_id' => (int) $snapshot_id,
            'done'        => (bool) $done,
            'status'      => $done ? 'ready' : ((string) ifset($state['phase'], '') === 'failed' ? 'failed' : 'building'),
            'phase'       => (string) ifset($state['phase'], 'done'),
            'progress'    => $progress,
            'processed'   => (int) $processed,
            'total'       => (int) $total,
            'message'     => $this->getPhaseMessage($state, $progress),
            'server_memory_mb' => (int) ifset($state['server_memory_limit_mb'], 0),
            'server_memory_unlimited' => !empty($state['server_memory_unlimited']),
            'batch_size'  => (int) ifset($state['product_batch_size'], shopMigratePluginWbSettings::PRODUCT_BATCH_DEFAULT),
        );
        if (!empty($state['warnings'])) {
            $response['warnings'] = array_values($state['warnings']);
            $response['warning'] = implode(' ', $response['warnings']);
        }
        $wait_seconds = max(
            0,
            (int) ifset($state['retry_next_request_at'], 0) - time()
        );
        if ((string) ifset($state['phase'], '') === 'fbw_stocks') {
            $wait_seconds = max($wait_seconds, (int) ifset($state['fbw_next_request_at'], 0) - time());
        }
        if ($wait_seconds > 0) {
            $response['retry_after_ms'] = $wait_seconds * 1000;
        }
        return $response;
    }

    private function calculateProgress($snapshot_id, array $state)
    {
        $phase = (string) ifset($state['phase'], 'cards');
        $ranges = array(
            'cards'                   => array(0, 35),
            'parents'                 => array(35, 38),
            'subjects'                => array(38, 46),
            'subject_characteristics' => array(46, 60),
            'prices'                  => array(60, 70),
            'seller_warehouses'       => array(70, 73),
            'seller_stocks'           => array(73, 85),
            'fbw_stocks'              => array(85, 98),
            'finalize'                => array(98, 100),
        );
        if (!isset($ranges[$phase])) {
            return $phase === 'done' ? 100.0 : 0.0;
        }
        list($start, $end) = $ranges[$phase];
        list($processed, $total) = $this->getProcessedAndTotal($snapshot_id, $state);
        $ratio = $total > 0 ? min(1, $processed / $total) : 0;
        if (in_array($phase, array('parents', 'seller_warehouses', 'finalize'), true)) {
            $ratio = 0;
        }
        return round($start + ($end - $start) * $ratio, 1);
    }

    private function getProcessedAndTotal($snapshot_id, array $state)
    {
        $phase = (string) ifset($state['phase'], '');
        $cards_total = max(0, $this->repository->getCardsModel()->countBySnapshot($snapshot_id));
        $subjects_total = max(0, $this->repository->getSubjectsModel()->countBySnapshot($snapshot_id));
        switch ($phase) {
            case 'cards':
                return array((int) ifset($state['cards_loaded'], 0), 0);
            case 'subjects':
                return array((int) ifset($state['subjects_loaded'], 0), (int) ifset($state['subjects_total'], 0));
            case 'subject_characteristics':
                $used_subjects_total = (int) $this->repository->getSubjectsModel()
                    ->select('COUNT(*)')
                    ->where('snapshot_id = ? AND products_count > 0', (int) $snapshot_id)
                    ->fetchField();
                return array((int) ifset($state['characteristic_schemas_loaded'], 0), $used_subjects_total);
            case 'prices':
                return array($this->countIdsUpToCursor($snapshot_id, (int) ifset($state['price_cursor'], 0)), $cards_total);
            case 'fbw_stocks':
                return array($this->countIdsUpToCursor($snapshot_id, (int) ifset($state['fbw_nm_cursor'], 0)), $cards_total);
            case 'seller_stocks':
                $warehouses_total = $this->repository->getWarehousesModel()->countBySource($snapshot_id, 'seller');
                $sizes_total = $this->repository->getSizesModel()->countBySnapshot($snapshot_id);
                $total = $warehouses_total * $sizes_total;
                $processed = (int) ifset($state['seller_warehouse_offset'], 0) * $sizes_total
                    + $this->repository->getSizesModel()->countUpToCursor(
                        $snapshot_id,
                        (int) ifset($state['seller_stock_chrt_cursor'], 0)
                    );
                return array(min($processed, $total), $total);
        }
        return array(0, 0);
    }

    private function countIdsUpToCursor($snapshot_id, $cursor)
    {
        // Cursor IDs are sparse WB identifiers, so use a bounded count query rather than the ID value.
        $cursor = (int) $cursor;
        if ($cursor <= 0) {
            return 0;
        }
        return (int) $this->repository->getCardsModel()->select('COUNT(*)')
            ->where('snapshot_id = ? AND nm_id <= ?', (int) $snapshot_id, $cursor)
            ->fetchField();
    }

    private function getPhaseMessage(array $state, $progress)
    {
        if ((string) ifset($state['phase'], '') === 'fbw_stocks'
            && (int) ifset($state['fbw_next_request_at'], 0) > time()
        ) {
            return sprintf(_wp('Waiting %d seconds before the next WB warehouse request.'), (int) $state['fbw_next_request_at'] - time());
        }
        if ((int) ifset($state['retry_next_request_at'], 0) > time()) {
            return _wp('Wildberries API is temporarily unavailable. The snapshot will retry automatically.');
        }
        $labels = array(
            'cards'                   => _wp('product cards'),
            'parents'                 => _wp('parent categories'),
            'subjects'                => _wp('categories and subcategories'),
            'subject_characteristics' => _wp('characteristic schemas'),
            'prices'                  => _wp('prices'),
            'seller_warehouses'       => _wp('seller warehouses'),
            'seller_stocks'           => _wp('seller warehouse stocks'),
            'fbw_stocks'              => _wp('WB warehouse stocks (FBW)'),
            'finalize'                => _wp('finalization'),
            'done'                    => _wp('complete'),
        );
        $phase = (string) ifset($state['phase'], 'done');
        return sprintf(
            _wp('Collecting Wildberries data before import: %s%% (%s)'),
            $progress,
            ifset($labels[$phase], $phase)
        );
    }
}
