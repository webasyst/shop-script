<?php

/**
 * Imports immutable WB snapshots in resumable product-sized units.
 *
 * One unit is a complete WB imtID group (or nmID fallback), but its work is
 * checkpointed after product creation, bounded variant chunks, finalization,
 * and every image chunk. A single AJAX request may cross several checkpoints
 * and complete several products while its batch and time budgets allow it.
 * Cancellation is observed only between complete products, so an already
 * started product (including its images) is finished.
 */
class shopMigratePluginWbImporter
{
    const KIND_PRODUCTS = 'products';
    const KIND_IMAGES = 'images';
    const STATUS_RUNNING = 'running';
    const STATUS_CANCEL_REQUESTED = 'cancel_requested';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    // Keep every AJAX step comfortably below a common 30-second PHP limit.
    const HARD_DEADLINE_SECONDS = 22;
    const UNIT_START_RESERVE_SECONDS = 4;
    const VARIANT_BATCH_SIZE = 5;
    const MAX_SELECTOR_CANDIDATES = 12;
    const MAX_SELECTOR_FEATURES = 4;
    const SYNTHETIC_FEATURE_CREATE_ATTEMPTS = 5;
    const FEATURE_CODE_MAX_LENGTH = 64;

    private $repository;
    private $settings;
    private $type_mapper;
    private $category_mapper;
    private $stock_mapper;
    private $feature_mapper;
    private $logger;

    private $runs_model;
    private $unpriced_products_model;
    private $snapshots_model;
    private $cards_model;
    private $sizes_model;
    private $attributes_model;
    private $attribute_values_model;
    private $prices_model;
    private $warehouses_model;
    private $stocks_model;
    private $product_map_model;
    private $sku_map_model;
    private $image_map_model;

    private $product_model;
    private $sku_model;
    private $category_products_model;
    private $product_features_model;
    private $selectable_features_model;
    private $feature_model;
    private $type_features_model;
    private $product_stocks_model;
    private $stock_model;
    private $currency_model;
    private $image_worker;
    private $deadline;
    private $synthetic_features = array();
    private $snapshot_prices_available = array();
    private $operation_lock;

    public function __construct(
        shopMigratePluginWbSnapshotRepository $repository,
        shopMigratePluginWbSettings $settings,
        shopMigratePluginWbTypeMapper $type_mapper,
        shopMigratePluginWbCategoryMapper $category_mapper,
        shopMigratePluginWbStockMapper $stock_mapper,
        shopMigratePluginWbFeatureMapper $feature_mapper,
        $logger = null
    ) {
        $this->repository = $repository;
        $this->settings = $settings;
        $this->type_mapper = $type_mapper;
        $this->category_mapper = $category_mapper;
        $this->stock_mapper = $stock_mapper;
        $this->feature_mapper = $feature_mapper;
        $this->logger = $logger;

        $this->runs_model = new shopMigratePluginWbRunsModel();
        $this->unpriced_products_model = new shopMigratePluginWbUnpricedProductsModel();
        $this->snapshots_model = new shopMigratePluginWbSnapshotsModel();
        $this->cards_model = new shopMigratePluginWbCardsModel();
        $this->sizes_model = new shopMigratePluginWbSizesModel();
        $this->attributes_model = new shopMigratePluginWbAttributesModel();
        $this->attribute_values_model = new shopMigratePluginWbAttributeValuesModel();
        $this->prices_model = new shopMigratePluginWbPricesModel();
        $this->warehouses_model = new shopMigratePluginWbWarehousesModel();
        $this->stocks_model = new shopMigratePluginWbStocksModel();
        $this->product_map_model = new shopMigratePluginWbProductMapModel();
        $this->sku_map_model = new shopMigratePluginWbSkuMapModel();
        $this->image_map_model = new shopMigratePluginWbImageMapModel();

        $this->product_model = new shopProductModel();
        $this->sku_model = new shopProductSkusModel();
        $this->category_products_model = new shopCategoryProductsModel();
        $this->product_features_model = new shopProductFeaturesModel();
        $this->selectable_features_model = new shopProductFeaturesSelectableModel();
        $this->feature_model = new shopFeatureModel();
        $this->type_features_model = new shopTypeFeaturesModel();
        $this->product_stocks_model = new shopProductStocksModel();
        $this->stock_model = new shopStockModel();
        $this->currency_model = new shopCurrencyModel();
        $this->image_worker = new shopMigratePluginWbImageWorker($logger);
        $this->operation_lock = new shopMigratePluginWbOperationLock($this->runs_model);
    }

    public function start($snapshot_id, $kind = self::KIND_PRODUCTS, array $options = array())
    {
        $snapshot_id = (int) $snapshot_id;
        $lock_key = 'global';
        if (!$this->acquireLock($lock_key)) {
            throw new waException(_wp('Another Wildberries import is being started.'));
        }
        try {
            return $this->startLocked($snapshot_id, $kind, $options);
        } finally {
            $this->releaseLock($lock_key);
        }
    }

    private function startLocked($snapshot_id, $kind, array $options)
    {
        $snapshot = $this->snapshots_model->getById($snapshot_id);
        if (!$snapshot || (string) $snapshot['status'] !== 'ready') {
            throw new waException(_wp('A completed Wildberries snapshot is required before import.'));
        }
        $kind = in_array($kind, array(self::KIND_PRODUCTS, self::KIND_IMAGES), true)
            ? $kind
            : self::KIND_PRODUCTS;

        $active = $this->runs_model->select('*')
            ->where("status IN ('running','cancel_requested')")
            ->order('id DESC')
            ->limit(1)
            ->fetchAssoc();
        if ($active) {
            return $this->buildResponse($active);
        }

        $defaults = $this->settings->getImportOptions();
        $options = array_merge($defaults, $options);
        // Batch size is derived from the PHP memory limit while building the
        // snapshot. Do not trust a browser-provided override.
        $options['batch_size'] = $this->settings->getProductBatchSize();
        $options['mode'] = in_array(ifset($options['mode'], ''), array('auto', 'manual'), true)
            ? $options['mode']
            : 'auto';
        $options['feature_mode'] = in_array(
            ifset($options['feature_mode'], ''),
            array(shopMigratePluginWbSettings::FEATURE_MODE_AUTO, shopMigratePluginWbSettings::FEATURE_MODE_SKIP),
            true
        ) ? $options['feature_mode'] : shopMigratePluginWbSettings::FEATURE_MODE_AUTO;
        $options['feature_force_text'] = $options['mode'] === shopMigratePluginWbSettings::MODE_MANUAL
            && !empty($options['feature_force_text']);
        $options['image_mode'] = in_array(ifset($options['image_mode'], ''), $this->settings->getImageModes(), true)
            ? $options['image_mode']
            : shopMigratePluginWbSettings::IMAGE_MODE_IMMEDIATE;
        if ($kind === self::KIND_IMAGES) {
            $options['image_mode'] = shopMigratePluginWbSettings::IMAGE_MODE_IMMEDIATE;
        }

        $total = $this->countGroups($snapshot_id, $kind === self::KIND_IMAGES);
        if ($kind === self::KIND_IMAGES && $total <= 0) {
            throw new waException(_wp('Import products before importing images separately.'));
        }
        $now = date('Y-m-d H:i:s');
        $counters = array(
            'total'              => $total,
            'processed'          => 0,
            'created'            => 0,
            'updated'            => 0,
            'skipped'            => 0,
            'failed'             => 0,
            'images_imported'    => 0,
            'images_skipped'     => 0,
            'image_errors'       => 0,
            'last_group_id'      => 0,
            'current_stage'      => '',
            'current_product_id' => 0,
            'current_product_created' => 0,
            'variant_offset'     => 0,
            'messages'           => array(),
        );
        $run_id = $this->runs_model->insert(array(
            'snapshot_id'      => $snapshot_id,
            'kind'             => $kind,
            'status'           => self::STATUS_RUNNING,
            'cursor'           => 0,
            'current_group_id' => 0,
            'image_offset'     => 0,
            'cancel_requested' => 0,
            'options'          => $this->encodeJson($options),
            'counters'         => $this->encodeJson($counters),
            'error'            => null,
            'created_at'       => $now,
            'updated_at'       => $now,
            'finished_at'      => null,
        ));
        return $this->buildResponse($this->runs_model->getById($run_id));
    }

    public function requestCancel($run_id)
    {
        $run = $this->getRun($run_id);
        if ($this->isTerminal($run['status'])) {
            return $this->buildResponse($run);
        }
        $this->runs_model->updateById($run['id'], array(
            'status'           => self::STATUS_CANCEL_REQUESTED,
            'cancel_requested' => 1,
            'updated_at'       => date('Y-m-d H:i:s'),
        ));
        return $this->buildResponse($this->getRun($run_id));
    }

    public function getStatus($run_id)
    {
        return $this->buildResponse($this->getRun($run_id));
    }

