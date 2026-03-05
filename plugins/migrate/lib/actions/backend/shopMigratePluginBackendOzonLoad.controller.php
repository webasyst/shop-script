<?php

class shopMigratePluginBackendOzonLoadController extends waJsonController
{
    public function execute()
    {
        try {
            $settings = new shopMigratePluginOzonSettings();
            $credentials = $settings->getCredentials();
            if ($credentials['client_id'] === '' || $credentials['api_key'] === '') {
                throw new waException(_wp('Save Client ID and API Key first.'));
            }

            $logger = new shopMigratePluginOzonLogger($settings->getLogMode());
            $api = new shopMigratePluginOzonApiClient($credentials['client_id'], $credentials['api_key'], $logger);
            $repository = new shopMigratePluginOzonSnapshotRepository();
            $builder = new shopMigratePluginOzonSnapshotBuilder($api, $repository, $settings);
            $snapshot_id = $builder->build();

            $this->response = array(
                'snapshot_id' => $snapshot_id,
            );
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }
}
