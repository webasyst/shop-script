<?php

class shopMigratePluginWbTransport extends shopMigrateTransport
{
    public function getControls($errors = array())
    {
        if (!wa()->getUser()->getRights('shop', 'importexport')) {
            throw new waRightsException(_wp('Access denied.'));
        }
        $view = wa()->getView();
        $wb_debug = wa()->getConfig()->isDebug();
        $view->assign('wb_debug', $wb_debug);
        $view->assign('wb_tables', $wb_debug ? shopMigratePluginWbHelper::getTablesMeta() : array());
        $view->assign('wb_can_create_category', (bool) wa()->getUser()->getRights('shop', 'setscategories'));
        $view->assign('wb_can_create_stock', (bool) wa()->getUser()->getRights('shop', 'settings'));
        $settings = new shopMigratePluginWbSettings();
        $repository = new shopMigratePluginWbSnapshotRepository();
        $options = $settings->getImportOptions();
        $view->assign('wb_settings', array_merge($options, array(
            'has_token' => $settings->hasToken(),
            'api_token' => $settings->getToken(),
            'collection_revision' => $settings->getCollectionRevision(),
        )));

        $snapshot = $this->getSnapshot($settings, $repository);
        $snapshot_data = $snapshot ? $this->formatSnapshot($snapshot, $repository) : null;
        $image_import_product_count = $snapshot && $snapshot['status'] === 'ready'
            ? $repository->getProductMapModel()->countCompletedForSnapshot((int) $snapshot['id'])
            : 0;
        $shop_lists = $this->getShopLists();
        $view->assign('wb_snapshot', $snapshot_data);
        $view->assign('wb_image_import_product_count', $image_import_product_count);
        $view->assign(
            'wb_mapping',
            $snapshot && $snapshot['status'] === 'ready'
                ? $this->buildMapping((int) $snapshot['id'], $repository, $shop_lists)
                : array('types' => array(), 'categories' => array(), 'warehouses' => array())
        );
        $view->assign('wb_lists', $shop_lists);
        $view->assign('wb_active_run', $this->getActiveRun());

        return $view->fetch(
            wa()->getAppPath('plugins/migrate/templates/actions/backend/WbTransport.html', 'shop')
        );
    }

    public function validate($result, &$errors)
    {
        return true;
    }

    public function count()
    {
        return array();
    }

    public function step(&$current, &$count, &$processed, $stage, &$error)
    {
        return false;
    }

    private function getSnapshot(shopMigratePluginWbSettings $settings, shopMigratePluginWbSnapshotRepository $repository)
    {
        $snapshot_id = $settings->getCurrentSnapshotId();
        if (!$snapshot_id) {
            $snapshot_id = $settings->getBuildingSnapshotId();
        }
        if (!$snapshot_id) {
            return null;
        }
        return $repository->getSnapshotsModel()->getByIdSafe($snapshot_id);
    }