    public function advance($run_id)
    {
        $lock_run_id = (int) $run_id;
        $run = $this->getRun($lock_run_id);
        if ($this->isTerminal($run['status'])) {
            return $this->buildResponse($run);
        }

        $lock_key = 'global';
        $locked = $this->acquireLock($lock_key);
        if (!$locked) {
            return $this->buildResponse($run);
        }
        try {
            $run = $this->getRun($run_id);
            if ($this->isTerminal($run['status'])) {
                return $this->buildResponse($run);
            }
            $this->deadline = microtime(true) + self::HARD_DEADLINE_SECONDS;
            $options = $this->decodeJson($run['options']);
            $counters = $this->normalizeCounters($this->decodeJson($run['counters']));
            $mode = ifset($options['mode'], shopMigratePluginWbSettings::MODE_AUTO);
            $mode = in_array($mode, array(
                shopMigratePluginWbSettings::MODE_AUTO,
                shopMigratePluginWbSettings::MODE_MANUAL,
            ), true) ? $mode : shopMigratePluginWbSettings::MODE_AUTO;
            $feature_mode = ifset($options['feature_mode'], shopMigratePluginWbSettings::FEATURE_MODE_AUTO);
            if (!in_array($feature_mode, array(
                shopMigratePluginWbSettings::FEATURE_MODE_AUTO,
                shopMigratePluginWbSettings::FEATURE_MODE_SKIP,
            ), true)) {
                $feature_mode = shopMigratePluginWbSettings::FEATURE_MODE_AUTO;
            }
            $feature_force_text = $mode === shopMigratePluginWbSettings::MODE_MANUAL
                && !empty($options['feature_force_text']);
            $this->feature_mapper->setForceTextFeatures($feature_force_text);
            $snapshot_id = (int) $run['snapshot_id'];

            if ($run['kind'] === self::KIND_PRODUCTS) {
                // One request can now advance from any saved stage into the
                // following products, so all product mappers must be ready for
                // the whole request instead of only its initial stage.
                $this->type_mapper->warmup($snapshot_id);
                $this->category_mapper->warmup($snapshot_id);
                $this->stock_mapper->warmup($snapshot_id);
                if ($feature_mode !== shopMigratePluginWbSettings::FEATURE_MODE_SKIP) {
                    $this->feature_mapper->warmup($snapshot_id);
                }
            }

            if ($this->shouldCancelBetweenUnits($run, $counters)) {
                $run = $this->finishRun($run, self::STATUS_CANCELLED, $counters);
                return $this->buildResponse($run);
            }

            $batch_size = min(
                shopMigratePluginWbSettings::PRODUCT_BATCH_MAX,
                max(
                    shopMigratePluginWbSettings::PRODUCT_BATCH_MIN,
                    (int) ifset($options['batch_size'], shopMigratePluginWbSettings::PRODUCT_BATCH_DEFAULT)
                )
            );
            $units_done = 0;
            while (
                $units_done < $batch_size
                && microtime(true) + self::UNIT_START_RESERVE_SECONDS < $this->deadline
            ) {
                $run = $this->getRun($run_id);
                if ((int) $run['current_group_id'] > 0) {
                    $group_id = (int) $run['current_group_id'];
                } else {
                    if ($this->shouldCancelBetweenUnits($run, $counters)) {
                        $run = $this->finishRun($run, self::STATUS_CANCELLED, $counters);
                        return $this->buildResponse($run);
                    }
                    $group_id = $this->findNextGroupId(
                        $snapshot_id,
                        (int) ifset($counters['last_group_id'], 0),
                        $run['kind'] === self::KIND_IMAGES
                    );
                    if (!$group_id) {
                        $run = $this->finishRun($run, self::STATUS_COMPLETED, $counters);
                        return $this->buildResponse($run);
                    }
                    $run['current_group_id'] = $group_id;
                    $run['image_offset'] = 0;
                    $counters['current_stage'] = $run['kind'] === self::KIND_IMAGES ? 'images' : 'product';
                    $counters['current_product_id'] = 0;
                    $counters['current_product_created'] = 0;
                    $counters['variant_offset'] = 0;
                    $this->saveRun($run, $counters);
                }

                $cards = $this->loadGroupCards($snapshot_id, $group_id);
                if (!$cards) {
                    $this->completeUnit($run, $counters, $group_id);
                    $units_done++;
                    continue;
                }

                try {
                    $stage = (string) ifset($counters['current_stage'], '');
                    if ($stage === '') {
                        $stage = $run['kind'] === self::KIND_IMAGES ? 'images' : 'product';
                        $counters['current_stage'] = $stage;
                    }
                    if ($run['kind'] === self::KIND_PRODUCTS && $stage === 'product') {
                        $imported = $this->importGroupProduct(
                            $snapshot_id,
                            $group_id,
                            $cards,
                            $mode,
                            $feature_mode
                        );
                        if (!empty($imported['skipped'])) {
                            $counters['skipped']++;
                            $counters['current_product_id'] = 0;
                            $this->completeUnit($run, $counters, $group_id);
                            $units_done++;
                            if ($this->shouldCancelBetweenUnits($run, $counters)) {
                                $run = $this->finishRun($run, self::STATUS_CANCELLED, $counters);
                                return $this->buildResponse($run);
                            }
                            continue;
                        } else {
                            $counters['current_product_id'] = (int) $imported['product_id'];
                            $counters['current_product_created'] = !empty($imported['created']) ? 1 : 0;
                        }
                        $counters['variant_offset'] = 0;
                        $counters['current_stage'] = 'variants';
                        $this->saveRun($run, $counters);
                        // Keep the durable checkpoint, but use the remaining
                        // request budget instead of forcing another AJAX call.
                        continue;
                    }

                    if ($run['kind'] === self::KIND_PRODUCTS && $stage === 'variants') {
                        $variant_result = $this->importGroupVariants(
                            $snapshot_id,
                            $cards,
                            (int) $counters['current_product_id'],
                            (int) $counters['variant_offset'],
                            self::VARIANT_BATCH_SIZE,
                            $mode,
                            $feature_mode
                        );
                        $counters['variant_offset'] = (int) $variant_result['next'];
                        if (!empty($variant_result['done'])) {
                            $counters['current_stage'] = 'finalize';
                        }
                        $this->saveRun($run, $counters);
                        // Never put more than a bounded SKU chunk between two
                        // persisted checkpoints. The next chunk may still run
                        // in this request when enough time remains.
                        continue;
                    }

                    if ($run['kind'] === self::KIND_PRODUCTS && $stage === 'finalize') {
                        $this->finalizeImportGroup(
                            $snapshot_id,
                            $group_id,
                            $cards,
                            (int) $counters['current_product_id'],
                            $feature_mode,
                            !empty($counters['current_product_created']),
                            (int) $run['id']
                        );
                        $counter = !empty($counters['current_product_created']) ? 'created' : 'updated';
                        $counters[$counter]++;
                        $counters['current_stage'] = 'images';
                        $this->saveRun($run, $counters);
                        continue;
                    }

                    $product_id = (int) ifset($counters['current_product_id'], 0);
                    if ($run['kind'] === self::KIND_IMAGES && !$product_id) {
                        $product_id = $this->findMappedProductId($group_id);
                        $counters['current_product_id'] = $product_id;
                    }
                    $should_process_images = $run['kind'] === self::KIND_IMAGES
                        || ifset($options['image_mode'], shopMigratePluginWbSettings::IMAGE_MODE_IMMEDIATE)
                            !== shopMigratePluginWbSettings::IMAGE_MODE_LATER;
                    if ($should_process_images && $product_id > 0) {
                        $images = $this->image_worker->collectGroupImages($cards);
                        $previous_image_offset = (int) $run['image_offset'];
                        $image_result = $this->image_worker->process(
                            $product_id,
                            $images,
                            $previous_image_offset,
                            shopMigratePluginWbImageWorker::DEFAULT_BATCH_SIZE,
                            $this->deadline
                        );
                        $counters['images_imported'] += (int) $image_result['imported'];
                        $counters['images_skipped'] += (int) $image_result['skipped'];
                        $counters['image_errors'] += count($image_result['errors']);
                        foreach ($image_result['errors'] as $message) {
                            $this->appendMessage($counters, $message);
                        }
                        $run['image_offset'] = (int) $image_result['next'];
                        $this->saveRun($run, $counters);
                        if (empty($image_result['done'])) {
                            // Image progress is checkpointed one file at a
                            // time. Continue only while the outer deadline
                            // guard still leaves a safe processing reserve.
                            if ((int) $image_result['next'] <= $previous_image_offset) {
                                break;
                            }
                            continue;
                        }
                        $this->assignGroupSkuImages($product_id, $images);
                    }
                } catch (Throwable $e) {
                    $counters['failed']++;
                    $this->appendMessage($counters, sprintf(
                        _wp('Wildberries group %d could not be imported: %s'),
                        $group_id,
                        $e->getMessage()
                    ));
                    $this->logError($e, array('run_id' => $run_id, 'group_id' => $group_id));
                    // Do not advance past a partially imported product. A new
                    // run can replay its idempotent stages from the product
                    // mapping, whereas marking this unit complete would hide
                    // incomplete SKUs behind a successful run status.
                    $now = date('Y-m-d H:i:s');
                    $this->runs_model->updateById($run['id'], array(
                        'status'      => self::STATUS_FAILED,
                        'counters'    => $this->encodeJson($counters),
                        'error'       => mb_substr($e->getMessage(), 0, 65535),
                        'updated_at'  => $now,
                        'finished_at' => $now,
                    ));
                    return $this->buildResponse($this->getRun($run['id']));
                }

                $this->completeUnit($run, $counters, $group_id);
                $units_done++;
                $this->releaseUnitMemory();
                if ($this->shouldCancelBetweenUnits($run, $counters)) {
                    $run = $this->finishRun($run, self::STATUS_CANCELLED, $counters);
                    return $this->buildResponse($run);
                }
            }

            return $this->buildResponse($this->getRun($run_id));
        } catch (Throwable $e) {
            $this->logError($e, array('run_id' => $lock_run_id));
            try {
                $failed_run = $this->getRun($lock_run_id);
                if (!$this->isTerminal($failed_run['status'])) {
                    $failed_counters = $this->normalizeCounters($this->decodeJson($failed_run['counters']));
                    $this->appendMessage($failed_counters, sprintf(
                        _wp('Wildberries import stopped because of an internal error: %s'),
                        $e->getMessage()
                    ));
                    $now = date('Y-m-d H:i:s');
                    $this->runs_model->updateById($lock_run_id, array(
                        'status'      => self::STATUS_FAILED,
                        'counters'    => $this->encodeJson($failed_counters),
                        'error'       => mb_substr($e->getMessage(), 0, 65535),
                        'updated_at'  => $now,
                        'finished_at' => $now,
                    ));
                }
                return $this->buildResponse($this->getRun($lock_run_id));
            } catch (Throwable $state_error) {
                $this->logError($state_error, array('run_id' => $lock_run_id, 'phase' => 'mark_failed'));
                throw $e;
            }
        } finally {
            $this->releaseLock($lock_key);
        }
    }

