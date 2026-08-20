<?php

class shopMigratePluginOzonImporter
{
    const SCRIPT_TIME_LIMIT_SECONDS = 60;
    const HARD_DEADLINE_SECONDS = 55;
    const DEFAULT_BATCH_SIZE = 25;
    const MAX_BATCH_SIZE = 50;
    const IMAGE_IMPORT_MAX_DIMENSION = 1080;
    const IMAGE_IMPORT_RESIZE_QUALITY = 88;
    const IMAGE_IMPORT_BYTES_PER_PIXEL = 16;
    const IMAGE_IMPORT_MEMORY_SAFETY_BYTES = 33554432;
    const IMAGE_RESIZE_PROCESS_MAX_MEMORY_BYTES = 201326592;
    const IMAGE_DOWNLOAD_MAX_BYTES = 52428800;
    const IMAGE_DOWNLOAD_TIMEOUT_SECONDS = 12;
    const IMAGE_CONNECT_TIMEOUT_SECONDS = 4;
    const OZON_IMAGE_HOST = 'ir.ozone.ru';
    const OZON_IMAGE_CDN_HOST = 'cdn1ozone.webasyst.cloud';
    const OZON_IMAGE_OFFICIAL_CDN_HOST = 'cdn1.ozone.ru';
    const STOREFRONT_SELECTOR_MAX_FEATURES = 3;
    const STOREFRONT_SELECTOR_MAX_CANDIDATES = 12;

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
    private $product_features_selectable_model;
    private $tag_model;
    private $product_tags_model;
    private $product_images_model;
    private $feature_model;
    private $feature_values_models = array();
    private $feature_value_validity = array();
    private $temp_image_dir;
    private $image_cache = array();
    private $product_default_sku = array();
    private $invalid_attribute_pair_keys = array();
    private $hard_deadline_at = 0.0;
    private $prefer_ozon_image_cdn = false;
    private $preferred_ozon_image_host = '';
    private $image_sync_pending = false;
    private $image_sync_next_offset = 0;
    private $image_sync_total = 0;

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
        $this->product_features_selectable_model = class_exists('shopProductFeaturesSelectableModel')
            ? new shopProductFeaturesSelectableModel()
            : null;
        $this->tag_model = new shopTagModel();
        $this->product_tags_model = new shopProductTagsModel();
        $this->product_images_model = new shopProductImagesModel();
        $this->feature_model = new shopFeatureModel();
        $this->temp_image_dir = '';
        try {
            $this->temp_image_dir = rtrim(wa()->getTempPath('plugins/migrate/ozon/', 'shop'), '/\\').DIRECTORY_SEPARATOR;
        } catch (Throwable $e) {
            waLog::log(
                '[OzonImporter] Cannot initialize the image temporary directory: '.$e->getMessage(),
                shopMigratePluginOzonLogger::LOG_FILE
            );
        }
        $this->prefer_ozon_image_cdn = $settings->shouldPreferImageCdn();
        $this->preferred_ozon_image_host = $this->prefer_ozon_image_cdn
            ? self::OZON_IMAGE_CDN_HOST
            : self::OZON_IMAGE_HOST;
    }

    public function import($snapshot_id, array $options = array())
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::SCRIPT_TIME_LIMIT_SECONDS);
        }
        $this->startHardDeadline(self::HARD_DEADLINE_SECONDS);

        $batch_size = $this->normalizeBatchSize(ifset($options['batch_size'], self::DEFAULT_BATCH_SIZE));
        $cursor = max(0, (int) ifset($options['cursor'], 0));
        $image_continuation = !empty($options['image_continuation']);
        $image_offset = max(0, (int) ifset($options['image_offset'], 0));
        $this->restorePreferredOzonImageHost(ifset($options['image_host'], ''));
        $this->ensureRuntimeNotExceeded('load snapshot');
        $snapshot = $this->repository->getSnapshotsModel()->getByIdSafe($snapshot_id);
        if (!$snapshot || $snapshot['status'] !== 'ready') {
            throw new waException('Snapshot is not ready for import');
        }
        $this->ensureRuntimeNotExceeded('warmup invalid pairs');
        $this->warmupInvalidAttributePairs($snapshot);

        $products_model = $this->repository->getProductsModel();
        $total_products = $products_model->countBySnapshot($snapshot_id);
        if ($total_products <= 0) {
            return $this->buildImportResponse(array(
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
            ), 0, 0, 0, 0, 0, true, $batch_size, 0);
        }

        $cleaned_orphans = 0;
        $repaired_mappings = 0;
        if ($cursor === 0 && !$image_continuation) {
            $mapping_cleanup = $this->product_map_model->cleanupMissingShopEntitiesForSnapshot($snapshot_id);
            $repaired_mappings = (int) ifset($mapping_cleanup['total'], 0);
            $cleaned_orphans = $this->cleanupIncompleteSnapshotProducts($snapshot_id);
            if ($repaired_mappings > 0) {
                waLog::log(
                    sprintf(
                        '[OzonImporter] Repaired %d stale product mappings before import (deleted=%d, stale_sku=%d)',
                        $repaired_mappings,
                        (int) ifset($mapping_cleanup['deleted_products'], 0),
                        (int) ifset($mapping_cleanup['cleared_skus'], 0)
                    ),
                    shopMigratePluginOzonLogger::LOG_FILE
                );
            }
        }

        $this->ensureRuntimeNotExceeded('warmup mappers');
        $this->type_mapper->warmup($snapshot_id);
        $this->category_mapper->warmup($snapshot_id);
        $this->stock_mapper->warmup($snapshot_id);
        $this->feature_mapper->warmup($snapshot_id);

        $result = array('created' => 0, 'updated' => 0, 'skipped' => 0);

        $total_units = $products_model->countImportUnits($snapshot_id);
        if ($cursor > $total_units) {
            $cursor = $total_units;
        }
        if ($cursor >= $total_units) {
            return $this->buildImportResponse(
                $result,
                $cursor,
                $cursor,
                0,
                $total_products,
                $total_products,
                true,
                $batch_size,
                $cleaned_orphans,
                $repaired_mappings
            );
        }

        $summary_limit = $image_continuation ? 1 : $batch_size;
        $summaries = $products_model->getImportUnitSummaries($snapshot_id, $cursor, $summary_limit);
        $selected_summaries = array();
        $planned_products = 0;
        foreach ($summaries as $summary) {
            $unit_products = max(1, (int) ifset($summary['products_count'], 1));
            if ($planned_products > 0 && $planned_products + $unit_products > $batch_size) {
                break;
            }
            $selected_summaries[] = $summary;
            $planned_products += $unit_products;
        }
        if (!$selected_summaries && $summaries) {
            $selected_summaries[] = reset($summaries);
        }

        $this->ensureRuntimeNotExceeded('load import page');
        $products = $products_model->getProductsForImportUnits($snapshot_id, $selected_summaries);
        list($grouped_products, $single_products) = $this->partitionProductsByModelInfo($products);
        $units = $this->sortImportUnits($this->buildImportUnits($grouped_products, $single_products));
        $batch_product_ids = $this->collectProductIdsFromUnits($units);
        $attribute_values = $batch_product_ids ? $this->groupValuesByProduct($snapshot_id, $batch_product_ids) : array();
        $stocks = $batch_product_ids ? $this->groupStocksByOffer($snapshot_id, $batch_product_ids) : array();

        $processed_units = 0;
        $batch_products = 0;
        $image_pending = false;
        $next_image_offset = 0;
        $image_total = 0;
        foreach ($units as $unit) {
            if ($processed_units > 0 && !$this->hasRuntimeBudget(5)) {
                break;
            }
            $this->resetImageSyncState();
            $continuation_handled = false;
            if ($image_continuation && $processed_units === 0) {
                $continuation_handled = $this->continueUnitImages($unit, $image_offset);
                if (!$continuation_handled) {
                    waLog::log(
                        sprintf(
                            '[OzonImporter] Image continuation could not resolve its product mapping; unit will be imported again (cursor=%d)',
                            $cursor
                        ),
                        shopMigratePluginOzonLogger::LOG_FILE
                    );
                    $this->importUnit($snapshot_id, $unit, $attribute_values, $stocks, $result);
                }
            } else {
                $this->importUnit($snapshot_id, $unit, $attribute_values, $stocks, $result);
            }
            $this->releaseUnitCaches();

            if ($this->image_sync_pending) {
                $image_pending = true;
                $next_image_offset = $this->image_sync_next_offset;
                $image_total = $this->image_sync_total;
                break;
            }

            $processed_units++;
            $batch_products += max(1, (int) ifset($unit['products_count'], 1));
            if ($continuation_handled) {
                break;
            }
        }
        unset($products, $grouped_products, $single_products, $attribute_values, $stocks, $units);

        if ($processed_units === 0 && !$image_pending) {
            // A missing row must not lock the cursor forever; it is counted as skipped.
            $processed_units = count($selected_summaries);
            $batch_products = $planned_products;
            $result['skipped'] += $batch_products;
        }

        $next_cursor = min($total_units, $cursor + $processed_units);
        $processed_total = $products_model->countProductsBeforeImportUnitOffset($snapshot_id, $next_cursor);
        $done = !$image_pending && $next_cursor >= $total_units;

        $this->logBatchMemory($snapshot_id, $cursor, $next_cursor, $batch_products);

        return $this->buildImportResponse(
            $result,
            $cursor,
            $next_cursor,
            $batch_products,
            $processed_total,
            $total_products,
            $done,
            $batch_size,
            $cleaned_orphans,
            $repaired_mappings,
            $image_pending,
            $next_image_offset,
            $image_total
        );
    }

    private function normalizeBatchSize($batch_size)
    {
        $batch_size = (int) $batch_size;
        if ($batch_size <= 0) {
            $batch_size = self::DEFAULT_BATCH_SIZE;
        }
        return min(self::MAX_BATCH_SIZE, max(1, $batch_size));
    }

    private function buildImportUnits(array $grouped_products, array $single_products)
    {
        $units = array();
        foreach ($grouped_products as $group) {
            $product_ids = array_keys(ifset($group['items'], array()));
            $units[] = array(
                'type'           => 'group',
                'payload'        => $group,
                'product_ids'    => $product_ids,
                'offer_ids'      => $this->extractOfferIdsFromGroup($group),
                'products_count' => count($product_ids),
            );
        }
        foreach ($single_products as $product_id => $item) {
            $product = $item['product'];
            $units[] = array(
                'type'           => 'single',
                'payload'        => $item,
                'product_ids'    => array($product_id),
                'offer_ids'      => array((string) ifset($product['offer_id'], '')),
                'products_count' => 1,
            );
        }
        return $units;
    }

    private function sortImportUnits(array $units)
    {
        usort($units, function (array $left, array $right) {
            $left_ids = (array) ifset($left['product_ids'], array());
            $right_ids = (array) ifset($right['product_ids'], array());
            $left_id = $left_ids ? min($left_ids) : PHP_INT_MAX;
            $right_id = $right_ids ? min($right_ids) : PHP_INT_MAX;
            return $left_id <=> $right_id;
        });
        return $units;
    }

    private function extractOfferIdsFromGroup(array $group)
    {
        $offer_ids = array();
        foreach ((array) ifset($group['items'], array()) as $item) {
            $product = ifset($item['product'], array());
            $offer_id = (string) ifset($product['offer_id'], '');
            if ($offer_id !== '') {
                $offer_ids[] = $offer_id;
            }
        }
        return $offer_ids;
    }

    private function collectProductIdsFromUnits(array $units)
    {
        $product_ids = array();
        foreach ($units as $unit) {
            foreach ((array) ifset($unit['product_ids'], array()) as $product_id) {
                $product_id = (int) $product_id;
                if ($product_id > 0) {
                    $product_ids[$product_id] = $product_id;
                }
            }
        }
        return array_values($product_ids);
    }

    private function resetImageSyncState()
    {
        $this->image_sync_pending = false;
        $this->image_sync_next_offset = 0;
        $this->image_sync_total = 0;
    }

    private function continueUnitImages(array $unit, $image_offset)
    {
        $image_offset = max(0, (int) $image_offset);
        if ((string) ifset($unit['type'], '') === 'group') {
            $payload = (array) ifset($unit['payload'], array());
            $items = (array) ifset($payload['items'], array());
            $shop_product_id = 0;
            $primary_urls = array();
            $additional_urls = array();
            $sku_primary_urls = array();

            foreach ($items as $item) {
                $product = (array) ifset($item['product'], array());
                $details = (array) ifset($item['details'], array());
                $primary_url = $this->resolvePrimaryImageUrl($details);
                if ($primary_url) {
                    $primary_urls[] = $primary_url;
                }
                $additional_urls = array_merge($additional_urls, $this->collectImageUrls($details));

                $offer_id = (string) ifset($product['offer_id'], '');
                $mapping = $offer_id !== '' ? $this->product_map_model->getByOffer($offer_id) : null;
                if (!$mapping) {
                    continue;
                }
                $mapped_product_id = (int) ifset($mapping['shop_product_id'], 0);
                if (!$shop_product_id) {
                    $shop_product_id = $mapped_product_id;
                }
                if ($mapped_product_id !== $shop_product_id) {
                    continue;
                }
                $sku_id = (int) ifset($mapping['shop_sku_id'], 0);
                if ($sku_id) {
                    $sku_primary_urls[$sku_id] = $primary_url;
                }
            }

            if (!$shop_product_id || !$this->product_model->getById($shop_product_id)) {
                return false;
            }

            $image_map = $this->synchronizeProductImages(
                $shop_product_id,
                $primary_urls,
                $additional_urls,
                $image_offset
            );
            foreach ($sku_primary_urls as $sku_id => $primary_url) {
                $this->product_skus_model->updateById($sku_id, array(
                    'image_id' => $this->getImageIdFromMap($shop_product_id, $primary_url, $image_map),
                ));
            }
            return true;
        }

        $item = (array) ifset($unit['payload'], array());
        $product = (array) ifset($item['product'], array());
        $details = isset($item['details']) ? $item['details'] : null;
        if (!is_array($details)) {
            $details = !empty($product['details']) ? json_decode($product['details'], true) : array();
        }
        if (!is_array($details)) {
            $details = array();
        }
        $offer_id = (string) ifset($product['offer_id'], '');
        $mapping = $offer_id !== '' ? $this->product_map_model->getByOffer($offer_id) : null;
        if (!$mapping) {
            return false;
        }

        $shop_product_id = (int) ifset($mapping['shop_product_id'], 0);
        if (!$shop_product_id || !$this->product_model->getById($shop_product_id)) {
            return false;
        }
        $primary_url = $this->resolvePrimaryImageUrl($details);
        $image_map = $this->synchronizeProductImages(
            $shop_product_id,
            $primary_url ? array($primary_url) : array(),
            $this->collectImageUrls($details),
            $image_offset
        );
        $sku_id = (int) ifset($mapping['shop_sku_id'], 0);
        if ($sku_id) {
            $this->product_skus_model->updateById($sku_id, array(
                'image_id' => $this->getImageIdFromMap($shop_product_id, $primary_url, $image_map),
            ));
        }
        return true;
    }

    private function findFirstPendingUnitCursor(array $units, array $mapped_offer_ids)
    {
        foreach ($units as $index => $unit) {
            if (!$this->isUnitFullyMapped($unit, $mapped_offer_ids)) {
                return $index;
            }
        }
        return count($units);
    }

    private function isUnitFullyMapped(array $unit, array $mapped_offer_ids)
    {
        $offer_ids = (array) ifset($unit['offer_ids'], array());
        if (!$offer_ids) {
            return false;
        }
        foreach ($offer_ids as $offer_id) {
            if ((string) $offer_id === '' || empty($mapped_offer_ids[(string) $offer_id])) {
                return false;
            }
        }
        return true;
    }

    private function importUnit($snapshot_id, array $unit, array $attribute_values, array $stocks, array &$result)
    {
        $this->ensureRuntimeNotExceeded($unit['type'] === 'group' ? 'import grouped product' : 'import single product');
        try {
            if ($unit['type'] === 'group') {
                $is_new = $this->importGroupedProduct($snapshot_id, $unit['payload'], $attribute_values, $stocks);
            } else {
                $product = $unit['payload']['product'];
                $is_new = $this->importProduct($snapshot_id, $product, $attribute_values, $stocks);
            }
            if ($is_new === null) {
                $result['skipped']++;
            } elseif ($is_new) {
                $result['created']++;
            } else {
                $result['updated']++;
            }
        } catch (Throwable $e) {
            if ($this->isRuntimeExceededException($e)) {
                throw $e;
            }
            waLog::log(
                sprintf(
                    '[OzonImporter] Unit failed: snapshot=%d type=%s product_ids=%s; %s: %s at %s:%d',
                    (int) $snapshot_id,
                    (string) ifset($unit['type'], 'unknown'),
                    implode(',', array_map('intval', (array) ifset($unit['product_ids'], array()))),
                    get_class($e),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ),
                shopMigratePluginOzonLogger::LOG_FILE
            );
            $result['skipped']++;
        }
    }

    private function isRuntimeExceededException($e)
    {
        return strpos($e->getMessage(), 'Import exceeded '.self::HARD_DEADLINE_SECONDS.' seconds at phase:') === 0;
    }

    private function countProductsBeforeCursor(array $units, $cursor)
    {
        $cursor = min(max(0, (int) $cursor), count($units));
        $count = 0;
        for ($i = 0; $i < $cursor; $i++) {
            $count += max(1, (int) ifset($units[$i]['products_count'], 1));
        }
        return $count;
    }

    private function buildImportResponse(array $result, $cursor, $next_cursor, $batch_products, $processed_total, $total_products, $done, $batch_size, $cleaned_orphans, $repaired_mappings = 0, $image_pending = false, $image_offset = 0, $image_total = 0)
    {
        $progress = $total_products > 0 ? round(min(100, ($processed_total / $total_products) * 100), 1) : 100;
        $result += array('created' => 0, 'updated' => 0, 'skipped' => 0);
        $result['cursor'] = (int) $cursor;
        $result['next_cursor'] = (int) $next_cursor;
        $result['processed'] = (int) $batch_products;
        $result['processed_total'] = (int) $processed_total;
        $result['total'] = (int) $total_products;
        $result['done'] = (bool) $done;
        $result['progress'] = $progress;
        $result['batch_size'] = (int) $batch_size;
        $result['cleaned_orphans'] = (int) $cleaned_orphans;
        $result['repaired_mappings'] = (int) $repaired_mappings;
        $result['image_pending'] = (bool) $image_pending;
        $result['image_offset'] = max(0, (int) $image_offset);
        $result['image_total'] = max(0, (int) $image_total);
        $result['image_host'] = (string) $this->preferred_ozon_image_host;
        return $result;
    }

    private function deleteIncompleteProductIfEmpty($product_id)
    {
        $product_id = (int) $product_id;
        if ($product_id <= 0) {
            return;
        }

        $sku_count = (int) $this->product_skus_model->query(
            "SELECT COUNT(*) FROM shop_product_skus WHERE product_id = i:product_id",
            array('product_id' => $product_id)
        )->fetchField();
        if ($sku_count > 0) {
            return;
        }

        $map_count = (int) $this->product_map_model->query(
            "SELECT COUNT(*) FROM shop_migrate_ozon_product_map WHERE shop_product_id = i:product_id",
            array('product_id' => $product_id)
        )->fetchField();
        if ($map_count > 0) {
            return;
        }

        $this->product_model->delete(array($product_id));
        waLog::log(
            sprintf('[OzonImporter] Removed incomplete new Ozon product without SKU: shop_product_id=%d', $product_id),
            shopMigratePluginOzonLogger::LOG_FILE
        );
    }

    private function cleanupIncompleteSnapshotProducts($snapshot_id)
    {
        $sql = "
            SELECT DISTINCT p.id
            FROM shop_product p
            JOIN shop_migrate_ozon_products ozon_p
                ON ozon_p.snapshot_id = i:snapshot_id
                AND ozon_p.name = p.name
            LEFT JOIN shop_product_skus s ON s.product_id = p.id
            LEFT JOIN shop_migrate_ozon_product_map m ON m.shop_product_id = p.id
            WHERE (p.sku_id IS NULL OR p.sku_id = 0)
                AND s.id IS NULL
                AND m.id IS NULL
            LIMIT 500
        ";
        $rows = $this->product_model->query($sql, array('snapshot_id' => (int) $snapshot_id))->fetchAll();
        if (!$rows) {
            return 0;
        }

        $ids = array_values(array_filter(array_map(function ($row) {
            return (int) ifset($row['id']);
        }, $rows)));
        if (!$ids) {
            return 0;
        }

        $this->product_model->delete($ids);
        $count = count($ids);
        if ($count > 0) {
            waLog::log(
                sprintf('[OzonImporter] Removed %d incomplete Ozon products without SKU before batched import', $count),
                shopMigratePluginOzonLogger::LOG_FILE
            );
        }
        return $count;
    }

    private function hasRuntimeBudget($reserve_seconds)
    {
        return $this->hard_deadline_at <= 0
            || microtime(true) + max(0, (int) $reserve_seconds) < $this->hard_deadline_at;
    }

    private function releaseUnitCaches()
    {
        $this->image_cache = array();
        $this->product_default_sku = array();
        if (count($this->feature_value_validity) > 2000) {
            $this->feature_value_validity = array();
        }
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    private function logBatchMemory($snapshot_id, $cursor, $next_cursor, $products)
    {
        waLog::log(
            sprintf(
                '[OzonImporter] Batch snapshot=%d cursor=%d..%d products=%d memory=%.1fM peak=%.1fM',
                (int) $snapshot_id,
                (int) $cursor,
                (int) $next_cursor,
                (int) $products,
                memory_get_usage(true) / 1048576,
                memory_get_peak_usage(true) / 1048576
            ),
            shopMigratePluginOzonLogger::LOG_FILE
        );
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
            'Import exceeded %d seconds at phase: %s',
            self::HARD_DEADLINE_SECONDS,
            (string) $phase
        ));
    }

    private function partitionProductsByModelInfo(array $products)
    {
        $grouped = array();
        $singles = array();
        foreach ($products as $product_id => $product) {
            $this->ensureRuntimeNotExceeded('prepare product groups');
            $model_id = (int) ifset($product['model_id'], 0);
            $model_count = (int) ifset($product['model_count'], 0);
            if ($model_id > 0 && $model_count > 1) {
                $details = $product['details'] ? json_decode($product['details'], true) : array();
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
        $this->ensureRuntimeNotExceeded('import product payload');
        $skip_features_for_pair = $this->shouldSkipFeaturesForProduct($product);
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

        $created_product_id = null;
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
            $created_product_id = $product_id;
        }

        try {
            $this->assignCategory($product_id, $category_id);

            $sku_id = $this->ensureSku($product_id, $product['offer_id'], $product, $details);
            $this->ensureDefaultSkuAssigned($product_id, $sku_id);

            if ($this->settings->getFeatureImportMode() === shopMigratePluginOzonSettings::FEATURE_MODE_AUTO) {
                if ($skip_features_for_pair) {
                    $this->clearAllProductFeatures($product_id);
                } else {
                    $product_attributes = ifset($attribute_values[$product['product_id']], array());
                    $this->assignFeatures($snapshot_id, $product_id, $type_id, $attribute_values, $product['product_id']);
                    $this->assignCollectedTags($product_id, $product_attributes);
                }
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
        } catch (Throwable $e) {
            $this->deleteIncompleteProductIfEmpty($created_product_id);
            throw $e;
        }

        return $existing_map ? false : true;
    }

    private function findExistingMapInGroup(array $items)
    {
        foreach ($items as $item) {
            $this->ensureRuntimeNotExceeded('search existing group map');
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
        $this->ensureRuntimeNotExceeded('import grouped product payload');
        if (empty($group['items'])) {
            return null;
        }
        $items = $group['items'];
        $skip_features_for_group = $this->shouldSkipFeaturesForGroup($items);
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

        $created_product_id = null;
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
            $created_product_id = $product_id;
        }

        try {
            $this->assignCategory($product_id, $category_id);

            $primary_urls = array();
            $additional_urls = array();
            $variant_primary_urls = array();
            foreach ($items as $item_product_id => $item) {
                $this->ensureRuntimeNotExceeded('collect grouped product images');
                $primary = $this->resolvePrimaryImageUrl($item['details']);
                if ($primary) {
                    $primary_urls[] = $primary;
                }
                $variant_primary_urls[$item_product_id] = $primary;
                $additional_urls = array_merge($additional_urls, $this->collectImageUrls($item['details']));
            }

            $active_offer_ids = array();
            $sku_ids = array();
            $selector_features_by_product = array();
            $tag_mode = $this->resolveTagImportMode();

            if ($this->settings->getFeatureImportMode() === shopMigratePluginOzonSettings::FEATURE_MODE_AUTO) {
                if ($skip_features_for_group) {
                    $variant_attributes = array_fill_keys($product_ids, array());
                    $this->clearAllProductFeatures($product_id);
                } else {
                    list($common_attributes, $variant_attributes) = $this->splitAttributesByVariance($product_ids, $attribute_values);
                    $this->applyFeatureAttributes($snapshot_id, $product_id, $type_id, $common_attributes);
                    if ($this->shouldAssignTagsToProduct($tag_mode)) {
                        $this->assignCollectedTags($product_id, $this->collectAttributesByProductIds($product_ids, $attribute_values));
                    }
                }
            } else {
                $variant_attributes = array_fill_keys($product_ids, array());
            }

            foreach ($items as $item_product_id => $item) {
                $this->ensureRuntimeNotExceeded('import grouped product sku');
                $variant_product = $item['product'];
                $variant_details = $item['details'];
                $sku_name = $this->resolveSkuName($product_data['name'], $variant_product, $variant_details);
                $sku_id = $this->ensureSku($product_id, $variant_product['offer_id'], $variant_product, $variant_details, array(
                    'name'      => $sku_name,
                ));
                $this->ensureDefaultSkuAssigned($product_id, $sku_id);
                $sku_ids[$item_product_id] = $sku_id;
                if ($this->settings->getFeatureImportMode() === shopMigratePluginOzonSettings::FEATURE_MODE_AUTO) {
                    if ($skip_features_for_group) {
                        $this->product_features_model->deleteByField(array(
                            'product_id' => $product_id,
                            'sku_id'     => $sku_id,
                        ));
                    } else {
                        $attributes = ifset($variant_attributes[$item_product_id], array());
                        if (!$this->shouldAssignTagsToSku($tag_mode)) {
                            $attributes = $this->filterTagAttributes($attributes);
                        }
                        $attribute_features = $this->assignSkuFeaturesFromAttributes(
                            $snapshot_id,
                            $product_id,
                            $type_id,
                            $sku_id,
                            $attributes
                        );
                        $selector_features_by_product[$item_product_id] = $this->collectSafeSelectorFeatures(
                            $attributes,
                            $attribute_features
                        );
                    }
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
            if ($created_product_id !== null
                && !$skip_features_for_group
                && $this->settings->getFeatureImportMode() === shopMigratePluginOzonSettings::FEATURE_MODE_AUTO
                && count($sku_ids) > 1
            ) {
                $this->configureStorefrontSkuSelectors($product_id, $sku_ids, $selector_features_by_product);
            }
            $this->finalizeProductCounters($product_id);

            $image_map = $this->synchronizeProductImages($product_id, $primary_urls, $additional_urls);
            foreach ($sku_ids as $item_product_id => $sku_id) {
                $primary_image_url = ifset($variant_primary_urls[$item_product_id]);
                $sku_image_id = $this->getImageIdFromMap($product_id, $primary_image_url, $image_map);
                $this->product_skus_model->updateById($sku_id, array('image_id' => $sku_image_id));
            }
        } catch (Throwable $e) {
            $this->deleteIncompleteProductIfEmpty($created_product_id);
            throw $e;
        }

        return $existing_map ? false : true;
    }

    private function warmupInvalidAttributePairs(array $snapshot)
    {
        $this->invalid_attribute_pair_keys = array();
        $meta = array();
        if (!empty($snapshot['meta']) && is_string($snapshot['meta'])) {
            $decoded = json_decode($snapshot['meta'], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }
        foreach ((array) ifset($meta['invalid_attribute_pairs'], array()) as $pair) {
            $category_id = (int) ifset($pair['description_category_id']);
            $type_id = (int) ifset($pair['type_id']);
            if ($category_id > 0 && $type_id > 0) {
                $this->invalid_attribute_pair_keys[$category_id.':'.$type_id] = true;
            }
        }
    }

    private function shouldSkipFeaturesForProduct(array $product)
    {
        $key = $this->getCategoryTypeKeyFromProduct($product);
        return $key !== '' && isset($this->invalid_attribute_pair_keys[$key]);
    }

    private function shouldSkipFeaturesForGroup(array $items)
    {
        foreach ($items as $item) {
            if (!empty($item['product']) && $this->shouldSkipFeaturesForProduct($item['product'])) {
                return true;
            }
        }
        return false;
    }

    private function getCategoryTypeKeyFromProduct(array $product)
    {
        $category_id = (int) ifset($product['description_category_id']);
        $type_id = (int) ifset($product['type_id']);
        if ($category_id <= 0 || $type_id <= 0) {
            return '';
        }
        return $category_id.':'.$type_id;
    }

    private function clearAllProductFeatures($product_id)
    {
        $product_id = (int) $product_id;
        if ($product_id <= 0) {
            return;
        }
        $this->product_features_model->deleteByField('product_id', $product_id);
    }

    private function buildProductData(
        array $product,
        array $details,
        $type_id,
        $category_id,
        $sku_type = shopProductModel::SKU_TYPE_FLAT
    )
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
            'sku_type'       => (int) $sku_type,
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
            return array();
        }
        list($payload, $feature_refs, $attribute_features) = $this->buildFeaturePayload(
            $attributes,
            $snapshot_id,
            $shop_type_id
        );
        $this->saveSkuFeaturePayload($product_id, $sku_id, $payload, $feature_refs);
        return $attribute_features;
    }

    private function buildFeaturePayload(array $attributes, $snapshot_id, $shop_type_id, $product_id_for_side_effects = null)
    {
        $payload = array();
        $feature_refs = array();
        $attribute_features = array();
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
                if (!array_key_exists($code, $payload)) {
                    $payload[$code] = array();
                } elseif (!$this->isSequentialArray($payload[$code])) {
                    $payload[$code] = array($payload[$code]);
                }
                $values = $this->isSequentialArray($value) ? $value : array($value);
                foreach ($values as $item_value) {
                    $payload[$code][] = $item_value;
                }
            } else {
                $single_value = is_array($value) && $this->isSequentialArray($value) ? reset($value) : $value;
                if (array_key_exists($code, $payload) && $this->isSequentialArray($payload[$code])) {
                    // The same Shop-Script feature was already promoted to multiple earlier in this payload.
                    $this->ensureFeatureSelectable($feature, true);
                    $payload[$code][] = $single_value;
                } else {
                    $payload[$code] = $single_value;
                }
            }
            $feature_refs[$code] = $feature;
            if (isset($attribute['attribute_id'])) {
                $attribute_features[(string) $attribute['attribute_id']] = $feature;
            }
        }

        return array($payload, $feature_refs, $attribute_features);
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
                $value_ids = $this->resolveFeatureValueIds($feature, $item_value);
                foreach ($value_ids as $value_id) {
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
        }
        if ($rows) {
            $this->product_features_model->multipleInsert($rows, waModel::INSERT_IGNORE);
        }
    }

    private function collectSafeSelectorFeatures(array $attributes, array $attribute_features)
    {
        $attributes_by_id = array();
        foreach ($attributes as $attribute) {
            if (!isset($attribute['attribute_id'])) {
                continue;
            }
            $attribute_id = (string) $attribute['attribute_id'];
            if (!isset($attributes_by_id[$attribute_id])) {
                $attributes_by_id[$attribute_id] = array();
            }
            $attributes_by_id[$attribute_id][] = $attribute;
        }

        $result = array();
        $duplicate_feature_ids = array();
        foreach ($attributes_by_id as $attribute_id => $attribute_entries) {
            if (count($attribute_entries) !== 1 || !isset($attribute_features[$attribute_id])) {
                continue;
            }
            $attribute = reset($attribute_entries);
            if ($this->extractUnambiguousSelectorValue(ifset($attribute['value'])) === null) {
                continue;
            }
            $feature = $attribute_features[$attribute_id];
            if (!$this->isSafeSelectorFeature($feature, $attribute)) {
                continue;
            }
            $feature_id = (int) $feature['id'];
            if (isset($result[$feature_id])) {
                $duplicate_feature_ids[$feature_id] = true;
                continue;
            }
            $result[$feature_id] = $feature;
        }

        foreach ($duplicate_feature_ids as $feature_id => $_) {
            unset($result[$feature_id]);
        }
        return $result;
    }

    private function extractUnambiguousSelectorValue($value)
    {
        $value = $this->decodeAttributeValue($value);
        if (is_array($value)) {
            if (array_key_exists('value', $value)) {
                return $this->extractUnambiguousSelectorValue($value['value']);
            }
            if (!$this->isSequentialArray($value) || count($value) !== 1) {
                return null;
            }
            return $this->extractUnambiguousSelectorValue(reset($value));
        }
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '' || mb_strlen($value, 'UTF-8') > 255) {
            return null;
        }
        if (mb_strpos($value, ',', 0, 'UTF-8') !== false || preg_match('/[\r\n]/u', $value)) {
            return null;
        }
        return $value;
    }

    private function isSafeSelectorFeature($feature, array $attribute)
    {
        if (!is_array($feature) || empty($feature['id']) || empty($feature['available_for_sku'])) {
            return false;
        }
        if (ifset($feature['status']) !== shopFeatureModel::STATUS_PUBLIC) {
            return false;
        }

        $type = (string) ifset($feature['type'], '');
        if ($type === ''
            || $type === shopFeatureModel::TYPE_TEXT
            || $type === shopFeatureModel::TYPE_DIVIDER
            || strpos($type, '2d.') === 0
            || strpos($type, '3d.') === 0
        ) {
            return false;
        }

        return !$this->isTechnicalSelectorFeature($feature, $attribute);
    }

    private function isTechnicalSelectorFeature(array $feature, array $attribute)
    {
        $attribute_name = $this->getNormalizedAttributeName($attribute);
        $feature_name = $this->normalizeSelectorText(ifset($feature['name'], ''));
        $text = trim($attribute_name.' '.$feature_name);
        $blocked_fragments = array(
            'артикул',
            'штрихкод',
            'код продавца',
            'идентификатор',
            'тн вэд',
            'маркиров',
            'для объединения',
            'объединить',
            'для шаблона',
            'аннотац',
            'описан',
            'видео',
            'ссылк',
            'сертификат',
            'документ',
            'бренд',
            'производител',
            'страна происхождения',
            'гарантийн',
            'срок годности',
            'дата производства',
            'партномер',
        );
        foreach ($blocked_fragments as $fragment) {
            if ($text !== '' && mb_strpos($text, $fragment, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        $code = strtolower((string) ifset($feature['code'], ''));
        return in_array($code, array('id', 'sku', 'gtin', 'weight'), true)
            || strpos($code, 'ozon_video') !== false
            || in_array($attribute_name, array('название', 'модель', 'название модели'), true)
            || in_array($feature_name, array('название', 'модель', 'название модели'), true);
    }

    private function configureStorefrontSkuSelectors($product_id, array $sku_ids, array $features_by_product)
    {
        try {
            if (!$this->product_features_selectable_model) {
                $this->logStorefrontSelectorDecision($product_id, 'skipped: selectable features model is unavailable');
                return;
            }
            $common_features = $this->intersectSelectorFeatures($sku_ids, $features_by_product);
            if (!$common_features) {
                $this->logStorefrontSelectorDecision(
                    $product_id,
                    sprintf('skipped: no safe attributes cover all %d SKUs', count($sku_ids))
                );
                return;
            }

            $usable_features = $this->loadUsableSelectorFeatures($product_id, $sku_ids, $common_features);
            if (!$usable_features) {
                $this->logStorefrontSelectorDecision(
                    $product_id,
                    'skipped: candidate attributes do not contain one distinct saved value for every SKU'
                );
                return;
            }

            $selected_feature_ids = $this->findMinimalUniqueSelectorFeatureIds($sku_ids, $usable_features);
            if (!$selected_feature_ids) {
                $this->logStorefrontSelectorDecision(
                    $product_id,
                    sprintf(
                        'skipped: no unique combination of up to %d safe attributes was found',
                        self::STOREFRONT_SELECTOR_MAX_FEATURES
                    )
                );
                return;
            }

            $labels = array();
            foreach ($selected_feature_ids as $feature_id) {
                $feature = $usable_features[$feature_id]['feature'];
                $this->ensureFeatureSelectable($feature, true);
                $label = trim((string) ifset($feature['name'], $feature['code']));
                $labels[] = str_replace(array("\r", "\n"), ' ', $label).' (#'.(int) $feature_id.')';
            }

            $product = new shopProduct((int) $product_id);
            $this->product_features_selectable_model->setFeatureIds($product, $selected_feature_ids);
            $this->logStorefrontSelectorDecision(
                $product_id,
                'configured: '.implode(', ', $labels)
            );
        } catch (Throwable $e) {
            $this->logStorefrontSelectorDecision($product_id, 'failed: '.$e->getMessage());
        }
    }

    private function intersectSelectorFeatures(array $sku_ids, array $features_by_product)
    {
        $common = null;
        foreach ($sku_ids as $source_product_id => $_) {
            $features = isset($features_by_product[$source_product_id])
                ? $features_by_product[$source_product_id]
                : array();
            if (!$features) {
                return array();
            }
            $common = $common === null ? $features : array_intersect_key($common, $features);
            if (!$common) {
                return array();
            }
        }
        return $common ?: array();
    }

    private function loadUsableSelectorFeatures($product_id, array $sku_ids, array $features)
    {
        $shop_sku_ids = array_values(array_unique(array_map('intval', $sku_ids)));
        uasort($features, function ($left, $right) {
            $left_priority = $this->getStorefrontSelectorPriority($left);
            $right_priority = $this->getStorefrontSelectorPriority($right);
            if ($left_priority !== $right_priority) {
                return $right_priority <=> $left_priority;
            }
            return (int) $left['id'] <=> (int) $right['id'];
        });
        $features = array_slice($features, 0, self::STOREFRONT_SELECTOR_MAX_CANDIDATES, true);
        $feature_ids = array_values(array_unique(array_map('intval', array_keys($features))));
        if (!$shop_sku_ids || !$feature_ids) {
            return array();
        }

        $rows = $this->product_features_model->getByField(array(
            'product_id' => (int) $product_id,
            'sku_id'     => $shop_sku_ids,
            'feature_id' => $feature_ids,
        ), true);
        $values = array();
        foreach ($rows as $row) {
            $feature_id = (int) ifset($row['feature_id']);
            $sku_id = (int) ifset($row['sku_id']);
            $value_id = (int) ifset($row['feature_value_id']);
            if (!isset($features[$feature_id]) || $sku_id <= 0 || $value_id <= 0) {
                continue;
            }
            if (!isset($values[$feature_id][$sku_id])) {
                $values[$feature_id][$sku_id] = array();
            }
            $values[$feature_id][$sku_id][$value_id] = true;
        }

        $usable = array();
        foreach ($features as $feature_id => $feature) {
            $feature_id = (int) $feature_id;
            $single_values = array();
            foreach ($shop_sku_ids as $sku_id) {
                $value_ids = isset($values[$feature_id][$sku_id])
                    ? array_keys($values[$feature_id][$sku_id])
                    : array();
                if (count($value_ids) !== 1) {
                    $single_values = array();
                    break;
                }
                $single_values[$sku_id] = (int) reset($value_ids);
            }
            $distinct_count = count(array_unique($single_values));
            if (!$single_values || $distinct_count < 2) {
                continue;
            }
            $usable[$feature_id] = array(
                'feature'        => $feature,
                'values'         => $single_values,
                'distinct_count' => $distinct_count,
                'priority'       => $this->getStorefrontSelectorPriority($feature),
            );
        }

        uasort($usable, function ($left, $right) {
            if ($left['priority'] !== $right['priority']) {
                return $right['priority'] <=> $left['priority'];
            }
            if ($left['distinct_count'] !== $right['distinct_count']) {
                return $right['distinct_count'] <=> $left['distinct_count'];
            }
            return (int) $left['feature']['id'] <=> (int) $right['feature']['id'];
        });

        return array_slice($usable, 0, self::STOREFRONT_SELECTOR_MAX_CANDIDATES, true);
    }

    private function getStorefrontSelectorPriority(array $feature)
    {
        $text = $this->normalizeSelectorText(
            ifset($feature['name'], '').' '.ifset($feature['code'], '')
        );
        $rules = array(
            1000 => array('цвет', 'color', 'оттенок'),
            950  => array('размер', 'size', 'ростовка'),
            900  => array('количество в упаковке', 'комплектность', 'комплектация'),
            850  => array('объем', 'вес', 'масса', 'длина', 'ширина', 'высота', 'диаметр'),
            800  => array('вкус', 'аромат', 'запах'),
            700  => array('вариант', 'форма выпуска', 'тип окраски', 'материал'),
            600  => array('назначение', 'сезон'),
        );
        foreach ($rules as $priority => $fragments) {
            foreach ($fragments as $fragment) {
                if (mb_strpos($text, $fragment, 0, 'UTF-8') !== false) {
                    return $priority;
                }
            }
        }
        return 100;
    }

    private function normalizeSelectorText($value)
    {
        if (!is_string($value)) {
            return '';
        }
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace('ё', 'е', $value);
        return preg_replace('/\s+/u', ' ', $value);
    }

    private function findMinimalUniqueSelectorFeatureIds(array $sku_ids, array $features)
    {
        $feature_ids = array_keys($features);
        $shop_sku_ids = array_values(array_unique(array_map('intval', $sku_ids)));
        $max_size = min(self::STOREFRONT_SELECTOR_MAX_FEATURES, count($feature_ids));
        for ($size = 1; $size <= $max_size; $size++) {
            $best = array();
            $best_score = null;
            foreach ($this->buildSelectorFeatureCombinations($feature_ids, $size) as $combination) {
                if (!$this->isUniqueSelectorCombination($shop_sku_ids, $features, $combination)) {
                    continue;
                }
                $score = 0;
                foreach ($combination as $feature_id) {
                    $score += ((int) $features[$feature_id]['priority'] * 1000)
                        + min(999, (int) $features[$feature_id]['distinct_count']);
                }
                if ($best_score === null || $score > $best_score) {
                    $best = $combination;
                    $best_score = $score;
                }
            }
            if ($best) {
                return array_map('intval', $best);
            }
        }
        return array();
    }

    private function buildSelectorFeatureCombinations(array $feature_ids, $size, $offset = 0, array $prefix = array())
    {
        if ($size === 0) {
            return array($prefix);
        }

        $result = array();
        $limit = count($feature_ids) - $size;
        for ($index = $offset; $index <= $limit; $index++) {
            $next_prefix = $prefix;
            $next_prefix[] = $feature_ids[$index];
            $result = array_merge(
                $result,
                $this->buildSelectorFeatureCombinations($feature_ids, $size - 1, $index + 1, $next_prefix)
            );
        }
        return $result;
    }

    private function isUniqueSelectorCombination(array $sku_ids, array $features, array $feature_ids)
    {
        $signatures = array();
        foreach ($sku_ids as $sku_id) {
            $parts = array();
            foreach ($feature_ids as $feature_id) {
                if (!isset($features[$feature_id]['values'][$sku_id])) {
                    return false;
                }
                $parts[] = (int) $features[$feature_id]['values'][$sku_id];
            }
            $signature = implode(':', $parts);
            if (isset($signatures[$signature])) {
                return false;
            }
            $signatures[$signature] = true;
        }
        return count($signatures) === count($sku_ids);
    }

    private function logStorefrontSelectorDecision($product_id, $message)
    {
        waLog::log(
            sprintf('[OzonImporter] Storefront SKU selectors for product #%d: %s', (int) $product_id, $message),
            shopMigratePluginOzonLogger::LOG_FILE
        );
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

    private function resolveFeatureValueIds(array $feature, $value)
    {
        $lookup_value = $this->sanitizeFeatureLookupValue($feature, $value);
        $resolved_ids = $this->feature_model->getValueId($feature, $lookup_value, true);
        $resolved_ids = $this->normalizeFeatureValueIds($resolved_ids);
        if (!$resolved_ids) {
            return array();
        }

        $result = array();
        foreach ($resolved_ids as $value_id) {
            if ($this->isFeatureValueIdValid($feature, $value_id)) {
                $result[$value_id] = $value_id;
            }
        }

        return array_values($result);
    }

    private function sanitizeFeatureLookupValue(array $feature, $value)
    {
        if (!is_array($value) || $this->isSequentialArray($value)) {
            return $value;
        }
        if (!isset($value['id'])) {
            return $value;
        }

        $value_id = (int) $value['id'];
        if ($value_id <= 0 || !$this->isFeatureValueIdValid($feature, $value_id, false)) {
            unset($value['id']);
        } else {
            $value['id'] = $value_id;
        }

        return $value;
    }

    private function normalizeFeatureValueIds($value_ids)
    {
        if ($value_ids === null || $value_ids === false || $value_ids === '') {
            return array();
        }
        $list = is_array($value_ids) ? $value_ids : array($value_ids);
        $result = array();
        foreach ($list as $value_id) {
            if (is_array($value_id)) {
                if (!isset($value_id['id'])) {
                    continue;
                }
                $value_id = $value_id['id'];
            }
            $value_id = (int) $value_id;
            if ($value_id > 0) {
                $result[$value_id] = $value_id;
            }
        }
        return array_values($result);
    }

    private function isFeatureValueIdValid(array $feature, $value_id, $allow_cache = true)
    {
        $feature_id = (int) ifset($feature['id'], 0);
        $value_id = (int) $value_id;
        $type = (string) ifset($feature['type'], '');
        if ($feature_id <= 0 || $value_id <= 0 || $type === '') {
            return false;
        }

        $cache_key = $feature_id.':'.$type.':'.$value_id;
        if ($allow_cache && isset($this->feature_value_validity[$cache_key])) {
            return $this->feature_value_validity[$cache_key];
        }

        $model = $this->getFeatureValuesModel($type);
        if (!$model) {
            if ($allow_cache) {
                $this->feature_value_validity[$cache_key] = false;
            }
            return false;
        }

        $row = $model->getByField(array(
            'id'         => $value_id,
            'feature_id' => $feature_id,
        ));
        $is_valid = !empty($row);
        if ($allow_cache) {
            $this->feature_value_validity[$cache_key] = $is_valid;
        }
        return $is_valid;
    }

    private function getFeatureValuesModel($type)
    {
        $type = (string) $type;
        if ($type === '') {
            return null;
        }
        if (!array_key_exists($type, $this->feature_values_models)) {
            try {
                $this->feature_values_models[$type] = shopFeatureModel::getValuesModel($type);
            } catch (Exception $e) {
                $this->feature_values_models[$type] = null;
            }
        }
        return $this->feature_values_models[$type];
    }

    private function assignStocks($snapshot_id, $product_id, $sku_id, $offer_id, array $stocks_by_offer)
    {
        $stock_payload = array();
        foreach ($this->stock_mapper->getResolvedShopStockIds() as $shop_stock_id) {
            $stock_payload[(int) $shop_stock_id] = 0.0;
        }

        if ($offer_id && !empty($stocks_by_offer[$offer_id])) {
            foreach ($stocks_by_offer[$offer_id] as $stock_row) {
                $shop_stock_id = $this->stock_mapper->resolve($snapshot_id, $stock_row['warehouse_id']);
                if ($shop_stock_id) {
                    if (!isset($stock_payload[$shop_stock_id])) {
                        $stock_payload[$shop_stock_id] = 0.0;
                    }
                    $stock_payload[$shop_stock_id] += (float) $stock_row['quantity'];
                }
            }
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
        $total = $stock_payload ? array_sum($stock_payload) : 0;
        $this->product_skus_model->updateById($sku_id, array('count' => $total));
        $this->product_model->updateById($product_id, array('count' => $total));
    }

    private function synchronizeProductImages($product_id, array $primary_urls, array $additional_urls = array(), $offset = 0)
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

        $old_image_rows = $this->product_images_model->select('id, filename, ext, original_filename')
            ->where('product_id = i:product_id', array('product_id' => (int) $product_id))
            ->order('sort, id')
            ->fetchAll();

        if (!$ordered) {
            if ($old_image_rows) {
                $this->product_images_model->deleteByProducts(array($product_id), true);
            }
            $this->image_cache = array($product_id => array());
            return array();
        }

        $map = array();
        $existing_by_source = array();
        foreach ($old_image_rows as $row) {
            $source_key = $this->getStoredImageSourceKey($row);
            if ($source_key !== '' && !isset($existing_by_source[$source_key])) {
                $existing_by_source[$source_key] = $row;
            }
        }
        foreach ($ordered as $url) {
            $source_key = $this->getImageSourceKey($url);
            if ($source_key !== '' && isset($existing_by_source[$source_key])) {
                $map[$url] = (int) $existing_by_source[$source_key]['id'];
            }
        }

        $offset = min(count($ordered), max(0, (int) $offset));
        $next_offset = $offset;
        $pending = false;
        for ($index = $offset; $index < count($ordered); $index++) {
            $url = $ordered[$index];
            if (isset($map[$url])) {
                $next_offset = $index + 1;
                continue;
            }
            $download_candidates = count($this->buildImageDownloadCandidates($url));
            if (!$this->hasRuntimeBudget(self::IMAGE_DOWNLOAD_TIMEOUT_SECONDS * $download_candidates + 5)) {
                $pending = true;
                $next_offset = $index;
                break;
            }
            $file = $this->downloadImage($url);
            if (!$file) {
                $next_offset = $index + 1;
                continue;
            }
            $original_file = $file;
            $file = $this->resizeImageFileIfNeeded($file, $url);
            if (!$file) {
                waFiles::delete($original_file);
                $next_offset = $index + 1;
                continue;
            }
            if (!$this->canImportImageFile($file, $url)) {
                waFiles::delete($file);
                $next_offset = $index + 1;
                continue;
            }
            try {
                $data = $this->product_images_model->addImage($file, $product_id, $this->resolveImageFilename($url));
                if (!empty($data['id'])) {
                    $map[$url] = (int) $data['id'];
                }
            } catch (Throwable $e) {
                waLog::log('[OzonImporter] Image import failed: '.$e->getMessage(), shopMigratePluginOzonLogger::LOG_FILE);
            } finally {
                waFiles::delete($file);
            }
            $next_offset = $index + 1;
            unset($data);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        $missing = 0;
        foreach ($ordered as $url) {
            if (!isset($map[$url])) {
                $missing++;
            }
        }

        $mapped_image_ids = array_values(array_unique(array_map('intval', array_values($map))));
        $available_count = count($mapped_image_ids);
        if (!$pending && $missing === 0) {
            $mapped_lookup = array_fill_keys($mapped_image_ids, true);
            foreach ($old_image_rows as $row) {
                $image_id = (int) ifset($row['id'], 0);
                if ($image_id && empty($mapped_lookup[$image_id])) {
                    $this->product_images_model->delete($image_id);
                }
            }
        } elseif (!$map && $old_image_rows) {
            foreach ($ordered as $index => $url) {
                $old_row = isset($old_image_rows[$index]) ? $old_image_rows[$index] : reset($old_image_rows);
                $map[$url] = (int) ifset($old_row['id'], 0);
            }
        }

        $this->reorderProductImages($product_id, array_values($map));

        if ($pending) {
            $this->image_sync_pending = true;
            $this->image_sync_next_offset = $next_offset;
            $this->image_sync_total = count($ordered);
            waLog::log(
                sprintf(
                    '[OzonImporter] Image synchronization paused for product #%d at %d/%d; it will continue in the next AJAX batch',
                    $product_id,
                    $next_offset,
                    count($ordered)
                ),
                shopMigratePluginOzonLogger::LOG_FILE
            );
        } elseif ($missing > 0) {
            waLog::log(
                sprintf(
                    '[OzonImporter] Image synchronization completed partially for product #%d: imported_or_matched=%d, unavailable=%d, desired=%d, retained_existing=%d',
                    $product_id,
                    $available_count,
                    $missing,
                    count($ordered),
                    count($old_image_rows)
                ),
                shopMigratePluginOzonLogger::LOG_FILE
            );
        }

        $this->image_cache = array($product_id => $map);
        return $map;
    }

    private function getImageSourceKey($url)
    {
        return strtolower($this->resolveImageFilename($url));
    }

    private function getStoredImageSourceKey(array $row)
    {
        $filename = trim((string) ifset($row['original_filename'], ''));
        if ($filename === '') {
            $filename = trim((string) ifset($row['filename'], ''));
            $extension = trim((string) ifset($row['ext'], ''));
            if ($filename !== '' && $extension !== '') {
                $filename .= '.'.$extension;
            }
        }
        return strtolower(basename($filename));
    }

    private function reorderProductImages($product_id, array $preferred_image_ids)
    {
        $current_rows = $this->product_images_model->select('id, filename, ext')
            ->where('product_id = i:product_id', array('product_id' => (int) $product_id))
            ->order('sort, id')
            ->fetchAll();
        if (!$current_rows) {
            $this->product_model->updateById($product_id, array(
                'image_id'       => null,
                'image_filename' => '',
                'ext'            => null,
            ));
            return;
        }

        $rows_by_id = array();
        foreach ($current_rows as $row) {
            $rows_by_id[(int) $row['id']] = $row;
        }
        $ordered_ids = array();
        foreach ($preferred_image_ids as $image_id) {
            $image_id = (int) $image_id;
            if ($image_id && isset($rows_by_id[$image_id])) {
                $ordered_ids[$image_id] = $image_id;
            }
        }
        foreach ($rows_by_id as $image_id => $row) {
            $ordered_ids[$image_id] = $image_id;
        }

        $sort = 0;
        foreach ($ordered_ids as $image_id) {
            $this->product_images_model->updateById($image_id, array('sort' => $sort++));
        }
        $first_id = reset($ordered_ids);
        $first_row = $first_id ? $rows_by_id[$first_id] : null;
        $this->product_model->updateById($product_id, array(
            'image_id'       => $first_row ? (int) $first_row['id'] : null,
            'image_filename' => $first_row ? (string) $first_row['filename'] : '',
            'ext'            => $first_row ? (string) $first_row['ext'] : null,
        ));
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

    private function canImportImageFile($file, $url)
    {
        $info = @getimagesize($file);
        if (!$info || empty($info[0]) || empty($info[1])) {
            waLog::log('[OzonImporter] Image import skipped: cannot read image dimensions ('.$url.')', shopMigratePluginOzonLogger::LOG_FILE);
            return false;
        }

        $memory_limit = $this->parseMemoryLimitBytes(ini_get('memory_limit'));
        if ($memory_limit <= 0) {
            return true;
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $pixels = $width * $height;
        $file_size = is_file($file) ? (int) filesize($file) : 0;
        $required = (int) ceil($pixels * self::IMAGE_IMPORT_BYTES_PER_PIXEL + $file_size);
        $available = $memory_limit - memory_get_usage(false);

        if ($available < $required + self::IMAGE_IMPORT_MEMORY_SAFETY_BYTES) {
            waLog::log(
                sprintf(
                    '[OzonImporter] Image import skipped: not enough PHP memory for %dx%d image; required about %s, available %s, limit %s (%s)',
                    $width,
                    $height,
                    $this->formatBytes($required),
                    $this->formatBytes($available),
                    $this->formatBytes($memory_limit),
                    $url
                ),
                shopMigratePluginOzonLogger::LOG_FILE
            );
            return false;
        }

        return true;
    }

    private function resizeImageFileIfNeeded($file, $url)
    {
        $info = @getimagesize($file);
        if (!$info || empty($info[0]) || empty($info[1])) {
            return $file;
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $max_dimension = max($width, $height);
        if ($max_dimension <= self::IMAGE_IMPORT_MAX_DIMENSION) {
            return $file;
        }

        // Prefer an isolated process: a decoder cannot exhaust the import request memory.
        if ($this->resizeImageFileWithPhpCliResizer($file, $url)) {
            return $file;
        }

        if (!$this->canDecodeImageInProcess($width, $height, $file)) {
            waLog::log(
                sprintf(
                    '[OzonImporter] Image skipped: %dx%d source cannot be resized safely within the PHP memory limit (%s)',
                    $width,
                    $height,
                    $url
                ),
                shopMigratePluginOzonLogger::LOG_FILE
            );
            return null;
        }

        $errors = array();
        if (class_exists('Imagick')) {
            try {
                $resized = $this->resizeImageFileWithImagick($file);
                $this->logImageResize($resized, $url, 'Imagick');
                return $file;
            } catch (Throwable $e) {
                $errors[] = 'Imagick: '.$e->getMessage();
            }
        } else {
            $errors[] = 'Imagick is not available';
        }

        try {
            $resized = $this->resizeImageFileWithGd($file);
            $this->logImageResize($resized, $url, 'GD');
            return $file;
        } catch (Throwable $e) {
            $errors[] = 'GD: '.$e->getMessage();
        }

        waLog::log(
            '[OzonImporter] Image resize failed and image was skipped: '.implode('; ', $errors).' ('.$url.')',
            shopMigratePluginOzonLogger::LOG_FILE
        );
        return null;
    }

    private function resizeImageFileWithImagick($file)
    {
        $image = new Imagick($file);
        try {
            if ($image->getNumberImages() > 1) {
                $image->setIteratorIndex(0);
                $frame = $image->getImage();
                $image->clear();
                $image->destroy();
                $image = $frame;
            }

            if (method_exists($image, 'autoOrient')) {
                $image->autoOrient();
            } elseif (method_exists($image, 'autoOrientImage')) {
                $image->autoOrientImage();
            }

            $source_width = (int) $image->getImageWidth();
            $source_height = (int) $image->getImageHeight();
            if ($source_width <= 0 || $source_height <= 0) {
                throw new RuntimeException('Cannot read image dimensions');
            }

            $scale = min(
                self::IMAGE_IMPORT_MAX_DIMENSION / $source_width,
                self::IMAGE_IMPORT_MAX_DIMENSION / $source_height,
                1
            );
            $target_width = max(1, (int) round($source_width * $scale));
            $target_height = max(1, (int) round($source_height * $scale));
            if ($scale < 1) {
                $image->thumbnailImage($target_width, $target_height, true);
                if (method_exists($image, 'setImagePage')) {
                    $image->setImagePage(0, 0, 0, 0);
                }
                $image->setImageCompressionQuality(self::IMAGE_IMPORT_RESIZE_QUALITY);
                $image->stripImage();
                $image->writeImage($file);
            }

            return array($source_width, $source_height, (int) $image->getImageWidth(), (int) $image->getImageHeight());
        } finally {
            $image->clear();
            $image->destroy();
        }
    }

    private function resizeImageFileWithGd($file)
    {
        $image = waImage::factory($file, waImage::Gd);
        $source_width = (int) $image->width;
        $source_height = (int) $image->height;
        if ($source_width <= 0 || $source_height <= 0) {
            throw new RuntimeException('Cannot read image dimensions');
        }

        $image->fixImageOrientation();
        $source_width = (int) $image->width;
        $source_height = (int) $image->height;
        $scale = min(
            self::IMAGE_IMPORT_MAX_DIMENSION / $source_width,
            self::IMAGE_IMPORT_MAX_DIMENSION / $source_height,
            1
        );
        $target_width = max(1, (int) round($source_width * $scale));
        $target_height = max(1, (int) round($source_height * $scale));
        if ($scale < 1) {
            $image->resize($target_width, $target_height);
            if (!$image->save($file, self::IMAGE_IMPORT_RESIZE_QUALITY)) {
                throw new RuntimeException('GD cannot write resized image');
            }
        }

        $result = array($source_width, $source_height, (int) $image->width, (int) $image->height);
        unset($image);
        return $result;
    }

    private function logImageResize(array $dimensions, $url, $adapter)
    {
        waLog::log(
            sprintf(
                '[OzonImporter] Image resized before import using %s: %dx%d -> %dx%d (%s)',
                $adapter,
                isset($dimensions[0]) ? (int) $dimensions[0] : 0,
                isset($dimensions[1]) ? (int) $dimensions[1] : 0,
                isset($dimensions[2]) ? (int) $dimensions[2] : 0,
                isset($dimensions[3]) ? (int) $dimensions[3] : 0,
                $url
            ),
            shopMigratePluginOzonLogger::LOG_FILE
        );
    }

    private function canDecodeImageInProcess($width, $height, $file)
    {
        $memory_limit = $this->parseMemoryLimitBytes(ini_get('memory_limit'));
        if ($memory_limit <= 0) {
            return true;
        }
        $file_size = is_file($file) ? (int) filesize($file) : 0;
        $required = (int) ceil((int) $width * (int) $height * self::IMAGE_IMPORT_BYTES_PER_PIXEL + $file_size);
        $available = $memory_limit - memory_get_usage(true) - self::IMAGE_IMPORT_MEMORY_SAFETY_BYTES;
        return $required > 0 && $required <= $available;
    }

    private function resizeImageFileWithPhpCliResizer($file, $url)
    {
        if (!function_exists('proc_open')) {
            return false;
        }

        $php = $this->resolvePhpCliBinary();
        if (!$php) {
            return false;
        }

        $before = @getimagesize($file);
        if (!$before || empty($before[0]) || empty($before[1])) {
            return false;
        }

        $script = tempnam(sys_get_temp_dir(), 'ozon_image_resize_');
        if (!$script) {
            return false;
        }

        $code = <<<'PHP'
<?php
$file = isset($argv[1]) ? $argv[1] : '';
$max_dimension = isset($argv[2]) ? (int) $argv[2] : 1080;
$quality = isset($argv[3]) ? (int) $argv[3] : 88;
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, 'Image file is not available');
    exit(2);
}

function ozon_resize_with_imagick($file, $max_dimension, $quality)
{
    $image = new Imagick($file);
    if ($image->getNumberImages() > 1) {
        $image->setIteratorIndex(0);
        $frame = $image->getImage();
        $image->clear();
        $image->destroy();
        $image = $frame;
    }
    if (method_exists($image, 'autoOrient')) {
        $image->autoOrient();
    } elseif (method_exists($image, 'autoOrientImage')) {
        $image->autoOrientImage();
    }
    $source_width = (int) $image->getImageWidth();
    $source_height = (int) $image->getImageHeight();
    if ($source_width <= 0 || $source_height <= 0) {
        throw new RuntimeException('Cannot read image dimensions');
    }
    $scale = min($max_dimension / $source_width, $max_dimension / $source_height, 1);
    if ($scale < 1) {
        $target_width = max(1, (int) round($source_width * $scale));
        $target_height = max(1, (int) round($source_height * $scale));
        $image->thumbnailImage($target_width, $target_height, true);
        if (method_exists($image, 'setImagePage')) {
            $image->setImagePage(0, 0, 0, 0);
        }
        $image->setImageCompressionQuality($quality);
        $image->stripImage();
        $image->writeImage($file);
    }
    echo $image->getImageWidth().'x'.$image->getImageHeight();
    $image->clear();
    $image->destroy();
}

function ozon_gd_create_from_file($file, $type)
{
    switch ($type) {
        case IMAGETYPE_JPEG:
            if (function_exists('imagecreatefromjpeg')) {
                return @imagecreatefromjpeg($file);
            }
            break;
        case IMAGETYPE_PNG:
            if (function_exists('imagecreatefrompng')) {
                return @imagecreatefrompng($file);
            }
            break;
        case IMAGETYPE_GIF:
            if (function_exists('imagecreatefromgif')) {
                return @imagecreatefromgif($file);
            }
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                return @imagecreatefromwebp($file);
            }
            break;
    }

    return false;
}

function ozon_gd_save_to_file($image, $file, $type, $quality)
{
    switch ($type) {
        case IMAGETYPE_JPEG:
            return function_exists('imagejpeg') && @imagejpeg($image, $file, $quality);
        case IMAGETYPE_PNG:
            $compression = max(0, min(9, 9 - (int) round($quality / 100 * 9)));
            return function_exists('imagepng') && @imagepng($image, $file, $compression);
        case IMAGETYPE_GIF:
            return function_exists('imagegif') && @imagegif($image, $file);
        case IMAGETYPE_WEBP:
            return function_exists('imagewebp') && @imagewebp($image, $file, $quality);
    }

    return false;
}

function ozon_gd_apply_jpeg_orientation($image, $file, $type)
{
    if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data') || !function_exists('imagerotate')) {
        return $image;
    }

    $exif = @exif_read_data($file);
    if (empty($exif['Orientation'])) {
        return $image;
    }

    switch ((int) $exif['Orientation']) {
        case 3:
            $rotated = @imagerotate($image, 180, 0);
            break;
        case 6:
            $rotated = @imagerotate($image, -90, 0);
            break;
        case 8:
            $rotated = @imagerotate($image, 90, 0);
            break;
        default:
            $rotated = null;
            break;
    }

    if ($rotated) {
        imagedestroy($image);
        return $rotated;
    }

    return $image;
}

function ozon_resize_with_gd($file, $max_dimension, $quality)
{
    if (!function_exists('gd_info')) {
        throw new RuntimeException('GD is not available');
    }

    $info = @getimagesize($file);
    if (!$info || empty($info[0]) || empty($info[1]) || empty($info[2])) {
        throw new RuntimeException('Cannot read image dimensions');
    }

    $source = ozon_gd_create_from_file($file, (int) $info[2]);
    if (!$source) {
        throw new RuntimeException('GD cannot open this image type');
    }

    $source = ozon_gd_apply_jpeg_orientation($source, $file, (int) $info[2]);
    $source_width = imagesx($source);
    $source_height = imagesy($source);
    if ($source_width <= 0 || $source_height <= 0) {
        imagedestroy($source);
        throw new RuntimeException('Cannot read image dimensions');
    }

    $scale = min($max_dimension / $source_width, $max_dimension / $source_height, 1);
    if ($scale >= 1) {
        echo $source_width.'x'.$source_height;
        imagedestroy($source);
        return;
    }

    $target_width = max(1, (int) round($source_width * $scale));
    $target_height = max(1, (int) round($source_height * $scale));
    $target = imagecreatetruecolor($target_width, $target_height);
    if (!$target) {
        imagedestroy($source);
        throw new RuntimeException('GD cannot allocate target image');
    }

    if (in_array((int) $info[2], array(IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP), true)) {
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $target_width, $target_height, $transparent);
    }

    if (!imagecopyresampled($target, $source, 0, 0, 0, 0, $target_width, $target_height, $source_width, $source_height)) {
        imagedestroy($target);
        imagedestroy($source);
        throw new RuntimeException('GD cannot resize image');
    }

    if (!ozon_gd_save_to_file($target, $file, (int) $info[2], $quality)) {
        imagedestroy($target);
        imagedestroy($source);
        throw new RuntimeException('GD cannot write resized image');
    }

    echo $target_width.'x'.$target_height;
    imagedestroy($target);
    imagedestroy($source);
}

$errors = array();
if (class_exists('Imagick')) {
    try {
        ozon_resize_with_imagick($file, $max_dimension, $quality);
        exit(0);
    } catch (Exception $e) {
        $errors[] = 'Imagick: '.$e->getMessage();
    }
} else {
    $errors[] = 'Imagick is not available';
}

try {
    ozon_resize_with_gd($file, $max_dimension, $quality);
    exit(0);
} catch (Exception $e) {
    $errors[] = 'GD: '.$e->getMessage();
}

fwrite(STDERR, implode('; ', $errors));
exit(1);
PHP;

        if (file_put_contents($script, $code) === false) {
            @unlink($script);
            return false;
        }

        $child_memory_limit = $this->getImageResizeProcessMemoryLimit();
        $command = escapeshellarg($php)
            .' -d '.escapeshellarg('memory_limit='.$child_memory_limit)
            .' '.escapeshellarg($script)
            .' '.escapeshellarg($file)
            .' '.(int) self::IMAGE_IMPORT_MAX_DIMENSION
            .' '.(int) self::IMAGE_IMPORT_RESIZE_QUALITY;
        $descriptors = array(
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $process = @proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            @unlink($script);
            return false;
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exit_code = proc_close($process);
        @unlink($script);

        if ($exit_code !== 0) {
            if ($stderr !== '') {
                waLog::log('[OzonImporter] Image resize failed via PHP CLI: '.$stderr.' ('.$url.')', shopMigratePluginOzonLogger::LOG_FILE);
            }
            return false;
        }

        $after = @getimagesize($file);
        if ($after && !empty($after[0]) && !empty($after[1])) {
            waLog::log(
                sprintf(
                    '[OzonImporter] Image resized before import: %dx%d -> %dx%d (%s)',
                    (int) $before[0],
                    (int) $before[1],
                    (int) $after[0],
                    (int) $after[1],
                    $url
                ),
                shopMigratePluginOzonLogger::LOG_FILE
            );
        } elseif ($stdout !== '') {
            waLog::log('[OzonImporter] Image resized before import: '.$stdout.' ('.$url.')', shopMigratePluginOzonLogger::LOG_FILE);
        }

        return true;
    }

    private function getImageResizeProcessMemoryLimit()
    {
        $current_limit = $this->parseMemoryLimitBytes(ini_get('memory_limit'));
        if ($current_limit <= 0) {
            return self::IMAGE_RESIZE_PROCESS_MAX_MEMORY_BYTES;
        }
        return min(self::IMAGE_RESIZE_PROCESS_MAX_MEMORY_BYTES, $current_limit);
    }

    private function resolvePhpCliBinary()
    {
        $names = DIRECTORY_SEPARATOR === '\\'
            ? array('php.exe')
            : array('php', 'php8.2');
        foreach ($this->getPhpBaseDirs() as $base_dir) {
            foreach ($names as $name) {
                $candidate = $base_dir.DIRECTORY_SEPARATOR.$name;
                if (is_file($candidate) && is_executable($candidate)) {
                    return $candidate;
                }
            }
        }

        if (defined('PHP_BINARY') && PHP_BINARY && is_file(PHP_BINARY)
            && preg_match('/^php(?:\.exe)?$/i', basename(PHP_BINARY))
        ) {
            return PHP_BINARY;
        }

        return null;
    }

    private function getPhpBaseDirs()
    {
        $base_dirs = array();
        if (defined('PHP_BINARY') && PHP_BINARY) {
            $base_dirs[] = dirname(PHP_BINARY);
        }
        if (defined('PHP_BINDIR') && PHP_BINDIR) {
            $base_dirs[] = PHP_BINDIR;
        }
        $extension_dir = ini_get('extension_dir');
        if ($extension_dir) {
            $base_dirs[] = dirname($extension_dir);
        }

        $result = array();
        foreach ($base_dirs as $base_dir) {
            $base_dir = rtrim((string) $base_dir, '/\\');
            if ($base_dir !== '' && is_dir($base_dir)) {
                $result[$base_dir] = $base_dir;
            }
        }

        return array_values($result);
    }

    private function parseMemoryLimitBytes($value)
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-1') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        switch ($unit) {
            case 'g':
                $number *= 1024;
                // no break
            case 'm':
                $number *= 1024;
                // no break
            case 'k':
                $number *= 1024;
                break;
        }

        return (int) $number;
    }

    private function formatBytes($bytes)
    {
        $bytes = max(0, (float) $bytes);
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }
        return (int) $bytes.' B';
    }

    private function downloadImage($url)
    {
        $url = $this->normalizeImageUrl($url);
        if (!$url) {
            return null;
        }

        $candidate_errors = array();
        foreach ($this->buildImageDownloadCandidates($url) as $candidate_index => $candidate) {
            $path = $this->createImageTempPath($candidate);
            $result = $path
                ? $this->downloadImageToFile($candidate, $path)
                : array('success' => false, 'error' => 'temporary directory is unavailable');
            if (!empty($result['success'])) {
                $image_info = @getimagesize($path);
                if (!$image_info || empty($image_info[0]) || empty($image_info[1])) {
                    $result = array('success' => false, 'error' => 'response is not a readable image');
                }
            }
            if (!empty($result['success'])) {
                if ($candidate_index > 0) {
                    waLog::log(
                        ($this->isOzonImageCdnHost($candidate)
                            ? '[OzonImporter] Image CDN fallback used: '
                            : '[OzonImporter] Image Ozon origin fallback used: ').$candidate,
                        shopMigratePluginOzonLogger::LOG_FILE
                    );
                }
                $this->setPreferredOzonImageHost($candidate);
                return $path;
            }
            $candidate_error = (string) ifset($result['error'], 'unknown transport error');
            $candidate_host = strtolower((string) parse_url($candidate, PHP_URL_HOST));
            $candidate_errors[] = ($candidate_host !== '' ? $candidate_host : 'unknown host').': '.$candidate_error;
            waLog::log(
                '[OzonImporter] Image candidate failed ('.$candidate.'): '.$candidate_error,
                shopMigratePluginOzonLogger::LOG_FILE
            );
            if ($path) {
                waFiles::delete($path);
            }
        }

        waLog::log(
            '[OzonImporter] Image download failed ('.$url.'): '.implode('; ', $candidate_errors),
            shopMigratePluginOzonLogger::LOG_FILE
        );
        return null;
    }

    private function buildImageDownloadCandidates($url)
    {
        $is_origin = $this->isOzonImageHost($url);
        $is_cdn = $this->isOzonImageCdnHost($url);
        if (!$is_origin && !$is_cdn) {
            return array($url);
        }

        $origin_url = $is_origin ? $url : $this->replaceUrlHost($url, self::OZON_IMAGE_HOST);
        $proxy_url = $this->isImageHost($url, self::OZON_IMAGE_CDN_HOST)
            ? $url
            : $this->replaceUrlHost($url, self::OZON_IMAGE_CDN_HOST);
        $official_cdn_url = $this->isImageHost($url, self::OZON_IMAGE_OFFICIAL_CDN_HOST)
            ? $url
            : $this->replaceUrlHost($url, self::OZON_IMAGE_OFFICIAL_CDN_HOST);

        $candidates = $this->prefer_ozon_image_cdn
            ? array($proxy_url, $official_cdn_url, $origin_url)
            : array($origin_url, $proxy_url, $official_cdn_url);
        $candidates = array_values(array_unique(array_filter($candidates)));

        foreach ($candidates as $index => $candidate) {
            if ($this->isImageHost($candidate, $this->preferred_ozon_image_host)) {
                unset($candidates[$index]);
                array_unshift($candidates, $candidate);
                break;
            }
        }
        return array_values($candidates);
    }

    private function setPreferredOzonImageHost($url)
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (!$this->isSupportedOzonImageHost($host)) {
            return;
        }

        $this->preferred_ozon_image_host = $host;
        if ($host === self::OZON_IMAGE_CDN_HOST) {
            $this->setPreferOzonImageCdn(true);
        } elseif ($host === self::OZON_IMAGE_HOST) {
            $this->setPreferOzonImageCdn(false);
        }
    }

    private function restorePreferredOzonImageHost($host)
    {
        $host = strtolower(trim((string) $host));
        if ($this->isSupportedOzonImageHost($host)) {
            $this->preferred_ozon_image_host = $host;
        }
    }

    private function isSupportedOzonImageHost($host)
    {
        return in_array((string) $host, array(
            self::OZON_IMAGE_HOST,
            self::OZON_IMAGE_CDN_HOST,
            self::OZON_IMAGE_OFFICIAL_CDN_HOST,
        ), true);
    }

    private function setPreferOzonImageCdn($prefer)
    {
        $prefer = (bool) $prefer;
        if ($this->prefer_ozon_image_cdn === $prefer) {
            return;
        }
        $this->prefer_ozon_image_cdn = $prefer;
        $this->settings->setPreferImageCdn($prefer);
    }

    private function isOzonImageHost($url)
    {
        return $this->isImageHost($url, self::OZON_IMAGE_HOST);
    }

    private function isOzonImageCdnHost($url)
    {
        return $this->isImageHost($url, self::OZON_IMAGE_CDN_HOST)
            || $this->isImageHost($url, self::OZON_IMAGE_OFFICIAL_CDN_HOST);
    }

    private function isImageHost($url, $host)
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST)) === (string) $host;
    }

    private function replaceUrlHost($url, $host)
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }
        $result = strtolower($parts['scheme']).'://'.(string) $host;
        if (!empty($parts['port'])) {
            $result .= ':'.(int) $parts['port'];
        }
        $result .= isset($parts['path']) ? $parts['path'] : '/';
        if (isset($parts['query']) && $parts['query'] !== '') {
            $result .= '?'.$parts['query'];
        }
        return $result;
    }

    private function createImageTempPath($url)
    {
        if (!$this->ensureImageTempDirectory()) {
            return null;
        }
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp'), true)) {
            $extension = 'jpg';
        }
        return $this->temp_image_dir.uniqid('ozon_img_', true).'.'.$extension;
    }

    private function ensureImageTempDirectory()
    {
        $directory = rtrim((string) $this->temp_image_dir, '/\\');
        clearstatcache(true, $directory);
        if ($directory !== '' && is_dir($directory) && is_writable($directory)) {
            return true;
        }

        try {
            $directory = rtrim(wa()->getTempPath('plugins/migrate/ozon/', 'shop'), '/\\');
            if ($directory !== '' && !is_dir($directory)) {
                waFiles::create($directory);
            }
        } catch (Throwable $e) {
            waLog::log(
                '[OzonImporter] Cannot recreate the image temporary directory: '.$e->getMessage(),
                shopMigratePluginOzonLogger::LOG_FILE
            );
            return false;
        }
        $this->temp_image_dir = $directory.DIRECTORY_SEPARATOR;
        clearstatcache(true, $directory);
        if (is_dir($directory) && is_writable($directory)) {
            return true;
        }

        waLog::log(
            '[OzonImporter] Image temporary directory is unavailable: '.$this->describeImageTempDirectory($directory),
            shopMigratePluginOzonLogger::LOG_FILE
        );
        return false;
    }

    private function openImageTempTarget($path)
    {
        $directory = dirname($path);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                clearstatcache(true, $directory);
                if (!is_dir($directory)) {
                    waFiles::create($directory);
                }
            } catch (Throwable $e) {
                waLog::log(
                    '[OzonImporter] Cannot recreate the image temporary directory: '.$e->getMessage(),
                    shopMigratePluginOzonLogger::LOG_FILE
                );
                return false;
            }
            $target = @fopen($path, 'wb');
            if (is_resource($target)) {
                return $target;
            }
        }

        waLog::log(
            '[OzonImporter] Cannot create an image temporary file: '.$this->describeImageTempDirectory($directory),
            shopMigratePluginOzonLogger::LOG_FILE
        );
        return false;
    }

    private function describeImageTempDirectory($directory)
    {
        clearstatcache(true, $directory);
        $free_space = @disk_free_space($directory);
        return sprintf(
            '%s (exists=%s, writable=%s, free=%s)',
            $directory,
            is_dir($directory) ? 'yes' : 'no',
            is_writable($directory) ? 'yes' : 'no',
            $free_space === false ? 'unknown' : $this->formatBytes($free_space)
        );
    }

    private function downloadImageToFile($url, $path)
    {
        if (function_exists('curl_init')) {
            return $this->downloadImageWithCurl($url, $path);
        }
        return $this->downloadImageWithStreams($url, $path);
    }

    private function downloadImageWithCurl($url, $path)
    {
        $target = $this->openImageTempTarget($path);
        if (!$target) {
            return array('success' => false, 'error' => 'cannot create a temporary file');
        }

        $downloaded = 0;
        $too_large = false;
        $curl = curl_init($url);
        $options = array(
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => self::IMAGE_CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::IMAGE_DOWNLOAD_TIMEOUT_SECONDS,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_FAILONERROR => false,
            CURLOPT_USERAGENT => 'Shop-Script Ozon migrate',
            CURLOPT_WRITEFUNCTION => function ($handle, $chunk) use ($target, &$downloaded, &$too_large) {
                $length = strlen($chunk);
                $downloaded += $length;
                if ($downloaded > self::IMAGE_DOWNLOAD_MAX_BYTES) {
                    $too_large = true;
                    return 0;
                }
                $written = fwrite($target, $chunk);
                return $written === false ? 0 : $written;
            },
        );
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        curl_setopt_array($curl, $options);

        $ok = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        fclose($target);

        if ($too_large) {
            return array('success' => false, 'error' => 'image exceeds '.self::IMAGE_DOWNLOAD_MAX_BYTES.' bytes');
        }
        if (!$ok || $status < 200 || $status >= 300 || !is_file($path) || filesize($path) <= 0) {
            if ($error === '') {
                $error = $status ? 'HTTP '.$status : 'empty response';
            }
            return array('success' => false, 'error' => $error);
        }
        return array('success' => true);
    }

    private function downloadImageWithStreams($url, $path)
    {
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => self::IMAGE_DOWNLOAD_TIMEOUT_SECONDS,
                'follow_location' => 1,
                'max_redirects' => 3,
                'user_agent' => 'Shop-Script Ozon migrate',
                'ignore_errors' => false,
            ),
        ));
        $target = $this->openImageTempTarget($path);
        if (!$target) {
            return array('success' => false, 'error' => 'cannot create a temporary file');
        }
        $source = @fopen($url, 'rb', false, $context);
        if (!$source) {
            fclose($target);
            return array('success' => false, 'error' => 'stream connection failed');
        }

        $downloaded = 0;
        $error = '';
        while (!feof($source)) {
            $chunk = fread($source, 8192);
            if ($chunk === false) {
                $error = 'stream read failed';
                break;
            }
            $downloaded += strlen($chunk);
            if ($downloaded > self::IMAGE_DOWNLOAD_MAX_BYTES) {
                $error = 'image exceeds '.self::IMAGE_DOWNLOAD_MAX_BYTES.' bytes';
                break;
            }
            if ($chunk !== '' && fwrite($target, $chunk) === false) {
                $error = 'temporary file write failed';
                break;
            }
        }
        fclose($source);
        fclose($target);

        if ($error !== '' || $downloaded <= 0) {
            return array('success' => false, 'error' => $error !== '' ? $error : 'empty response');
        }
        return array('success' => true);
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

        $attribute_ids = array();
        foreach ($result as $attributes_group) {
            foreach (array_keys($attributes_group) as $attribute_id) {
                $attribute_ids[(int) $attribute_id] = (int) $attribute_id;
            }
        }
        $attributes = $this->repository->getAttributesModel()->getByAttributeIds(
            $snapshot_id,
            array_values($attribute_ids)
        );
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

    private function groupStocksByOffer($snapshot_id, array $product_ids = array())
    {
        $rows = $product_ids
            ? $this->repository->getStocksModel()->getForProducts($snapshot_id, $product_ids)
            : $this->repository->getStocksModel()->getAllBySnapshot($snapshot_id);
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
        // Never downgrade a feature while processing another value of the same payload.
        if ($is_multiple && empty($feature['multiple'])) {
            $updates['multiple'] = 1;
            $feature['multiple'] = 1;
        } elseif (!isset($feature['multiple'])) {
            $feature['multiple'] = 0;
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