    private function formatSnapshot(array $snapshot, shopMigratePluginWbSnapshotRepository $repository)
    {
        $meta = array();
        if (!empty($snapshot['meta'])) {
            $meta = json_decode($snapshot['meta'], true);
            $meta = is_array($meta) ? $meta : array();
        }
        $counts = (array) ifset($meta['counts'], array());
        if (!isset($counts['product_groups'])) {
            $counts['product_groups'] = $repository->getCardsModel()->countGroupsBySnapshot((int) $snapshot['id']);
        }
        if (!isset($counts['import_categories'])) {
            $used_subjects = (int) $repository->getSubjectsModel()
                ->select('COUNT(*)')
                ->where('snapshot_id = ? AND products_count > 0', (int) $snapshot['id'])
                ->fetchField();
            $used_parents = (int) $repository->getSubjectsModel()
                ->select('COUNT(DISTINCT parent_id)')
                ->where('snapshot_id = ? AND products_count > 0', (int) $snapshot['id'])
                ->fetchField();
            $counts['used_subjects'] = $used_subjects;
            $counts['used_parents'] = $used_parents;
            $counts['import_categories'] = $used_subjects + $used_parents;
        }
        $counts['warehouses'] = $repository->getWarehousesModel()->countBySource((int) $snapshot['id'], 'seller');
        $build = (array) ifset($meta['build'], array());
        if ((string) ifset($build['fbw_stocks_status'], '') === 'complete') {
            $counts['warehouses'] += $repository->getWarehousesModel()->countBySource((int) $snapshot['id'], 'wb');
        }
        $warnings = (array) ifset($meta['warnings'], array());
        if ((int) ifset($build['version'], 1) < 2) {
            $supported_warnings = array(
                _wp('The Wildberries API token has no access to seller warehouses; seller stocks were skipped.'),
                _wp('The Wildberries API token has no access to seller stocks; seller stocks were skipped.'),
            );
            $warnings = array_intersect($warnings, $supported_warnings);
        }
        // Previously collected warnings may predate their translations.
        $warnings = array_map(static function ($warning) {
            return _wp($warning);
        }, array_values($warnings));
        return array(
            'id'         => (int) $snapshot['id'],
            'status'     => (string) $snapshot['status'],
            'phase'      => (string) $snapshot['phase'],
            'ready'      => (string) $snapshot['status'] === 'ready',
            'counts'     => $counts,
            'warnings'   => $warnings,
            'build'      => $build,
            'created_at' => (string) $snapshot['created_at'],
            'updated_at' => (string) $snapshot['updated_at'],
        );
    }