    private function importGroupProduct(
        $snapshot_id,
        $group_id,
        array $cards,
        $mode,
        $feature_mode
    )
    {
        $nm_ids = $this->getGroupNmIds($cards);
        $sizes = $this->loadSizes($snapshot_id, $nm_ids);
        if (!$sizes) {
            throw new waException(_wp('The Wildberries card has no importable variants.'));
        }
        $canonical = reset($cards);
        $parent_id = (int) ifset($canonical['parent_id'], 0);
        $subject_id = (int) ifset($canonical['subject_id'], 0);
        $type_id = (int) $this->type_mapper->resolve($snapshot_id, $parent_id, $subject_id, $mode);
        if (!$type_id) {
            // TypeMapper returns null only for the explicit manual action
            // "Skip products of this type".
            return array('product_id' => 0, 'created' => false, 'skipped' => true);
        }
        $category_id = $this->category_mapper->resolve($snapshot_id, $parent_id, $subject_id, $mode);
        $raw_imt_id = max(0, (int) ifset($canonical['imt_id'], 0));
        $raw_nm_id = $raw_imt_id > 0 ? 0 : max(0, (int) ifset($canonical['nm_id'], 0));
        $source_product_id = $raw_imt_id;
        if ($source_product_id <= 0) {
            $source_product_id = $raw_nm_id;
        }
        $name = $this->resolveProductName($canonical, $source_product_id);
        $currency = $this->resolveCurrency($this->findGroupCurrency($snapshot_id, $nm_ids));

        $pending_url = $this->pendingProductUrl($group_id);
        $mapping = $this->product_map_model->getByGroupId($group_id);
        $product = $mapping ? $this->product_model->getById((int) $mapping['shop_product_id']) : null;
        if ($mapping && !$product) {
            $this->product_map_model->deleteById($mapping['id']);
            $mapping = null;
        }
        if (!$mapping) {
            $pending_product = $this->product_model->getByField('url', $pending_url);
            if ($pending_product) {
                $pending_links = $this->product_map_model->getByShopProductId((int) $pending_product['id']);
                if ($pending_links) {
                    throw new waException(_wp('A pending Wildberries product is already linked to another source group.'));
                }
                $product = $pending_product;
            }
        }
        $first_import = !$mapping || empty($mapping['completed_at']);
        $product_inserted = !$product;
        $now = date('Y-m-d H:i:s');
        $product_data = array(
            'name'          => $name,
            'description'   => (string) ifset($canonical['description'], ''),
            'type_id'       => $type_id,
            'sku_type'      => $feature_mode !== shopMigratePluginWbSettings::FEATURE_MODE_SKIP && count($sizes) > 1
                ? shopProductModel::SKU_TYPE_SELECTABLE : shopProductModel::SKU_TYPE_FLAT,
            'edit_datetime' => $now,
        );
        // Keep an existing product's currency: some SKUs may retain their old
        // prices when WB omits them. Convert received prices at the SKU stage.
        if ($product_inserted) {
            $product_data['currency'] = $currency;
        }
        if ($first_import) {
            // A newly imported product remains hidden until every staged SKU
            // and selector has been finalized successfully.
            $product_data['status'] = 0;
        }
        if ($product_inserted) {
            $product_data += array(
                // Deterministic pending URL lets a replay recover the product
                // if PHP stops after INSERT but before the WB mapping write.
                'url'             => $pending_url,
                'contact_id'      => (int) wa()->getUser()->getId(),
                'create_datetime' => $now,
            );
            $product_id = (int) $this->product_model->insert($product_data);
            if ($product_id <= 0) {
                throw new waException(_wp('Unable to create a Shop-Script product.'));
            }
            $this->product_map_model->link($group_id, $raw_imt_id, $raw_nm_id, $product_id);
        } else {
            $product_id = (int) $product['id'];
            $this->product_model->updateById($product_id, $product_data);
            $this->product_map_model->link($group_id, $raw_imt_id, $raw_nm_id, $product_id);
        }
        $mapping = $this->product_map_model->getByGroupId($group_id);
        if (!$mapping || (int) $mapping['shop_product_id'] !== $product_id) {
            throw new waException(_wp('Unable to save the Wildberries product mapping.'));
        }
        $product = $this->product_model->getById($product_id);
        if ($product && (string) ifset($product['url'], '') === $pending_url) {
            $url_base = shopHelper::transliterate($name);
            $this->product_model->updateById($product_id, array(
                'url' => shopHelper::genUniqueUrl($url_base, $this->product_model),
            ));
        }

        if ($category_id) {
            $this->category_products_model->setData(new shopProduct($product_id), array((int) $category_id));
        } elseif ($mode === shopMigratePluginWbSettings::MODE_MANUAL) {
            $this->category_products_model->setData(new shopProduct($product_id), array());
        }

        $values = $this->loadAttributeValues($snapshot_id, $nm_ids);
        $attribute_meta = $this->loadAttributeMeta($snapshot_id, $subject_id);
        $split_attributes = $this->splitAttributes($nm_ids, $values);
        $common_attributes = $split_attributes[0];
        $product_payload = $this->buildFeaturePayload(
            $snapshot_id,
            $subject_id,
            $type_id,
            $common_attributes,
            $attribute_meta,
            $feature_mode
        );
        if ($feature_mode !== shopMigratePluginWbSettings::FEATURE_MODE_SKIP) {
            // Old selector metadata changes how shopProduct saves common values.
            // Remove it before the staged variant pass and reconstruct it from
            // persisted SKU feature rows during finalization.
            $this->selectable_features_model->deleteByField('product_id', $product_id);
            $this->saveProductFeatures($product_id, $product_payload);
        }

        return array('product_id' => $product_id, 'created' => $first_import, 'skipped' => false);
    }

    private function importGroupVariants(
        $snapshot_id,
        array $cards,
        $product_id,
        $offset,
        $limit,
        $mode,
        $feature_mode
    ) {
        $product_id = (int) $product_id;
        $product = $product_id > 0 ? $this->product_model->getById($product_id) : null;
        if (!$product) {
            throw new waException(_wp('The Shop-Script product being imported no longer exists.'));
        }

        $nm_ids = $this->getGroupNmIds($cards);
        $sizes = $this->loadSizes($snapshot_id, $nm_ids);
        if (!$sizes) {
            throw new waException(_wp('The Wildberries card has no importable variants.'));
        }
        $size_rows = array_values($sizes);
        $total = count($size_rows);
        $offset = min($total, max(0, (int) $offset));
        $limit = min(self::VARIANT_BATCH_SIZE, max(1, (int) $limit));
        $chunk = array_slice($size_rows, $offset, $limit);
        if (!$chunk) {
            return array('next' => $total, 'done' => true);
        }

        $canonical = reset($cards);
        $subject_id = (int) ifset($canonical['subject_id'], 0);
        $type_id = (int) ifset($product['type_id'], 0);
        $currency = (string) ifset($product['currency'], '');
        if (!$type_id) {
            throw new waException(_wp('The Shop-Script product type is missing.'));
        }
        $used_sku_codes = array();
        $used_sku_names = array();
        $identities = array();
        foreach ($size_rows as $index => $size) {
            $chrt_id = (int) $size['chrt_id'];
            $nm_id = (int) $size['nm_id'];
            $card = $this->findCard($cards, $nm_id);
            $sku_code = $this->resolveSkuCode($card, $size);
            $sku_code_key = $this->normalizeText($sku_code);
            if (isset($used_sku_codes[$sku_code_key])) {
                $sku_code = mb_substr($sku_code, 0, 220).'-WB-'.(int) $chrt_id;
                $sku_code_key = $this->normalizeText($sku_code);
            }
            $used_sku_codes[$sku_code_key] = true;
            $sku_name = $this->resolveSkuName($card, $size, count($sizes));
            $sku_name_key = $this->normalizeText($sku_name);
            if ($sku_name_key !== '' && isset($used_sku_names[$sku_name_key])) {
                $sku_name = mb_substr($sku_name, 0, 220).' / WB '.(int) $chrt_id;
                $sku_name_key = $this->normalizeText($sku_name);
            }
            if ($sku_name_key !== '') {
                $used_sku_names[$sku_name_key] = true;
            }
            $identities[$chrt_id] = array(
                'code' => $sku_code,
                'name' => $sku_name,
                'sort' => (int) $index,
            );
        }

        $prices = $this->loadPrices($snapshot_id, $nm_ids);
        $prices_available = $this->snapshotPricesAreAvailable($snapshot_id);
        $chunk_chrt_ids = array();
        foreach ($chunk as $size) {
            $chunk_chrt_ids[] = (int) $size['chrt_id'];
        }
        $stocks = $this->loadStocks($snapshot_id, $chunk_chrt_ids);
        $warehouse_context = $this->loadWarehouseContext($snapshot_id);
        $warehouse_keys = $warehouse_context['keys'];
        $values = $this->loadAttributeValues($snapshot_id, $nm_ids);
        $attribute_meta = $this->loadAttributeMeta($snapshot_id, $subject_id);
        list(, $variant_attributes) = $this->splitAttributes($nm_ids, $values);

        foreach ($chunk as $size) {
            $chrt_id = (int) $size['chrt_id'];
            $nm_id = (int) $size['nm_id'];
            $price = $this->resolveSizePrice($prices, $size);
            if ($prices_available && $price['available'] && $price['currency'] !== '') {
                $source_currency = $this->resolveCurrency($price['currency']);
                if ($source_currency !== $currency) {
                    $price['price'] = $this->currency_model->convert($price['price'], $source_currency, $currency);
                    $price['compare_price'] = $this->currency_model->convert($price['compare_price'], $source_currency, $currency);
                }
            }
            $identity = $identities[$chrt_id];
            $sku_data = array(
                'product_id'    => $product_id,
                'sku'           => $identity['code'],
                'name'          => $identity['name'],
                'sort'          => $identity['sort'],
                'price'         => $price['price'],
                'compare_price' => $price['compare_price'],
                'available'     => 1,
                'status'        => 1,
            );
            $sku_result = $this->ensureSku(
                $product_id,
                $nm_id,
                $chrt_id,
                $sku_data,
                $currency,
                $prices_available && $price['available']
            );
            $sku_id = (int) $sku_result['sku_id'];

            $sku_payload = $this->buildFeaturePayload(
                $snapshot_id,
                $subject_id,
                $type_id,
                ifset($variant_attributes[$nm_id], array()),
                $attribute_meta,
                $feature_mode
            );
            $explicit_size_label = $this->resolveExplicitSizeLabel($size);
            if ($feature_mode !== shopMigratePluginWbSettings::FEATURE_MODE_SKIP
                && count($sizes) > 1
                && $explicit_size_label !== ''
            ) {
                $size_feature = $this->ensureSyntheticFeature(
                    'wb_size',
                    _wp('Size'),
                    $type_id
                );
                $sku_payload[$size_feature['code']] = array(
                    'feature' => $size_feature,
                    'value'   => $explicit_size_label,
                );
            }
            $this->saveSkuFeatures(
                $product_id,
                $sku_id,
                $sku_payload,
                $feature_mode !== shopMigratePluginWbSettings::FEATURE_MODE_SKIP
            );
            $this->assignSkuStocks(
                $snapshot_id,
                $product_id,
                $sku_id,
                $chrt_id,
                $stocks,
                $warehouse_keys,
                $mode,
                !empty($sku_result['created']),
                !empty($warehouse_context['authoritative_empty']),
                $warehouse_context['protected_stock_ids']
            );
        }

        $next = min($total, $offset + count($chunk));
        return array('next' => $next, 'done' => $next >= $total);
    }

