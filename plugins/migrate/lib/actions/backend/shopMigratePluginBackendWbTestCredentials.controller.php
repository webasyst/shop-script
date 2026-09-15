<?php

class shopMigratePluginBackendWbTestCredentialsController extends waJsonController
{
    public function execute()
    {
        try {
            if (!$this->getUser()->getRights('shop', 'importexport')) {
                throw new waRightsException(_wp('Access denied.'));
            }
            if (waRequest::method() !== 'post') {
                throw new waRightsException(_wp('Access denied.'));
            }
            $settings = new shopMigratePluginWbSettings();
            $token = waRequest::post('api_token', '', waRequest::TYPE_STRING_TRIM);
            if ($token === '') {
                $token = $settings->getToken();
            }
            if ($token === '') {
                throw new waException(_wp('Enter a Wildberries API token first.'));
            }
            if (waRequest::issetPost('log_mode')) {
                $settings->setLogMode(waRequest::post(
                    'log_mode',
                    shopMigratePluginWbSettings::LOG_ERRORS,
                    waRequest::TYPE_STRING_TRIM
                ));
            }

            $api = new shopMigratePluginWbApiClient($token, array(
                'timeout'            => 6,
                'max_retry_attempts' => 1,
                'logger'             => new shopMigratePluginWbLogger($settings->getLogMode()),
            ));
            $permissions = new shopMigratePluginWbTokenPermissions($token, $settings);
            $this->response = $permissions->buildResponse($permissions->inspect($api, true));
        } catch (Throwable $e) {
            $this->setError($e->getMessage());
        }
    }
}
