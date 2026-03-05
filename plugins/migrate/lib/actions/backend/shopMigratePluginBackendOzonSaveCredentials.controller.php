<?php

class shopMigratePluginBackendOzonSaveCredentialsController extends waJsonController
{
    public function execute()
    {
        $client_id = trim(waRequest::post('client_id', '', waRequest::TYPE_STRING_TRIM));
        $api_key = trim(waRequest::post('api_key', '', waRequest::TYPE_STRING_TRIM));
        $log_mode = waRequest::post('log_mode', shopMigratePluginOzonSettings::LOG_ERRORS, waRequest::TYPE_STRING_TRIM);

        if ($client_id === '' || $api_key === '') {
            $this->setError(_wp('Fill in Client ID and API Key.'));
            return;
        }

        $settings = new shopMigratePluginOzonSettings();
        $settings->saveCredentials($client_id, $api_key);
        $settings->setLogMode($log_mode);

        $this->response = array('saved' => true);
    }
}