    private function finalizeImportGroup(
        $snapshot_id,
        $group_id,
        array $cards,
        $product_id,
        $feature_mode,
        $publish_product,
        $run_id
    )
    {
        $product_id = (int) $product_id;
        $product = $product_id > 0 ? $this->product_model->getById($product_id) : null;
        if (!$product) {
            throw new waException(_wp('The Shop-Script product being imported no longer exists.'));
        }
        $nm_ids = $this->getGroupNmIds($cards);
        $sizes = $this->loadSizes($snapshot_id, $nm_ids);
        if (!$sizes) {
            throw new waException(_wp('The Wildberries card has no importable variants.'));
        }
        $active_chrt_ids = array();
        foreach (array_keys($sizes) as $chrt_id) {
            $active_chrt_ids[(int) $chrt_id] = (int) $chrt_id;
        }
        $this->disableObsoleteSkus($product_id, $active_chrt_ids);
        $sku_values = $this->loadSkuSelectorValues($product_id, $active_chrt_ids);
        $available_sku_id = $this->findAvailableSkuId($product_id, array_keys($sku_values));
        if ($sku_values) {
            // A newly added unpriced SKU must not become the main SKU of a
            // product that already has a correctly priced, available variant.
            $this->product_model->updateById($product_id, array(
                'sku_id' => $available_sku_id ?: (int) key($sku_values),
            ));
        }
        if ($feature_mode === shopMigratePluginWbSettings::FEATURE_MODE_SKIP) {
            // Keep saved feature values, but use the flat SKU list when no
            // features were imported to build working variant selectors.
            $this->setFlatSkuMode($product_id);
        } else {
            $this->configureSelectors($product_id, $sku_values);
        }
        $this->product_model->correct($product_id);
        if ($available_sku_id) {
            // Shop-Script correct() includes hidden SKUs in price bounds.
            // Do not advertise an unpriced, blocked SKU as a zero minimum.
            $price_bounds = $this->sku_model->select(
                'MIN(primary_price) AS min_price, MAX(primary_price) AS max_price'
            )->where('product_id = ? AND available = 1 AND status = 1', $product_id)->fetchAssoc();
            if ($price_bounds && $price_bounds['min_price'] !== null) {
                $this->product_model->updateById($product_id, $price_bounds);
            }
        }
        if ($publish_product) {
            $this->product_model->updateById($product_id, array('status' => $available_sku_id ? 1 : 0));
            if (!$available_sku_id) {
                $this->unpriced_products_model->record($run_id, $snapshot_id, $product_id);
            }
        }
        if (!$this->product_map_model->markCompleted($group_id)) {
            throw new waException(_wp('Unable to finalize the Wildberries product mapping.'));
        }
    }

    private function findAvailableSkuId($product_id, array $sku_ids)
    {
        if (!$sku_ids) {
            return 0;
        }
        return (int) $this->sku_model->select('id')->where(
            'product_id = ? AND available = 1 AND status = 1 AND id IN ('.$this->integerList($sku_ids).')',
            (int) $product_id
        )->order('sort, id')->limit(1)->fetchField();
    }

    private function getGroupNmIds(array $cards)
    {
        $nm_ids = array();
        foreach ($cards as $card) {
            $nm_id = (int) ifset($card['nm_id'], 0);
            if ($nm_id > 0) {
                $nm_ids[$nm_id] = $nm_id;
            }
        }
        return array_values($nm_ids);
    }