    private function buildMapping($snapshot_id, shopMigratePluginWbSnapshotRepository $repository, array $shop_lists)
    {
        $subjects = $repository->getSubjectsModel()->getAllBySnapshot($snapshot_id);
        $type_map = $repository->getTypeMapModel()->getMap($snapshot_id);
        $category_map = $repository->getCategoryMapModel()->getMap($snapshot_id);
        $stock_map = $repository->getStockMapModel()->getMap($snapshot_id);

        $type_rows = array();
        $category_rows = array();
        $parent_rows = array();
        foreach ($subjects as $subject) {
            if ((int) ifset($subject['products_count'], 0) <= 0) {
                continue;
            }
            $subject_id = (int) $subject['subject_id'];
            $parent_id = (int) $subject['parent_id'];
            $path = trim((string) $subject['parent_name'].' / '.(string) $subject['name'], ' /');
            $mapping = ifset($type_map[$subject_id], array());
            if ((string) ifset($mapping['action'], 'auto') === 'create') {
                $mapping = array();
            }
            $mapped_type_id = (int) ifset($mapping['shop_type_id'], 0);
            $type_rows[] = array(
                'parent_id'      => $parent_id,
                'subject_id'     => $subject_id,
                'path'           => $path,
                'count'          => (int) $subject['products_count'],
                'mapping'        => array(
                    'action'       => (string) ifset($mapping['action'], 'auto'),
                    'shop_type_id' => $mapped_type_id ?: null,
                    'shop_type_name' => $mapped_type_id && isset($shop_lists['types'][$mapped_type_id])
                        ? (string) $shop_lists['types'][$mapped_type_id]['name']
                        : '',
                ),
            );

            if (!isset($parent_rows[$parent_id])) {
                $parent_mapping = ifset($category_map['parent:'.$parent_id], array());
                $mapped_category_id = (int) ifset($parent_mapping['shop_category_id'], 0);
                $parent_rows[$parent_id] = array(
                    'entity_type' => 'parent',
                    'wb_id'       => $parent_id,
                    'parent_id'   => 0,
                    'name'        => (string) $subject['parent_name'],
                    'path'        => (string) $subject['parent_name'],
                    'level'       => 0,
                    'mapping'     => array(
                        'action'           => (string) ifset($parent_mapping['action'], 'auto'),
                        'shop_category_id' => $mapped_category_id ?: null,
                        'shop_category_name' => (string) ifset($shop_lists['categories'][$mapped_category_id], ''),
                    ),
                );
            }
            $subject_mapping = ifset($category_map['subject:'.$subject_id], array());
            $mapped_category_id = (int) ifset($subject_mapping['shop_category_id'], 0);
            $category_rows[] = array(
                'entity_type' => 'subject',
                'wb_id'       => $subject_id,
                'parent_id'   => $parent_id,
                'name'        => (string) $subject['name'],
                'parent_name' => (string) $subject['parent_name'],
                'path'        => $path,
                'level'       => 1,
                'mapping'     => array(
                    'action'           => (string) ifset($subject_mapping['action'], 'auto'),
                    'shop_category_id' => $mapped_category_id ?: null,
                    'shop_category_name' => (string) ifset($shop_lists['categories'][$mapped_category_id], ''),
                ),
            );
        }
        $category_rows = array_merge(array_values($parent_rows), $category_rows);

        $warehouse_rows = array();
        $warehouses = $repository->getWarehousesModel()->getBySource($snapshot_id, 'seller');
        $snapshot = $repository->getSnapshotsModel()->getByIdSafe($snapshot_id);
        $meta = $snapshot ? $repository->getSnapshotsModel()->decodeMeta($snapshot) : array();
        if ((string) ifset($meta['build']['fbw_stocks_status'], '') === 'complete') {
            $warehouses = array_merge($warehouses, $repository->getWarehousesModel()->getBySource($snapshot_id, 'wb'));
        }
        foreach ($warehouses as $warehouse) {
            $key = (string) $warehouse['warehouse_key'];
            $mapping = ifset($stock_map[$key], array());
            if ((string) ifset($mapping['action'], 'auto') === 'create') {
                $mapping = array();
            }
            $mapped_stock_id = (int) ifset($mapping['shop_stock_id'], 0);
            $mapped_stock = (array) ifset($shop_lists['stocks'][$mapped_stock_id], array());
            $warehouse_rows[] = array(
                'warehouse_key' => $key,
                'name'          => (string) $warehouse['name'],
                'source'        => (string) $warehouse['source'],
                'mapping'       => array(
                    'action'        => (string) ifset($mapping['action'], 'auto'),
                    'shop_stock_id' => $mapped_stock_id ?: null,
                    'shop_stock_name' => (string) ifset($mapped_stock['name'], ''),
                ),
            );
        }

        return array(
            'types'      => $type_rows,
            'categories' => $category_rows,
            'warehouses' => $warehouse_rows,
        );
    }

    private function getShopLists()
    {
        $types = (new shopTypeModel())->select('id, name')->order('name')->fetchAll('id');
        $category_model = new shopCategoryModel();
        $categories = array();
        foreach ($category_model->select('id, name, depth')
            ->where('type = ?', shopCategoryModel::TYPE_STATIC)
            ->order('left_key')
            ->fetchAll() as $row
        ) {
            $categories[(int) $row['id']] = str_repeat('—', (int) $row['depth']).' '.$row['name'];
        }
        $stocks = (new shopStockModel())->select('id, name')->order('name')->fetchAll('id');
        return array(
            'types'      => $types,
            'categories' => $categories,
            'stocks'     => $stocks,
        );
    }

    private function getActiveRun()
    {
        $model = new shopMigratePluginWbRunsModel();
        $run = $model->select('id, snapshot_id, kind, status, `cursor`, current_group_id, image_offset, cancel_requested, counters')
            ->where("status IN ('running','cancel_requested')")
            ->order('id DESC')
            ->limit(1)
            ->fetchAssoc();
        if (!$run) {
            return null;
        }
        $run['id'] = (int) $run['id'];
        $run['counters'] = json_decode((string) $run['counters'], true);
        $run['counters'] = is_array($run['counters']) ? $run['counters'] : array();
        return $run;
    }
}
