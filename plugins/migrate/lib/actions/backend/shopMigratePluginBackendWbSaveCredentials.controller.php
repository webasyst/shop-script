<?php

class shopMigratePluginBackendWbSaveCredentialsController extends waJsonController
{
    public function execute()
    {
        $lock = null;
        $locked = false;
        try {
            if (!$this->getUser()->getRights('shop', 'importexport')) {
                throw new waRightsException(_wp('Access denied.'));
            }
            if (waRequest::method() !== 'post') {
                throw new waRightsException(_wp('Access denied.'));
            }
            $settings = new shopMigratePluginWbSettings();
            $token = waRequest::post('api_token', '', waRequest::TYPE_STRING_TRIM);

            $lock = new shopMigratePluginWbOperationLock();
            $locked = $lock->acquire('global');
            if (!$locked) {
                $this->response = array(
                    'saved'          => false,
                    'retry_after_ms' => 500,
                    'message'        => _wp('Stopping Wildberries data collection...'),
                );
                return;
            }

            $repository = new shopMigratePluginWbSnapshotRepository();
            $building_snapshot_id = $settings->getBuildingSnapshotId();
            $collection_stopped = false;
            if ($building_snapshot_id > 0) {
                $snapshot = $repository->getSnapshotsModel()->getByIdSafe($building_snapshot_id);
                if ($snapshot && (string) $snapshot['status'] === 'building') {
                    $meta = $repository->getSnapshotsModel()->decodeMeta($snapshot);
                    $build = (array) ifset($meta['build'], array());
                    $build['phase'] = 'failed';
                    $build['cancelled_at'] = date('Y-m-d H:i:s');
                    $meta['build'] = $build;
                    $repository->markFailed(
                        $building_snapshot_id,
                        _wp('Wildberries data collection was stopped after saving the API token.'),
                        $meta
                    );
                    $collection_stopped = true;
                }
                $settings->clearBuildingSnapshotReference();
            }

            // Every successful Save invalidates requests started by an older
            // browser-side collection loop, even when the token did not change.
            $collection_revision = $settings->incrementCollectionRevision();

            if ($token !== '') {
                $settings->saveToken($token);
            } elseif (!$settings->hasToken()) {
                throw new waException(_wp('Enter a Wildberries API token.'));
            }
            if (waRequest::issetPost('log_mode')) {
                $settings->setLogMode(waRequest::post(
                    'log_mode',
                    shopMigratePluginWbSettings::LOG_ERRORS,
                    waRequest::TYPE_STRING_TRIM
                ));
            }

            $save_message = $collection_stopped
                ? _wp('API token saved. Wildberries data collection stopped.')
                : _wp('API token saved.');
            $response = array(
                'saved' => true,
                'has_token' => true,
                'collection_stopped' => $collection_stopped,
                'collection_revision' => $collection_revision,
                'current_snapshot_id' => $settings->getCurrentSnapshotId(),
                'message' => $save_message,
            );

            // Permission probes are network calls and must not keep the global
            // migration lock while they are running.
            if ($locked && $lock) {
                $lock->release('global');
                $locked = false;
            }

            try {
                $effective_token = $token !== '' ? $token : $settings->getToken();
                $api = new shopMigratePluginWbApiClient($effective_token, array(
                    'timeout'            => 6,
                    'max_retry_attempts' => 1,
                    'logger'             => new shopMigratePluginWbLogger($settings->getLogMode()),
                ));
                $permissions = new shopMigratePluginWbTokenPermissions($effective_token, $settings);
                $access = $permissions->buildResponse($permissions->inspect($api, true));
                $response = array_merge($response, $access);
                $response['message'] = $save_message;
                $response['access_message'] = (string) ifset($access['message'], '');
            } catch (Throwable $e) {
                // The token is already persisted, so a temporary API failure
                // must not be reported as a failed Save operation.
                $response['permission_check_failed'] = true;
                $response['access_message'] = sprintf(
                    _wp('Wildberries API access could not be checked: %s'),
                    $e->getMessage()
                );
            }

            $this->response = $response;
        } catch (Throwable $e) {
            $this->setError($e->getMessage());
        } finally {
            if ($locked && $lock) {
                $lock->release('global');
            }
        }
    }
}
