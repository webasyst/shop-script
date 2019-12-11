<?php
class shopSettingsPaymentDeleteController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        if ($plugin_id = waRequest::post('plugin_id')) {
            $model = new shopPluginModel();
            if ($plugin = $model->getByField(array('id' => $plugin_id, 'type' => 'payment'))) {
                $settings_model = new shopPluginSettingsModel();
                $settings_model->del($plugin['id'], null);
                $model->deleteById($plugin['id']);

                $log_params = array(
                    'id' => $plugin['id'],
                    'plugin' => $plugin['plugin']
                );

                $this->logAction('payment_plugin_delete', $log_params);

            } else {
                throw new waException("Payment plugin {$plugin_id} not found", 404);
            }

        }
    }
}