    private function loadSkuSelectorValues($product_id, array $active_chrt_ids)
    {
        if (!$active_chrt_ids) {
            return array();
        }
        $map_table = $this->sku_map_model->getTableName();
        $map_rows = $this->sku_map_model->query(
            "SELECT chrt_id, shop_sku_id FROM {$map_table}
             WHERE shop_product_id = i:product_id
               AND chrt_id IN (".$this->integerList($active_chrt_ids).')',
            array('product_id' => (int) $product_id)
        )->fetchAll();
        $mapped_by_chrt = array();
        foreach ($map_rows as $row) {
            $mapped_by_chrt[(int) $row['chrt_id']] = (int) $row['shop_sku_id'];
        }

        // Preserve WB variant order. configureSelectors() also needs an empty
        // entry for a SKU without usable features so it cannot accidentally
        // select a feature that is absent on that SKU.
        $result = array();
        foreach ($active_chrt_ids as $chrt_id) {
            $sku_id = (int) ifset($mapped_by_chrt[(int) $chrt_id], 0);
            if ($sku_id > 0) {
                $result[$sku_id] = array();
            }
        }
        if (!$result) {
            return array();
        }

        $feature_table = $this->product_features_model->getTableName();
        $rows = $this->product_features_model->query(
            "SELECT sku_id, feature_id, feature_value_id FROM {$feature_table}
             WHERE product_id = i:product_id
               AND sku_id IN (".$this->integerList(array_keys($result)).')',
            array('product_id' => (int) $product_id)
        )->fetchAll();
        $values = array();
        foreach ($rows as $row) {
            $sku_id = (int) $row['sku_id'];
            $feature_id = (int) $row['feature_id'];
            if (!isset($result[$sku_id]) || $feature_id <= 0 || $row['feature_value_id'] === null) {
                continue;
            }
            $value_id = (int) $row['feature_value_id'];
            $values[$sku_id][$feature_id][$value_id] = $value_id;
        }
        foreach ($values as $sku_id => $features) {
            foreach ($features as $feature_id => $value_ids) {
                if (count($value_ids) === 1) {
                    $result[$sku_id][$feature_id] = reset($value_ids);
                }
            }
        }
        return $result;
    }

    private function ensureSku(
        $product_id,
        $nm_id,
        $chrt_id,
        array $data,
        $product_currency,
        $prices_available = true
    )
    {
        $mapping = $this->sku_map_model->getByChrtId($chrt_id);
        $sku = $mapping ? $this->sku_model->getById((int) $mapping['shop_sku_id']) : null;
        if ($mapping && (!$sku || (int) $sku['product_id'] !== (int) $product_id)) {
            $this->sku_map_model->deleteById($mapping['id']);
            $mapping = null;
            $sku = null;
        }
        if (!$prices_available) {
            // Values from an incomplete price source are not authoritative.
            $data['price'] = $data['compare_price'] = 0;
        }
        $data = $this->prepareSkuData($data, $product_currency);
        $created = false;
        if (!$sku) {
            $pending_code = $this->pendingSkuCode($chrt_id);
            $pending_sku = $this->sku_model->getByField(array(
                'product_id' => (int) $product_id,
                'sku'        => $pending_code,
            ));
            if ($pending_sku) {
                $pending_mapping = $this->sku_map_model->getByField(
                    'shop_sku_id',
                    (int) $pending_sku['id']
                );
                if ($pending_mapping) {
                    throw new waException(_wp('A pending Wildberries SKU is already linked to another variant.'));
                }
                $sku = $pending_sku;
            }
            if (!$sku) {
                // Store a deterministic marker first. If PHP stops between
                // this INSERT and link(), the next request can recover the
                // exact orphan instead of creating a duplicate SKU.
                $pending_data = $data;
                $pending_data['sku'] = $pending_code;
                $pending_data['available'] = 0;
                $pending_data['status'] = 0;
                $sku_id = (int) $this->sku_model->insert($pending_data);
            } else {
                $sku_id = (int) $sku['id'];
            }
            if ($sku_id <= 0) {
                throw new waException(_wp('Unable to create a Shop-Script SKU.'));
            }
            $created = true;
        } else {
            $sku_id = (int) $sku['id'];
        }
        $this->sku_map_model->link($nm_id, $chrt_id, $product_id, $sku_id);
        $mapping = $this->sku_map_model->getByChrtId($chrt_id);
        if (!$mapping || (int) $mapping['shop_sku_id'] !== $sku_id) {
            throw new waException(_wp('Unable to save the Wildberries SKU mapping.'));
        }
        // Linking precedes the final visible code update deliberately: a hard
        // stop on either side remains recoverable via the map or marker.
        if (!$prices_available) {
            if ($created) {
                $data['available'] = 0;
                $data['status'] = 0;
            } else {
                // Preserve both existing prices and the blocked state of an
                // unpriced SKU when a batch is replayed or import is repeated.
                unset($data['price'], $data['compare_price'], $data['primary_price'], $data['available'], $data['status']);
            }
        }
        $this->sku_model->updateById($sku_id, $data);
        return array('sku_id' => $sku_id, 'created' => $created);
    }

    private function prepareSkuData(array $data, $product_currency)
    {
        $price = $this->decimal(ifset($data['price'], 0));
        $compare_price = $this->decimal(ifset($data['compare_price'], 0));
        $data['price'] = $price === null ? 0 : $price;
        $data['compare_price'] = $compare_price === null ? 0 : $compare_price;
        $primary_currency = (string) wa('shop')->getConfig()->getCurrency();
        $product_currency = (string) $product_currency;
        $data['primary_price'] = $product_currency === $primary_currency
            ? $data['price']
            : $this->currency_model->convert($data['price'], $product_currency, $primary_currency);
        // Do not overwrite the existing aggregate until a complete WB stock
        // source has actually been mapped. New SKUs default to NULL (unknown /
        // unlimited in Shop-Script) when the token could not return stocks.
        return $data;
    }

    private function saveProductFeatures($product_id, array $payload)
    {
        $values = array();
        foreach ($payload as $item) {
            if (!empty($item['feature']['code'])) {
                $values[$item['feature']['code']] = $item['value'];
            }
        }
        $product = new shopProduct($product_id);
        $product->features = $values;
        $product->save();
    }

    private function saveSkuFeatures($product_id, $sku_id, array $payload, $replace = true)
    {
        $variant_feature_ids = array();
        foreach ($payload as $item) {
            $feature_id = (int) ifset($item['feature']['id'], 0);
            if ($feature_id > 0) {
                $variant_feature_ids[$feature_id] = $feature_id;
            }
        }
        if ($replace) {
            $this->product_features_model->deleteByField(array(
                'product_id' => $product_id,
                'sku_id'     => $sku_id,
            ));
        } elseif ($variant_feature_ids) {
            $this->product_features_model->deleteByField(array(
                'product_id' => $product_id,
                'sku_id'     => $sku_id,
                'feature_id' => array_values($variant_feature_ids),
            ));
        }
        if ($variant_feature_ids) {
            // A characteristic can change from common to variant between
            // snapshots. Do not leave its old product-level value behind.
            $this->product_features_model->deleteByField(array(
                'product_id' => $product_id,
                'sku_id'     => null,
                'feature_id' => array_values($variant_feature_ids),
            ));
        }
        $rows = array();
        $selector_values = array();
        foreach ($payload as $item) {
            $feature = ifset($item['feature'], array());
            if (empty($feature['id'])) {
                continue;
            }
            $values = $this->flattenValue(ifset($item['value'], ''));
            $value_ids = array();
            foreach ($values as $value) {
                $ids = $this->feature_model->getValueId($feature, $value, true);
                foreach ((array) $ids as $id) {
                    if (is_array($id)) {
                        if (!array_key_exists('id', $id)) {
                            continue;
                        }
                        $id = $id['id'];
                    }
                    if ($id === null || $id === '') {
                        continue;
                    }
                    $id = (int) $id;
                    if ($id > 0 || $id === 0 && $this->isBooleanFeature($feature)) {
                        $value_ids[$id] = $id;
                    }
                }
            }
            foreach ($value_ids as $value_id) {
                $rows[] = array(
                    'product_id'       => $product_id,
                    'sku_id'           => $sku_id,
                    'feature_id'       => (int) $feature['id'],
                    'feature_value_id' => $value_id,
                );
            }
            if (count($value_ids) === 1) {
                $selector_values[(int) $feature['id']] = reset($value_ids);
            }
        }
        if ($rows) {
            $this->product_features_model->multipleInsert($rows, waModel::INSERT_IGNORE);
        }
        return $selector_values;
    }

    private function configureSelectors($product_id, array $sku_values)
    {
        if (count($sku_values) <= 1) {
            $this->setFlatSkuMode($product_id);
            return;
        }
        $common_feature_ids = null;
        foreach ($sku_values as $values) {
            $ids = array_keys($values);
            $common_feature_ids = $common_feature_ids === null ? $ids : array_values(array_intersect($common_feature_ids, $ids));
        }
        if (!$common_feature_ids) {
            $this->setFlatSkuMode($product_id);
            return;
        }

        $features = $this->feature_model->getByField('id', array_values($common_feature_ids), true);
        $features_by_id = array();
        foreach ((array) $features as $feature) {
            $features_by_id[(int) $feature['id']] = $feature;
        }
        $candidates = array();
        foreach ((array) $common_feature_ids as $feature_id) {
            $feature_id = (int) $feature_id;
            if (empty($features_by_id[$feature_id]) || !$this->isSelectorCandidate($features_by_id[$feature_id])) {
                continue;
            }
            $unique = array();
            foreach ($sku_values as $values) {
                $unique[(string) $values[$feature_id]] = true;
            }
            if (count($unique) > 1) {
                $feature = $features_by_id[$feature_id];
                $candidates[] = array(
                    'id'       => $feature_id,
                    'priority' => $this->selectorFeaturePriority($feature),
                    'distinct' => count($unique),
                );
            }
        }
        if (!$candidates) {
            $this->setFlatSkuMode($product_id);
            return;
        }

        usort($candidates, function ($left, $right) {
            if ($left['priority'] !== $right['priority']) {
                return $left['priority'] < $right['priority'] ? -1 : 1;
            }
            if ($left['distinct'] !== $right['distinct']) {
                return $left['distinct'] > $right['distinct'] ? -1 : 1;
            }
            return $left['id'] - $right['id'];
        });
        $candidates = array_slice($candidates, 0, self::MAX_SELECTOR_CANDIDATES);
        $candidate_ids = array();
        foreach ($candidates as $candidate) {
            $candidate_ids[] = (int) $candidate['id'];
        }
        $selected = $this->findUniqueSelectorSet($candidate_ids, $sku_values);
        if (!$selected) {
            // Ambiguous option combinations cannot safely be represented by
            // Shop-Script selectors. Keep every WB chrtID as a flat SKU.
            $this->setFlatSkuMode($product_id);
            return;
        }

        foreach ($selected as $feature_id) {
            $this->feature_model->updateById($feature_id, array(
                'selectable'        => 1,
                'multiple'          => 1,
                'available_for_sku' => 1,
                'status'            => 'public',
            ));
        }
        $this->product_model->updateById($product_id, array('sku_type' => shopProductModel::SKU_TYPE_SELECTABLE));
        $this->selectable_features_model->setFeatureIds(new shopProduct($product_id), $selected);
    }

    private function setFlatSkuMode($product_id)
    {
        $this->selectable_features_model->deleteByField('product_id', $product_id);
        $this->product_model->updateById($product_id, array('sku_type' => shopProductModel::SKU_TYPE_FLAT));
    }

    private function isSelectorCandidate(array $feature)
    {
        if (!empty($feature['parent_id']) || (string) ifset($feature['status'], '') !== 'public') {
            return false;
        }
        $type = strtolower((string) ifset($feature['type'], ''));
        if ($type === '' || strpos($type, '2d.') === 0 || strpos($type, '3d.') === 0) {
            return false;
        }
        $base_type = preg_replace('/\..*$/', '', $type);
        return !in_array($base_type, array('text', 'divider'), true);
    }

    private function selectorFeaturePriority(array $feature)
    {
        $code = strtolower((string) ifset($feature['code'], ''));
        if ($code === 'wb_size') {
            return -1000;
        }
        $haystack = $code.' '.$this->normalizeText(ifset($feature['name'], ''));
        return preg_match('/(size|colour|color|razmer|rost|tsvet)/', $haystack) ? -500 : 0;
    }

    private function findUniqueSelectorSet(array $candidate_ids, array $sku_values)
    {
        $max_size = min(self::MAX_SELECTOR_FEATURES, count($candidate_ids));
        for ($size = 1; $size <= $max_size; $size++) {
            $selected = $this->searchSelectorCombination($candidate_ids, $sku_values, $size, 0, array());
            if ($selected !== false) {
                return $selected;
            }
        }
        return array();
    }

    private function searchSelectorCombination(
        array $candidate_ids,
        array $sku_values,
        $target_size,
        $offset,
        array $selected
    ) {
        if (count($selected) === (int) $target_size) {
            return $this->selectorSetIsUnique($selected, $sku_values) ? $selected : false;
        }
        $needed = (int) $target_size - count($selected);
        $last = count($candidate_ids) - $needed;
        for ($index = (int) $offset; $index <= $last; $index++) {
            $next = $selected;
            $next[] = (int) $candidate_ids[$index];
            $result = $this->searchSelectorCombination(
                $candidate_ids,
                $sku_values,
                $target_size,
                $index + 1,
                $next
            );
            if ($result !== false) {
                return $result;
            }
        }
        return false;
    }

    private function selectorSetIsUnique(array $feature_ids, array $sku_values)
    {
        $seen = array();
        foreach ($sku_values as $values) {
            $parts = array();
            foreach ($feature_ids as $feature_id) {
                if (!array_key_exists($feature_id, $values)) {
                    return false;
                }
                $parts[] = $feature_id.':'.(string) $values[$feature_id];
            }
            $signature = implode('|', $parts);
            if (isset($seen[$signature])) {
                return false;
            }
            $seen[$signature] = true;
        }
        return count($seen) === count($sku_values);
    }

    private function assignSkuStocks(
        $snapshot_id,
        $product_id,
        $sku_id,
        $chrt_id,
        array $stocks,
        array $warehouse_keys,
        $mode,
        $sku_created,
        $authoritative_empty,
        array $protected_stock_ids = array()
    )
    {
        if (!$warehouse_keys) {
            if ($sku_created && $authoritative_empty) {
                $this->initializeNewSkuAsOutOfStock($product_id, $sku_id);
            }
            return;
        }
        $resolved = array();
        foreach ($warehouse_keys as $warehouse_key) {
            $shop_stock_id = $this->stock_mapper->resolve($snapshot_id, $warehouse_key, $mode);
            if ($shop_stock_id && !in_array((int) $shop_stock_id, $protected_stock_ids, true)) {
                $resolved[$warehouse_key] = (int) $shop_stock_id;
            }
        }

        // Only mapped WB warehouses are authoritative. Existing quantities in
        // unrelated or explicitly skipped Shop-Script stocks must be preserved.
        if (!$resolved) {
            return;
        }
        $totals = array();
        foreach ($resolved as $shop_stock_id) {
            $totals[(int) $shop_stock_id] = 0.0;
        }
        if ($sku_created) {
            // A new SKU has no unrelated stock history to preserve. Explicit
            // zero rows prevent missing Shop stocks from turning a known WB
            // quantity into an unlimited aggregate.
            foreach ((array) $this->stock_model->getAll('id') as $stock_key => $stock) {
                $stock_id = is_array($stock) ? (int) ifset($stock['id'], 0) : (int) $stock_key;
                if ($stock_id > 0 && !isset($totals[$stock_id])) {
                    $totals[$stock_id] = 0.0;
                }
            }
        }
        foreach (ifset($stocks[$chrt_id], array()) as $stock) {
            $key = (string) $stock['warehouse_key'];
            if (isset($resolved[$key])) {
                $shop_stock_id = $resolved[$key];
                if (!isset($totals[$shop_stock_id])) {
                    // A mapper may have created a stock after getAll() was read.
                    $totals[$shop_stock_id] = 0.0;
                }
                $totals[$shop_stock_id] += max(0, (float) $stock['quantity']);
            }
        }

        foreach ($totals as $stock_id => $quantity) {
            $this->product_stocks_model->set(array(
                'product_id' => $product_id,
                'sku_id'     => $sku_id,
                'stock_id'   => $stock_id,
                'count'      => $quantity,
            ));
        }
        $this->syncSkuAggregateCount($sku_id);
    }

    private function initializeNewSkuAsOutOfStock($product_id, $sku_id)
    {
        foreach ((array) $this->stock_model->getAll('id') as $stock_key => $stock) {
            $stock_id = is_array($stock) ? (int) ifset($stock['id'], 0) : (int) $stock_key;
            if ($stock_id <= 0) {
                continue;
            }
            $this->product_stocks_model->set(array(
                'product_id' => (int) $product_id,
                'sku_id'     => (int) $sku_id,
                'stock_id'   => $stock_id,
                'count'      => 0,
            ));
        }
        $this->sku_model->updateById((int) $sku_id, array('count' => 0));
    }

    private function syncSkuAggregateCount($sku_id)
    {
        $shop_stocks = (array) $this->stock_model->getAll('id');
        if (!$shop_stocks) {
            return;
        }
        $rows = (array) $this->product_stocks_model->getByField('sku_id', (int) $sku_id, true);
        $counts = array();
        foreach ($rows as $row) {
            $stock_id = (int) ifset($row['stock_id'], 0);
            if ($stock_id > 0) {
                $counts[$stock_id] = (float) $row['count'];
            }
        }
        $total = 0.0;
        foreach ($shop_stocks as $stock_key => $stock) {
            $stock_id = is_array($stock) ? (int) ifset($stock['id'], 0) : (int) $stock_key;
            if ($stock_id > 0 && !array_key_exists($stock_id, $counts)) {
                $total = null;
                break;
            }
            if ($stock_id > 0) {
                $total += $counts[$stock_id];
            }
        }
        $this->sku_model->updateById((int) $sku_id, array('count' => $total));
    }

    private function buildFeaturePayload(
        $snapshot_id,
        $subject_id,
        $type_id,
        array $values,
        array $meta,
        $feature_mode
    )
    {
        $payload = array();
        if ($feature_mode === shopMigratePluginWbSettings::FEATURE_MODE_SKIP) {
            return $payload;
        }
        foreach ($values as $characteristic_id => $value) {
            $feature = $this->feature_mapper->resolve(
                $snapshot_id,
                $subject_id,
                $characteristic_id,
                $type_id,
                $value
            );
            if (!$feature || empty($feature['code'])) {
                continue;
            }
            $prepared_value = $this->prepareFeatureValue(
                $value,
                ifset($meta[$characteristic_id], array()),
                $feature
            );
            if ($prepared_value === null) {
                continue;
            }
            $payload[$feature['code']] = array(
                'feature' => $feature,
                'value'   => $prepared_value,
            );
        }
        return $payload;
    }

    private function prepareFeatureValue($value, array $attribute, array $feature)
    {
        if (strpos((string) ifset($feature['type'], ''), 'dimension.') === 0) {
            return $this->prepareDimensionFeatureValue($value, $attribute, $feature);
        }
        if (is_array($value)) {
            $result = array();
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $result[] = trim((string) $item);
                }
            }
            if (empty($feature['multiple'])) {
                if ((string) ifset($feature['type'], '') === shopFeatureModel::TYPE_TEXT) {
                    return $result ? implode(', ', array_values(array_unique($result))) : '';
                }
                if (count(array_unique($result)) > 1) {
                    throw new waException(sprintf(
                        _wp('Feature "%s" cannot store multiple values. Enable text feature import to preserve all values.'),
                        (string) ifset($feature['name'], $feature['code'])
                    ));
                }
                return $result ? reset($result) : '';
            }
            return $result;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            $text = trim((string) $value);
            $unit = trim((string) ifset($attribute['unit'], ''));
            if ($unit !== '' && $text !== '' && strpos($text, $unit) === false && strpos((string) ifset($feature['type'], ''), 'dimension.') !== 0) {
                $text .= ' '.$unit;
            }
            return $text;
        }
        return $this->encodeJson($value);
    }

