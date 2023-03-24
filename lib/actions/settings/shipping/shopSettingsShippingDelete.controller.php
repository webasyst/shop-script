<?php
class shopSettingsShippingDeleteController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        if ($plugin_id = waRequest::post('plugin_id')) {
            $model = new shopPluginModel();
            if ($plugin = $model->getByField(array('id' => $plugin_id, 'type' => 'shipping'))) {
                $model->deleteById($plugin['id']);

                $log_params = array(
                    'id' => $plugin['id'],
                    'plugin' => $plugin['plugin']
                );

                $this->logAction('shipping_plugin_delete', $log_params);

                shopSettingsShippingSaveController::disableShipping();

            } else {
                throw new waException("Shipping plugin {$plugin_id} not found", 404);
            }

        }
    }
}
