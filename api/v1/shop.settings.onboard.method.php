<?php

class shopSettingsOnboardMethod extends shopApiMethod
{
    protected $method = 'POST';

    public function execute()
    {
        if (!wa('shop')->getSetting('welcome')) {
            $this->response = false;
            return;
        }

        $currency = $this->post('currency', true);
        $all_currencies = waCurrency::getAll(true);
        if (!isset($all_currencies[$currency])) {
            throw new waAPIException('invalid_param', sprintf_wp('Invalid value of parameter “%s”.', 'currency'), 400);
        }

        $country = $this->post('country', true);
        $country = mb_strtolower($country);
        $all_countries = $this->getAllCountries();
        if (!isset($all_countries[$country])) {
            throw new waAPIException('invalid_param', sprintf_wp('Invalid value of parameter “%s”.', 'country'), 400);
        }

        $setup_options = [
            'currency' => $currency,
            'country' => $country,
        ];

        $demo_db = $this->post('demo_db');
        if ($demo_db) {
            if (!wa_is_int($demo_db)) {
                throw new waAPIException('invalid_param', sprintf_wp('Invalid value of parameter “%s”.', 'demo_db'), 400);
            }
            $setup_options['demo_db'] = $demo_db;
        }

        shopBackendWelcomeAction::setupEverything($setup_options);

        $this->setGeneralSettings();

        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->del('shop', 'welcome');

        $this->response = true;
    }

    protected function setGeneralSettings()
    {
        $app_settings_model = new waAppSettingsModel();
        foreach([
            'shop_name' => 'name',
            'shop_phone' => 'phone',
            'shop_email' => 'email',
        ] as $post_name => $app_settings_name) {
            $value = $this->post($post_name);
            if ($value) {
                $app_settings_model->set('shop', $app_settings_name, $value);
            }
        }
    }

    protected function getAllCountries()
    {
        $all_countries = [];
        $path = wa('shop')->getConfig()->getConfigPath('data/welcome/', false);
        if (file_exists($path)) {
            $files = waFiles::listdir($path, false);
            foreach ($files as $file) {
                if (preg_match('/^country_([a-z]{3})\.php$/', $file, $matches)) {
                    $country = mb_strtolower($matches[1]);
                    $all_countries[$country] = $country;
                }
            }
        }
        return $all_countries;
    }
}