    private function prepareDimensionFeatureValue($value, array $attribute, array $feature)
    {
        if (is_array($value)) {
            if (array_key_exists('value', $value)) {
                $value = $value['value'];
            } else {
                $values = array_values(array_unique(array_filter($value, 'is_scalar'), SORT_REGULAR));
                if (count($values) > 1) {
                    // This path accepts one measurement (including its unit),
                    // not a collection. Never quietly discard measurements.
                    throw new waException(sprintf(
                        _wp('Feature "%s" cannot store multiple values. Enable text feature import to preserve all values.'),
                        (string) ifset($feature['name'], $feature['code'])
                    ));
                }
                $scalar = null;
                foreach ($value as $item) {
                    if (is_scalar($item)) {
                        $scalar = $item;
                        break;
                    }
                }
                $value = $scalar;
            }
        }
        if (is_string($value)) {
            $value = str_replace(array("\xC2\xA0", ' ', ','), array('', '', '.'), trim($value));
            if (!is_numeric($value) && preg_match('/^(-?\d+(?:\.\d+)?)[^\d]*$/u', $value, $matches)) {
                $value = $matches[1];
            }
        }
        if (!is_numeric($value)) {
            return null;
        }
        $unit = $this->feature_mapper->detectAttributeUnit($attribute, $feature);
        if ($unit === null || $unit === '') {
            $unit = (string) ifset($feature['default_unit'], '');
        }
        $dimension_type = substr((string) ifset($feature['type'], ''), strlen('dimension.'));
        if ($dimension_type === '') {
            return null;
        }
        return array(
            'value' => (float) $value,
            'unit'  => $unit,
            'type'  => $dimension_type,
        );
    }

    private function ensureSyntheticFeature($code, $name, $type_id)
    {
        if (!isset($this->synthetic_features[$code])) {
            $feature = $this->findCompatibleSyntheticFeature($code, $name);
            if (!$feature) {
                $feature = $this->createSyntheticFeature($code, $name);
            }
            if (!$feature) {
                throw new waException(_wp('Unable to create the Wildberries size feature.'));
            }
            $this->feature_model->updateById($feature['id'], array(
                'selectable'        => 1,
                'multiple'          => 1,
                'available_for_sku' => 1,
                'status'            => 'public',
            ));
            $feature = $this->feature_model->getById($feature['id']);
            $this->synthetic_features[$code] = $feature;
        }
        $feature = $this->synthetic_features[$code];
        $this->type_features_model->addFeaturesToType($type_id, array((int) $feature['id']));
        return $feature;
    }

    private function findCompatibleSyntheticFeature($code, $name)
    {
        $candidates = array();
        $feature = $this->feature_model->getByField('code', (string) $code);
        if ($feature) {
            $candidates[(int) $feature['id']] = $feature;
        }
        foreach ((array) $this->feature_model->getByField('name', (string) $name, true) as $feature) {
            if (is_array($feature) && !empty($feature['id'])) {
                $candidates[(int) $feature['id']] = $feature;
            }
        }
        foreach ($candidates as $feature) {
            if (empty($feature['parent_id']) && (string) ifset($feature['type'], '') === 'varchar') {
                return $feature;
            }
        }
        return null;
    }

    private function createSyntheticFeature($code, $name)
    {
        $last_duplicate = null;
        for ($attempt = 0; $attempt < self::SYNTHETIC_FEATURE_CREATE_ATTEMPTS; $attempt++) {
            $candidate_code = $this->buildSyntheticFeatureCodeCandidate($code, $attempt);
            $feature = $this->feature_model->getByField('code', $candidate_code);
            if ($feature) {
                if (empty($feature['parent_id']) && (string) ifset($feature['type'], '') === 'varchar') {
                    return $feature;
                }
                continue;
            }

            $data = array(
                'name'              => $name,
                'code'              => $candidate_code,
                'type'              => 'varchar',
                'selectable'        => 1,
                'multiple'          => 1,
                'available_for_sku' => 1,
                'status'            => 'public',
            );
            try {
                $id = (int) $this->feature_model->save($data);
            } catch (waDbException $e) {
                if ((int) $e->getCode() !== 1062) {
                    throw $e;
                }
                $last_duplicate = $e;
                $insert_code = (string) ifset($data['code'], $candidate_code);
                $feature = $this->feature_model->getByField('code', $insert_code);
                if ($feature
                    && empty($feature['parent_id'])
                    && (string) ifset($feature['type'], '') === 'varchar'
                ) {
                    return $feature;
                }
                continue;
            }
            if ($id > 0) {
                return $this->feature_model->getById($id);
            }
        }
        if ($last_duplicate) {
            throw $last_duplicate;
        }
        return null;
    }

    private function buildSyntheticFeatureCodeCandidate($base_code, $attempt)
    {
        $base_code = strtolower(trim((string) $base_code));
        if ((int) $attempt <= 0) {
            return mb_substr($base_code, 0, self::FEATURE_CODE_MAX_LENGTH);
        }
        $suffix = '_migrate_wb'.((int) $attempt > 1 ? '_'.(int) $attempt : '');
        $prefix_length = max(1, self::FEATURE_CODE_MAX_LENGTH - strlen($suffix));
        $prefix = rtrim(mb_substr($base_code, 0, $prefix_length), '_');
        return mb_substr(($prefix !== '' ? $prefix : 'feature').$suffix, 0, self::FEATURE_CODE_MAX_LENGTH);
    }

    private function isBooleanFeature(array $feature)
    {
        return preg_replace('/\..*$/', '', strtolower((string) ifset($feature['type'], ''))) === 'boolean';
    }

    private function splitAttributes(array $nm_ids, array $values)
    {
        $all_ids = array();
        foreach ($values as $nm_values) {
            foreach ($nm_values as $id => $value) {
                $all_ids[$id] = $id;
            }
        }
        $common = array();
        $variants = array();
        foreach ($all_ids as $id) {
            $signature = null;
            $is_common = true;
            foreach ($nm_ids as $nm_id) {
                if (!array_key_exists($id, ifset($values[$nm_id], array()))) {
                    $is_common = false;
                    break;
                }
                $current = $this->valueSignature($values[$nm_id][$id]);
                if ($signature === null) {
                    $signature = $current;
                } elseif ($signature !== $current) {
                    $is_common = false;
                    break;
                }
            }
            if ($is_common && $nm_ids) {
                $common[$id] = $values[reset($nm_ids)][$id];
            } else {
                foreach ($nm_ids as $nm_id) {
                    if (isset($values[$nm_id]) && array_key_exists($id, $values[$nm_id])) {
                        $variants[$nm_id][$id] = $values[$nm_id][$id];
                    }
                }
            }
        }
        return array($common, $variants);
    }

    private function disableObsoleteSkus($product_id, array $active_chrt_ids)
    {
        $rows = $this->sku_map_model->getByField('shop_product_id', $product_id, true);
        foreach ($rows as $row) {
            $chrt_id = (int) $row['chrt_id'];
            if (isset($active_chrt_ids[$chrt_id])) {
                continue;
            }
            $sku = $this->sku_model->getById((int) $row['shop_sku_id']);
            if ($sku && (int) $sku['product_id'] === (int) $product_id) {
                $this->sku_model->updateById($sku['id'], array(
                    'available'  => 0,
                    'status'     => 0,
                    'count'      => 0,
                ));
                $this->product_features_model->deleteByField(array(
                    'product_id' => $product_id,
                    'sku_id'     => (int) $sku['id'],
                ));
                $stock_rows = $this->product_stocks_model->getByField('sku_id', (int) $sku['id'], true);
                foreach ((array) $stock_rows as $stock_row) {
                    $stock_id = (int) ifset($stock_row['stock_id'], 0);
                    if ($stock_id > 0) {
                        $this->product_stocks_model->set(array(
                            'product_id' => $product_id,
                            'sku_id'     => (int) $sku['id'],
                            'stock_id'   => $stock_id,
                            'count'      => 0,
                        ));
                    }
                }
            }
        }
    }

