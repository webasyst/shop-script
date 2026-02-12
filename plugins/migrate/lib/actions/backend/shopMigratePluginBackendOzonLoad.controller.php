<?php

class shopMigratePluginBackendOzonLoadController extends waJsonController
{
    public function execute()
    {
        try {
            $settings = new shopMigrateOzonSettings();
            $credentials = $settings->getCredentials();
            if ($credentials['client_id'] === '' || $credentials['api_key'] === '') {
                throw new waException(_wp('Save Client ID and API Key first.'));
            }

            $logger = new shopMigrateOzonLogger($settings->getLogMode());
            $api = new shopMigrateOzonApiClient($credentials['client_id'], $credentials['api_key'], $logger);
            $repository = new shopMigrateOzonSnapshotRepository();
            $builder = new shopMigrateOzonSnapshotBuilder($api, $repository, $settings);
            $snapshot_id = $builder->build();

            $this->response = array(
                'snapshot_id' => $snapshot_id,
            );
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }
}
