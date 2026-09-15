<?php

class shopMigratePluginBackendWbCleanupController extends waJsonController
{
    public function execute()
    {
        try {
            $this->executeCleanup();
        } catch (Throwable $e) {
            $this->setError($e->getMessage());
        }
    }

    private function executeCleanup()
    {
        $this->assertAllowed();

        $definitions = shopMigratePluginWbHelper::getTablesMeta();
        $requested = waRequest::post('tables', array(), waRequest::TYPE_ARRAY);
        $selected = array();
        foreach ((array) $requested as $table) {
            if (!is_scalar($table)) {
                continue;
            }
            $table = trim((string) $table);
            if (isset($definitions[$table])) {
                $selected[$table] = true;
            }
        }

        // TRUNCATE restarts run IDs. Do not let a new run inherit an old report.
        if (isset($selected['shop_migrate_wb_runs'])) {
            $selected['shop_migrate_wb_unpriced_products'] = true;
        }

        // Iterate over the hard-coded allowlist, never over request values.
        $targets = array();
        foreach (array_keys($definitions) as $table) {
            if (isset($selected[$table])) {
                $targets[] = $table;
            }
        }

        if (!$targets) {
            $this->response = array(
                'cleared' => 0,
                'tables'  => array(),
            );
            return;
        }

        $model = new waModel();
        $lock = new shopMigratePluginWbOperationLock($model);
        if (!$lock->acquire('global')) {
            throw new waException(_wp('Another Wildberries operation is in progress. Try again.'));
        }
        try {
            $this->assertNoActiveWork();

            // TRUNCATE is not transactional. Invalidate snapshot references
            // before the first destructive query so a partial cleanup can
            // never leave the UI pointing at an incomplete snapshot.
            if ($this->invalidatesSnapshot($targets)) {
                $settings = new shopMigratePluginWbSettings();
                $settings->clearSnapshotReference();
                $settings->clearBuildingSnapshotReference();
            }

            foreach ($targets as $table) {
                // Table names only come from shopMigratePluginWbHelper's allowlist.
                $model->exec('TRUNCATE TABLE `'.$table.'`');
            }
        } finally {
            $lock->release('global');
        }

        $this->response = array(
            'cleared' => count($targets),
            'tables'  => $targets,
        );
    }

    private function assertAllowed()
    {
        if (!$this->getUser()->getRights('shop', 'importexport')) {
            throw new waRightsException(_wp('Access denied.'));
        }
        if (waRequest::method() !== 'post') {
            throw new waRightsException(_wp('Access denied.'));
        }
        if (!wa()->getConfig()->isDebug()) {
            throw new waRightsException(_wp('Access denied.'));
        }
    }

    private function assertNoActiveWork()
    {
        $active_run = (new shopMigratePluginWbRunsModel())->select('id')
            ->where("status IN ('running', 'cancel_requested')")
            ->limit(1)
            ->fetchAssoc();
        if ($active_run) {
            throw new waException(_wp('Finish or cancel the active Wildberries import before clearing tables.'));
        }

        $settings = new shopMigratePluginWbSettings();
        $building_snapshot_id = $settings->getBuildingSnapshotId();
        if ($building_snapshot_id > 0) {
            $building_snapshot = (new shopMigratePluginWbSnapshotsModel())->getByIdSafe($building_snapshot_id);
            if ($building_snapshot && (string) $building_snapshot['status'] === 'building') {
                throw new waException(_wp('Wait for the Wildberries snapshot to finish before clearing tables.'));
            }
        }
    }

    private function invalidatesSnapshot(array $targets)
    {
        $snapshot_tables = array(
            'shop_migrate_wb_snapshots',
            'shop_migrate_wb_parents',
            'shop_migrate_wb_subjects',
            'shop_migrate_wb_cards',
            'shop_migrate_wb_sizes',
            'shop_migrate_wb_attributes',
            'shop_migrate_wb_attribute_values',
            'shop_migrate_wb_prices',
            'shop_migrate_wb_warehouses',
            'shop_migrate_wb_stocks',
        );
        return (bool) array_intersect($snapshot_tables, $targets);
    }
}