    /**
     * Shop-Script stores one selected image per SKU. Assign the first
     * successfully imported photo of every WB card to all of its size SKUs;
     * the remaining photos stay available in the common product gallery.
     */
    private function assignGroupSkuImages($product_id, array $images)
    {
        $product_id = (int) $product_id;
        if ($product_id <= 0 || !$images) {
            return;
        }

        $image_rows = (array) $this->image_map_model->getByProduct($product_id);
        $image_ids_by_source = array();
        foreach ($image_rows as $row) {
            $source_key = (string) ifset($row['source_key'], '');
            $image_id = (int) ifset($row['shop_image_id'], 0);
            if ($source_key !== '' && $image_id > 0) {
                $image_ids_by_source[$source_key] = $image_id;
            }
        }

        $primary_by_nm = array();
        foreach ($images as $item) {
            $source_key = (string) ifset($item['source_key'], '');
            $image_id = (int) ifset($image_ids_by_source[$source_key], 0);
            if ($image_id <= 0) {
                continue;
            }
            if (!empty($item['nm_ids']) && is_array($item['nm_ids'])) {
                $nm_ids = $item['nm_ids'];
            } else {
                $item_nm_id = isset($item['nm_id']) ? (int) $item['nm_id'] : 0;
                $nm_ids = $item_nm_id > 0 ? array($item_nm_id => $item_nm_id) : array();
            }
            $positions = isset($item['nm_positions']) && is_array($item['nm_positions'])
                ? $item['nm_positions']
                : array();
            foreach ($nm_ids as $nm_id) {
                $nm_id = (int) $nm_id;
                if ($nm_id <= 0) {
                    continue;
                }
                $position = (int) ifset($positions[$nm_id], ifset($item['position'], 0));
                if (!isset($primary_by_nm[$nm_id]) || $position < $primary_by_nm[$nm_id]['position']) {
                    $primary_by_nm[$nm_id] = array(
                        'position' => $position,
                        'image_id' => $image_id,
                    );
                }
            }
        }
        if (!$primary_by_nm) {
            return;
        }

        $sku_rows = (array) $this->sku_map_model->getByField('shop_product_id', $product_id, true);
        foreach ($sku_rows as $row) {
            $nm_id = (int) ifset($row['nm_id'], 0);
            $sku_id = (int) ifset($row['shop_sku_id'], 0);
            if ($sku_id <= 0 || !isset($primary_by_nm[$nm_id])) {
                continue;
            }
            $sku = $this->sku_model->getById($sku_id);
            if (!$sku || (int) $sku['product_id'] !== $product_id) {
                continue;
            }
            $image_id = (int) $primary_by_nm[$nm_id]['image_id'];
            if ((int) ifset($sku['image_id'], 0) !== $image_id) {
                $this->sku_model->updateById($sku_id, array('image_id' => $image_id));
            }
        }
    }

    private function loadGroupCards($snapshot_id, $group_id)
    {
        return array_values((array) $this->cards_model->getByGroup($snapshot_id, $group_id));
    }

