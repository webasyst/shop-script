<?php

class shopMigratePluginBackendOzonTestCredentialsController extends waJsonController
{
    public function execute()
    {
        try {
            $client_id = trim(waRequest::post('client_id', '', waRequest::TYPE_STRING_TRIM));
            $api_key = trim(waRequest::post('api_key', '', waRequest::TYPE_STRING_TRIM));
            if ($client_id === '' || $api_key === '') {
                throw new waException(_wp('Fill in Client ID and API Key first.'));
            }

            $settings = new shopMigratePluginOzonSettings();
            $logger = new shopMigratePluginOzonLogger($settings->getLogMode());
            $api = new shopMigratePluginOzonApiClient($client_id, $api_key, $logger);
            $response = $api->listWarehouses();
            $warehouses = ifset($response['result'], array());

            $this->response = array(
                'ok' => true,
                'warehouses' => count($warehouses),
                'message' => _wp('Connection successful'),
            );
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }
}
