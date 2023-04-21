<?php

class shopShippingCli extends waCliController
{
    /** @var shopShipping */
    private $adapter;

    public function preExecute()
    {
        parent::preExecute();
        $this->adapter = shopShipping::getInstance();
    }

    public function execute()
    {
        $plugin_model = new shopPluginModel();
        $options = array(
            'all' => true,
            'except_dummy' => true,
        );
        $methods = $plugin_model->listPlugins(shopPluginModel::TYPE_SHIPPING, $options);

        if ($methods) {
            /**
             * @event shipping_sync_cli
             * @param array $params['methods']
             * @return void
             */
            $params = [
                'methods' => $methods
            ];
            wa('shop')->event('shipping_sync_cli', $params);
        }

        $adapter = shopShipping::getInstance();
        foreach ($methods as $shipping_id => $method) {
            try {
                $plugin = waShipping::factory($method['plugin'], $shipping_id, $adapter);

                $this->runSync($plugin, $method);

            } catch (waException $ex) {
                $message = $ex->getMessage();
                $data = compact('message', 'shipping_id');
                waLog::log(var_export($data, true), 'shop/shipping.cli.log');
            }
        }

        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->set('shop', 'shipping_plugins_sync', time());
    }

    /**
     * @param waShipping $plugin
     * @param string[]   $method
     */
    protected function runSync($plugin, $method)
    {
        if (!empty($method['status'])) {
            try {
                $plugin->runSync();
            } catch (waException $ex) {
                $data = [
                    'message'  => $ex->getMessage(),
                    'shipping' => $method
                ];
                waLog::log(var_export($data, true), 'shop/shipping.cli.log');
            }
        }
    }
}