    private function loadSizes($snapshot_id, array $nm_ids)
    {
        if (!$nm_ids) {
            return array();
        }
        $table = $this->sizes_model->getTableName();
        $rows = $this->sizes_model->query(
            "SELECT * FROM {$table}
             WHERE snapshot_id = i:snapshot_id AND nm_id IN (".$this->integerList($nm_ids).')
             ORDER BY nm_id, id',
            array('snapshot_id' => $snapshot_id)
        )->fetchAll();
        $result = array();
        foreach ($rows as $row) {
            $result[(int) $row['chrt_id']] = $row;
        }
        return $result;
    }

    private function loadAttributeValues($snapshot_id, array $nm_ids)
    {
        if (!$nm_ids) {
            return array();
        }
        $table = $this->attribute_values_model->getTableName();
        $rows = $this->attribute_values_model->query(
            "SELECT * FROM {$table}
             WHERE snapshot_id = i:snapshot_id AND nm_id IN (".$this->integerList($nm_ids).')',
            array('snapshot_id' => $snapshot_id)
        )->fetchAll();
        $result = array();
        foreach ($rows as $row) {
            $result[(int) $row['nm_id']][(int) $row['characteristic_id']] = $this->decodeStoredValue($row['value']);
        }
        return $result;
    }

    private function loadAttributeMeta($snapshot_id, $subject_id)
    {
        $rows = $this->attributes_model->getByField(array(
            'snapshot_id' => $snapshot_id,
            'subject_id'  => $subject_id,
        ), true);
        $result = array();
        foreach ($rows as $row) {
            $result[(int) $row['characteristic_id']] = $row;
        }
        return $result;
    }

    private function loadPrices($snapshot_id, array $nm_ids)
    {
        if (!$nm_ids) {
            return array();
        }
        $table = $this->prices_model->getTableName();
        $rows = $this->prices_model->query(
            "SELECT * FROM {$table}
             WHERE snapshot_id = i:snapshot_id AND nm_id IN (".$this->integerList($nm_ids).')',
            array('snapshot_id' => $snapshot_id)
        )->fetchAll();
        $result = array('chrt' => array(), 'fallback' => array());
        foreach ($rows as $row) {
            if ((int) $row['chrt_id'] > 0) {
                $result['chrt'][(int) $row['chrt_id']] = $row;
            }
            $result['fallback'][(int) $row['nm_id'].':'.$this->normalizeText($row['tech_size'])] = $row;
        }
        return $result;
    }

    private function loadStocks($snapshot_id, array $chrt_ids)
    {
        if (!$chrt_ids) {
            return array();
        }
        $table = $this->stocks_model->getTableName();
        $warehouses_table = $this->warehouses_model->getTableName();
        $rows = $this->stocks_model->query(
            "SELECT s.* FROM {$table} s
             INNER JOIN {$warehouses_table} w
                ON w.snapshot_id = s.snapshot_id AND w.warehouse_key = s.warehouse_key
             WHERE s.snapshot_id = i:snapshot_id
               AND s.chrt_id IN (".$this->integerList($chrt_ids).")
               AND w.source IN ('seller', 'wb')",
            array('snapshot_id' => $snapshot_id)
        )->fetchAll();
        $result = array();
        foreach ($rows as $row) {
            $result[(int) $row['chrt_id']][] = $row;
        }
        return $result;
    }

    private function loadWarehouseContext($snapshot_id)
    {
        $snapshot = $this->snapshots_model->getById((int) $snapshot_id);
        $meta = $snapshot ? $this->snapshots_model->decodeMeta($snapshot) : array();
        $build = (array) ifset($meta['build'], array());
        $complete = array(
            'seller' => (string) ifset($build['seller_stocks_status'], '') === 'complete',
            'wb' => (string) ifset($build['fbw_stocks_status'], '') === 'complete',
        );
        $keys = array();
        foreach ($this->warehouses_model->getAllBySnapshot($snapshot_id) as $row) {
            if (!empty($complete[$row['source']])) {
                $keys[] = (string) $row['warehouse_key'];
            }
        }
        $protected_stock_ids = array();
        foreach ($this->repository->getStockMapModel()->getMap($snapshot_id) as $mapping) {
            $source = explode(':', (string) $mapping['warehouse_key'], 2)[0];
            if (isset($complete[$source]) && !$complete[$source] && !empty($mapping['shop_stock_id'])) {
                // If both sources map to one Shop stock, an incomplete source
                // must not cause its balance to be replaced with a partial sum.
                $protected_stock_ids[] = (int) $mapping['shop_stock_id'];
            }
        }
        return array(
            'keys'                => $keys,
            'protected_stock_ids' => array_values(array_unique($protected_stock_ids)),
            // Missing rows are authoritative only for completed sources.
            'authoritative_empty' => !$keys && $complete['seller']
                && (!isset($build['fbw_stocks_status']) || $complete['wb']),
        );
    }

    private function snapshotPricesAreAvailable($snapshot_id)
    {
        $snapshot_id = (int) $snapshot_id;
        if (array_key_exists($snapshot_id, $this->snapshot_prices_available)) {
            return $this->snapshot_prices_available[$snapshot_id];
        }
        $snapshot = $this->snapshots_model->getById($snapshot_id);
        $meta = $snapshot ? $this->snapshots_model->decodeMeta($snapshot) : array();
        $build = (array) ifset($meta['build'], array());
        $this->snapshot_prices_available[$snapshot_id] = (
            (string) ifset($build['prices_status'], '') !== 'unavailable'
        );
        return $this->snapshot_prices_available[$snapshot_id];
    }

    private function resolveSizePrice(array $prices, array $size)
    {
        $row = ifset($prices['chrt'][(int) $size['chrt_id']]);
        if (!$row) {
            $key = (int) $size['nm_id'].':'.$this->normalizeText($size['tech_size']);
            $row = ifset($prices['fallback'][$key], array());
        }
        $price = $this->decimal(ifset($row['discounted_price'], null));
        if ($price === null) {
            $price = $this->decimal(ifset($row['price'], null));
        }
        $compare = $this->decimal(ifset($row['price'], 0));
        if ($compare === null || $compare <= $price) {
            $compare = 0;
        }
        return array(
            'price' => $price !== null && $price >= 0 ? $price : 0,
            'compare_price' => $compare,
            'currency' => (string) ifset($row['currency'], ''),
            // An explicitly supplied zero is a price; an absent row is not.
            'available' => $price !== null && $price >= 0,
        );
    }

    private function findGroupCurrency($snapshot_id, array $nm_ids)
    {
        if (!$nm_ids) {
            return '';
        }
        $table = $this->prices_model->getTableName();
        return (string) $this->prices_model->query(
            "SELECT currency FROM {$table}
             WHERE snapshot_id = i:snapshot_id AND nm_id IN (".$this->integerList($nm_ids).")
             AND currency <> '' LIMIT 1",
            array('snapshot_id' => $snapshot_id)
        )->fetchField();
    }

    private function resolveCurrency($currency)
    {
        $currency = strtoupper(trim((string) $currency));
        $map = array('643' => 'RUB', 'RUR' => 'RUB', '933' => 'BYN', '398' => 'KZT', '051' => 'AMD');
        if (isset($map[$currency])) {
            $currency = $map[$currency];
        }
        if (preg_match('/^[A-Z]{3}$/', $currency)) {
            $model = new shopCurrencyModel();
            if ($model->getById($currency)) {
                return $currency;
            }
        }
        return (string) wa('shop')->getConfig()->getCurrency();
    }

    private function resolveProductName(array $card, $group_id)
    {
        $name = trim((string) ifset($card['name'], ''));
        if ($name === '') {
            $name = trim((string) ifset($card['brand'], '').' '.(string) ifset($card['vendor_code'], ''));
        }
        return $name !== '' ? mb_substr($name, 0, 255) : sprintf(_wp('Wildberries product %d'), $group_id);
    }

    private function pendingProductUrl($group_id)
    {
        return 'wb-import-pending-'.substr(sha1('shop-migrate-wb-product:'.(int) $group_id), 0, 24);
    }

    private function pendingSkuCode($chrt_id)
    {
        return 'wb-import-pending-'.substr(sha1('shop-migrate-wb-sku:'.(int) $chrt_id), 0, 24);
    }

    private function resolveSkuCode(array $card, array $size)
    {
        $base = trim((string) ifset($card['vendor_code'], ''));
        $size_label = $this->resolveSizeLabel($size);
        if ($base === '') {
            $base = 'WB-'.(int) $size['nm_id'];
        }
        if ($size_label !== '' && $size_label !== '0') {
            $base .= '-'.$size_label;
        }
        return mb_substr($base, 0, 255);
    }

    private function resolveSkuName(array $card, array $size, $total)
    {
        if ((int) $total <= 1) {
            return '';
        }
        $parts = array();
        $vendor_code = trim((string) ifset($card['vendor_code'], ''));
        $parts[] = $vendor_code !== '' ? $vendor_code : 'WB '.(int) ifset($size['nm_id'], 0);
        $label = $this->resolveSizeLabel($size);
        if ($label !== '') {
            $parts[] = $label;
        }
        return mb_substr(implode(' / ', array_filter($parts, 'strlen')), 0, 255);
    }

    private function resolveSizeLabel(array $size)
    {
        return $this->resolveExplicitSizeLabel($size);
    }

    private function resolveExplicitSizeLabel(array $size)
    {
        $label = trim((string) ifset($size['tech_size'], ''));
        if ($label === '' || $label === '0') {
            $label = trim((string) ifset($size['wb_size'], ''));
        }
        return $label !== '0' ? $label : '';
    }

    private function findCard(array $cards, $nm_id)
    {
        foreach ($cards as $card) {
            if ((int) $card['nm_id'] === (int) $nm_id) {
                return $card;
            }
        }
        return reset($cards);
    }

    private function findMappedProductId($group_id)
    {
        $map = $this->product_map_model->getByGroupId($group_id);
        if (!$map || empty($map['completed_at'])) {
            return 0;
        }
        $product = $this->product_model->getById((int) $map['shop_product_id']);
        return $product ? (int) $product['id'] : 0;
    }

    private function countGroups($snapshot_id, $mapped_only)
    {
        if (!$mapped_only) {
            return (int) $this->cards_model->countGroups($snapshot_id);
        }
        return $this->product_map_model->countCompletedForSnapshot($snapshot_id);
    }

    private function findNextGroupId($snapshot_id, $after_group_id, $mapped_only)
    {
        if (!$mapped_only) {
            $next = $this->cards_model->nextGroup($snapshot_id, $after_group_id);
            return is_array($next) ? (int) ifset($next['group_id'], 0) : (int) $next;
        }
        return $this->product_map_model->findNextCompletedGroupIdForSnapshot(
            $snapshot_id,
            $after_group_id
        );
    }

    private function completeUnit(array &$run, array &$counters, $group_id)
    {
        $counters['processed']++;
        $counters['last_group_id'] = (int) $group_id;
        $counters['current_stage'] = '';
        $counters['current_product_id'] = 0;
        $counters['current_product_created'] = 0;
        $counters['variant_offset'] = 0;
        $run['cursor'] = $counters['processed'];
        $run['current_group_id'] = 0;
        $run['image_offset'] = 0;
        $this->saveRun($run, $counters);
    }

    private function saveRun(array &$run, array $counters)
    {
        $data = array(
            'cursor'           => (int) $run['cursor'],
            'current_group_id' => (int) $run['current_group_id'],
            'image_offset'     => (int) $run['image_offset'],
            'counters'         => $this->encodeJson($counters),
            'updated_at'       => date('Y-m-d H:i:s'),
        );
        $this->runs_model->updateById($run['id'], $data);
        // Cancellation is written by another AJAX request. Reload it instead
        // of ever overwriting status/flag from this worker's stale copy.
        $run = $this->getRun($run['id']);
    }

    private function finishRun(array $run, $status, array $counters)
    {
        $latest = $this->getRun($run['id']);
        if ($status === self::STATUS_COMPLETED && !empty($latest['cancel_requested'])) {
            $status = self::STATUS_CANCELLED;
        }
        $now = date('Y-m-d H:i:s');
        $this->runs_model->updateById($run['id'], array(
            'status'           => $status,
            'cancel_requested' => $status === self::STATUS_CANCELLED ? 1 : (int) $latest['cancel_requested'],
            'current_group_id' => 0,
            'image_offset'     => 0,
            'cursor'           => (int) $counters['processed'],
            'counters'         => $this->encodeJson($counters),
            'updated_at'       => $now,
            'finished_at'      => $now,
        ));
        return $this->getRun($run['id']);
    }

    private function shouldCancelBetweenUnits(array $run, array $counters)
    {
        return !empty($run['cancel_requested'])
            && (int) $run['current_group_id'] === 0
            && empty($counters['current_stage']);
    }

    private function buildResponse(array $run)
    {
        $counters = $this->normalizeCounters($this->decodeJson($run['counters']));
        $counters['messages'] = $this->filterImportMessages((array) $counters['messages']);
        $total = max(0, (int) $counters['total']);
        $processed = min($total, max(0, (int) $counters['processed']));
        $progress = $total > 0 ? round($processed * 100 / $total, 2) : 100;
        $terminal = $this->isTerminal($run['status']);
        if ($terminal && $run['status'] === self::STATUS_COMPLETED) {
            $progress = 100;
        }
        $message = $run['kind'] === self::KIND_IMAGES
            ? sprintf(_wp('Processed images for %d of %d products.'), $processed, $total)
            : sprintf(_wp('Processed %d of %d Wildberries products.'), $processed, $total);
        if ($run['status'] === self::STATUS_CANCEL_REQUESTED || !empty($run['cancel_requested']) && !$terminal) {
            $message = _wp('Cancellation requested. The current product will be completed first.');
        } elseif ($run['status'] === self::STATUS_CANCELLED) {
            $message = _wp('Wildberries import was cancelled after completing the current product.');
        } elseif ($run['status'] === self::STATUS_FAILED) {
            $message = _wp('Wildberries import failed. Review the errors below.');
        } elseif ($run['status'] === self::STATUS_COMPLETED) {
            $message = $run['kind'] === self::KIND_IMAGES
                ? _wp('Wildberries product images have been imported.')
                : _wp('Wildberries products have been imported into Shop-Script.');
        }
        $unpriced_count = $terminal && $run['kind'] === self::KIND_PRODUCTS
            ? $this->unpriced_products_model->countForRun($run['id'])
            : 0;
        return array(
            'run_id'           => (int) $run['id'],
            'snapshot_id'      => (int) $run['snapshot_id'],
            'kind'             => (string) $run['kind'],
            'status'           => (string) $run['status'],
            'done'             => $terminal,
            'cancelled'        => $run['status'] === self::STATUS_CANCELLED,
            'cancel_requested' => !empty($run['cancel_requested']),
            'progress'         => $progress,
            'processed'        => $processed,
            'total'            => $total,
            'skipped'          => (int) $counters['skipped'],
            'current_group_id' => (int) $run['current_group_id'],
            'image_offset'     => (int) $run['image_offset'],
            'message'          => $message,
            'counters'         => $counters,
            'errors'           => array_values((array) ifset($counters['messages'], array())),
            'unpriced_products_count' => $unpriced_count,
            'unpriced_products_message' => $unpriced_count > 0 ? _wp(
                '%d product has not been published on the storefront: prices are not specified.',
                '%d products have not been published on the storefront: prices are not specified.',
                $unpriced_count
            ) : '',
            'unpriced_products_hash' => $unpriced_count > 0
                ? shopMigratePluginWbUnpricedProductsModel::COLLECTION_HASH.'/'.(int) $run['id']
                : '',
        );
    }

    private function filterImportMessages(array $messages)
    {
        // Older runs stored this informational notice among errors. Do not
        // display it when an import is resumed or its saved result is opened.
        $template = 'Product "%s" was imported but remains hidden because prices were not collected. Set SKU prices and availability before publishing it.';
        $translated_template = _wp('Product "%s" was imported but remains hidden because prices were not collected. Set SKU prices and availability before publishing it.');
        $patterns = array();
        foreach (array_unique(array($template, $translated_template)) as $translation) {
            $patterns[] = '~\\A'.str_replace('%s', '.*', preg_quote($translation, '~')).'\\z~s';
        }
        return array_values(array_filter($messages, function ($message) use ($patterns) {
            if (is_string($message)) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $message)) {
                        return false;
                    }
                }
            }
            return true;
        }));
    }

    private function getRun($run_id)
    {
        $run = $this->runs_model->getById((int) $run_id);
        if (!$run) {
            throw new waException(_wp('Wildberries import run was not found.'));
        }
        return $run;
    }

    private function isTerminal($status)
    {
        return in_array($status, array(self::STATUS_CANCELLED, self::STATUS_COMPLETED, self::STATUS_FAILED), true);
    }

    private function normalizeCounters(array $counters)
    {
        return array_merge(array(
            'total'              => 0,
            'processed'          => 0,
            'created'            => 0,
            'updated'            => 0,
            'skipped'            => 0,
            'failed'             => 0,
            'images_imported'    => 0,
            'images_skipped'     => 0,
            'image_errors'       => 0,
            'last_group_id'      => 0,
            'current_stage'      => '',
            'current_product_id' => 0,
            'current_product_created' => 0,
            'variant_offset'     => 0,
            'messages'           => array(),
        ), $counters);
    }

    private function appendMessage(array &$counters, $message)
    {
        $messages = (array) ifset($counters['messages'], array());
        $messages[] = mb_substr((string) $message, 0, 1000);
        $counters['messages'] = array_slice($messages, -20);
    }

    private function acquireLock($run_id)
    {
        return $this->operation_lock->acquire($run_id);
    }

    private function releaseLock($run_id)
    {
        $this->operation_lock->release($run_id);
    }

    private function integerList(array $values)
    {
        $result = array();
        foreach ($values as $value) {
            $value = (int) $value;
            if ($value > 0) {
                $result[$value] = $value;
            }
        }
        return $result ? implode(',', $result) : '0';
    }

    private function decodeStoredValue($value)
    {
        if (!is_string($value)) {
            return $value;
        }
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function flattenValue($value)
    {
        if (!is_array($value)) {
            return array($value);
        }
        if (array_key_exists('value', $value) && array_key_exists('unit', $value)) {
            return array($value);
        }
        $result = array();
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $result[] = $item;
            }
        }
        return $result;
    }

    private function valueSignature($value)
    {
        if (is_array($value)) {
            $copy = $value;
            sort($copy);
            return $this->encodeJson($copy);
        }
        return $this->normalizeText($value);
    }

    private function normalizeText($value)
    {
        $value = mb_strtolower(trim((string) $value));
        return preg_replace('/\s+/u', ' ', $value);
    }

    private function decimal($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = str_replace(array(' ', ','), array('', '.'), $value);
        }
        return is_numeric($value) ? max(0, (float) $value) : null;
    }

    private function encodeJson($value)
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? '{}' : $json;
    }

    private function decodeJson($value)
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : array();
    }

    private function releaseUnitMemory()
    {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    private function logError(Throwable $e, array $context)
    {
        $message = sprintf('%s: %s at %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
        if ($this->logger && method_exists($this->logger, 'logError')) {
            $this->logger->logError($message, $context);
        } else {
            waLog::log('[WbImporter] '.$message.' '.json_encode($context), 'shop/plugins/migrate/migrate_wb.log');
        }
    }
}
