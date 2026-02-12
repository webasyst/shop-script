<?php

class shopMigrateOzonTransport extends shopMigrateTransport
{
    public function getControls($errors = array())
    {
        $view = wa()->getView();
        $settings = new shopMigrateOzonSettings();
        $repository = new shopMigrateOzonSnapshotRepository();

        $snapshot = null;
        $snapshot_id = $settings->getCurrentSnapshotId();
        $show_snapshot = (bool) waRequest::request('ozon_show_snapshot', 0, waRequest::TYPE_INT);
        if ($snapshot_id && $show_snapshot) {
            $snapshot = $repository->getSnapshotsModel()->getByIdSafe($snapshot_id);
            if ($snapshot && $snapshot['status'] !== 'ready') {
                $snapshot = null;
            }
        }

        $view->assign('ozon_settings', array(
            'client_id'      => $settings->getCredentials()['client_id'],
            'api_key'        => $settings->getCredentials()['api_key'],
            'log_mode'       => $settings->getLogMode(),
            'mode'           => $settings->getOperationMode(),
            'feature_mode'   => $settings->getFeatureImportMode(),
            'force_text_features' => $settings->shouldForceTextFeatures(),
        ));

        $formatted_snapshot = $snapshot ? $this->formatSnapshot($snapshot) : null;
        $meta = $formatted_snapshot ? ifset($formatted_snapshot['meta'], array()) : array();

        $view->assign('ozon_snapshot', $formatted_snapshot);
        $view->assign('ozon_mapping', $formatted_snapshot ? $this->buildMapping($snapshot['id'], $repository, $meta) : array());
        $view->assign('ozon_lists', $this->getShopLists());
        $view->assign('ozon_debug', wa()->getConfig()->isDebug());
        $view->assign('ozon_tables', shopMigrateOzonHelper::getTablesMeta());

        return $view->fetch(wa()->getAppPath('plugins/migrate/templates/actions/backend/OzonTransport.html', 'shop'));
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

    private function formatSnapshot(array $snapshot)
    {
        $meta = array();
        if (!empty($snapshot['meta'])) {
            $meta = json_decode($snapshot['meta'], true);
        }
        return array(
            'id'         => $snapshot['id'],
            'status'     => $snapshot['status'],
            'meta'       => $meta,
            'created_at' => $snapshot['created_at'],
            'updated_at' => $snapshot['updated_at'],
        );
    }

    private function buildMapping($snapshot_id, shopMigrateOzonSnapshotRepository $repository, array $snapshot_meta = array())
    {
        $categories_model = $repository->getCategoriesModel();
        $products_model = $repository->getProductsModel();
        $warehouses_model = $repository->getWarehousesModel();
        $type_map_model = $repository->getTypeMapModel();
        $category_map_model = $repository->getCategoryMapModel();
        $stock_map_model = $repository->getStockMapModel();

        $paths = $categories_model->getPathMap($snapshot_id);
        $type_paths_meta = is_array($snapshot_meta) ? ifset($snapshot_meta['type_paths'], array()) : array();
        $type_rows = array();
        $type_map = $type_map_model->getMap($snapshot_id);

        $type_pairs = $products_model->query(
            'SELECT description_category_id, type_id, COUNT(*) cnt
             FROM '.$products_model->getTableName().'
             WHERE snapshot_id = i:sid AND description_category_id > 0 AND type_id > 0
             GROUP BY description_category_id, type_id
             ORDER BY cnt DESC',
            array('sid' => $snapshot_id)
        )->fetchAll();

        $used_categories = array();
        foreach ($type_pairs as $pair) {
            $key = sprintf('%d:%d', $pair['description_category_id'], $pair['type_id']);
            $mapping = isset($type_map[$key]) ? $type_map[$key] : array();
            $used_categories[$pair['description_category_id']] = true;
            $type_rows[] = array(
                'description_category_id' => $pair['description_category_id'],
                'type_id'                 => $pair['type_id'],
                'path'                    => ifset($type_paths_meta[$key], ifset($paths[$pair['description_category_id']], '')),
                'count'                   => $pair['cnt'],
                'mapping'                 => array(
                    'shop_type_id' => ifset($mapping['shop_type_id']),
                    'mode'         => ifset($mapping['mode'], 'auto'),
                ),
            );
        }

        $category_rows = array();
        $category_map = $category_map_model->getMap($snapshot_id);
        $categories = $categories_model->getAllBySnapshot($snapshot_id);
        foreach ($categories as $category) {
            if (!isset($used_categories[$category['description_category_id']])) {
                continue;
            }
            $mapping = isset($category_map[$category['description_category_id']]) ? $category_map[$category['description_category_id']] : array();
            $category_rows[] = array(
                'description_category_id' => $category['description_category_id'],
                'path'                    => $category['path'],
                'level'                   => $category['level'],
                'mapping'                 => array(
                    'action'           => ifset($mapping['action'], 'auto'),
                    'shop_category_id' => ifset($mapping['shop_category_id']),
                ),
            );
        }

        $warehouse_rows = array();
        $stock_map = $stock_map_model->getMap($snapshot_id);
        $warehouses = $warehouses_model->getAllBySnapshot($snapshot_id);
        foreach ($warehouses as $warehouse_id => $warehouse) {
            $mapping = isset($stock_map[$warehouse_id]) ? $stock_map[$warehouse_id] : array();
            $warehouse_rows[] = array(
                'warehouse_id' => $warehouse_id,
                'name'         => $warehouse['name'],
                'type'         => $warehouse['type'],
                'mapping'      => array(
                    'action'        => ifset($mapping['action'], 'auto'),
                    'shop_stock_id' => ifset($mapping['shop_stock_id']),
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
        $type_model = new shopTypeModel();
        $category_model = new shopCategoryModel();
        $stock_model = new shopStockModel();

        $types = $type_model->select('*')->order('name ASC')->fetchAll('id');

        $categories = array();
        $rows = $category_model->select('id, name, depth')->order('left_key')->fetchAll();
        foreach ($rows as $row) {
            $categories[$row['id']] = str_repeat('—', (int) $row['depth']).' '.$row['name'];
        }

        $stocks = $stock_model->select('*')->order('name ASC')->fetchAll('id');

        return array(
            'types'      => $types,
            'categories' => $categories,
            'stocks'     => $stocks,
        );
    }
}
