<?php

class shopPaymentCli extends waCliController
{
    /** @var shopPayment */
    private $adapter;

    public function preExecute()
    {
        parent::preExecute();
        $this->adapter = shopPayment::getInstance();
    }

    public function execute()
    {
        $plugin_model = new shopPluginModel();
        $options = array(
            'all' => true,
        );
        $methods = $plugin_model->listPlugins(shopPluginModel::TYPE_PAYMENT, $options);

        if ($methods) {
            /**
             * @event payment_sync_cli
             * @param array $params['methods']
             * @return void
             */
            $params = [
                'methods' => $methods
            ];
            wa('shop')->event('payment_sync_cli', $params);
        }

        $adapter = shopPayment::getInstance();
        foreach ($methods as $payment_id => $method) {
            try {
                $plugin = waPayment::factory($method['plugin'], $payment_id, $adapter);

                $this->runSync($plugin, $method);

            } catch (waException $ex) {
                $message = $ex->getMessage();
                $data = compact('message', 'payment_id');
                waLog::log(var_export($data, true), 'shop/payment.cli.log');
            }
        }

        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->set('shop', 'payment_plugins_sync', time());
    }

    /**
     * @param waPayment $plugin
     * @param string[]   $method
     */
    protected function runSync($plugin, $method)
    {
        if (!empty($method['status']) && method_exists($plugin, 'runSync')) {
            try {
                $plugin->runSync();
            } catch (waException $ex) {
                $data = [
                    'message'  => $ex->getMessage(),
                    'payment' => $method
                ];
                waLog::log(var_export($data, true), 'shop/payment.cli.log');
            }
        }
    }
}
