<?php

class shopMigratePluginBackendWbLoadController extends waJsonController
{
    public function execute()
    {
        $settings = null;
        $collection_revision = null;
        try {
            if (!$this->getUser()->getRights('shop', 'importexport')) {
                throw new waRightsException(_wp('Access denied.'));
            }
            if (waRequest::method() !== 'post') {
                throw new waRightsException(_wp('Access denied.'));
            }
            $settings = new shopMigratePluginWbSettings();
            if (!$settings->hasToken()) {
                throw new waException(_wp('Save a Wildberries API token first.'));
            }
            if (waRequest::issetPost('log_mode')) {
                $settings->setLogMode(waRequest::post(
                    'log_mode',
                    shopMigratePluginWbSettings::LOG_ERRORS,
                    waRequest::TYPE_STRING_TRIM
                ));
            }
            $snapshot_id = waRequest::post('snapshot_id', 0, waRequest::TYPE_INT);
            $collection_revision = waRequest::post('collection_revision', -1, waRequest::TYPE_INT);
            if ($collection_revision < 0 || $collection_revision !== $settings->getFreshCollectionRevision()) {
                $this->respondCollectionStopped($settings);
                return;
            }
            $building_snapshot_id = $settings->getBuildingSnapshotId();
            if ($snapshot_id > 0
                && ($building_snapshot_id <= 0 || $snapshot_id !== $building_snapshot_id)
            ) {
                throw new waException(_wp('The Wildberries snapshot has changed. Reload the page and try again.'));
            }

            $logger = new shopMigratePluginWbLogger($settings->getLogMode());
            $api = new shopMigratePluginWbApiClient($settings->getToken(), array(
                // One HTTP attempt per AJAX request. Snapshot-level retries are
                // persisted by the builder and resumed by the browser.
                'timeout'            => 18,
                'max_retry_attempts' => 1,
                'logger'             => $logger,
            ));
            $repository = new shopMigratePluginWbSnapshotRepository();
            $builder = new shopMigratePluginWbSnapshotBuilder($api, $repository, $settings);
            $this->response = $builder->advance($snapshot_id, array(
                'collection_revision' => $collection_revision,
            ));
        } catch (Throwable $e) {
            // Save in another tab can invalidate the revision after our first
            // check, before the builder acquires its lock. Return the same
            // resumable UI state without reviving the cancelled request.
            if ($settings && $collection_revision !== null
                && $collection_revision !== $settings->getFreshCollectionRevision()
            ) {
                $this->respondCollectionStopped($settings);
                return;
            }
            waLog::log(
                sprintf(
                    '[WbSnapshotBuilder] AJAX batch failed: %s: %s at %s:%d',
                    get_class($e),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ),
                'shop/plugins/migrate/migrate_wb.log'
            );
            $this->setError($e->getMessage());
        }
    }

    private function respondCollectionStopped(shopMigratePluginWbSettings $settings)
    {
        $this->response = array(
            'status' => 'cancelled',
            'collection_stopped' => true,
            'collection_revision' => $settings->getFreshCollectionRevision(),
            'message' => _wp('Wildberries data collection was stopped. Start it again.'),
        );
    }
}
