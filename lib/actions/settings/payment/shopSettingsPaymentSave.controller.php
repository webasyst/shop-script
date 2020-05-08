<?php
class shopSettingsPaymentSaveController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        if ($plugin = waRequest::post('payment')) {
            try {
                if (!isset($plugin['settings'])) {
                    $plugin['settings'] = array();
                }
                
                $this->trimSettingsValue($plugin['settings']);
                shopPayment::savePlugin($plugin);

                $is_edit = $plugin['id'] > 0;

                $log_params = array(
                    'id' => $plugin['id'],
                    'status' => $plugin['status'],
                    'plugin' => $plugin['plugin']
                );

                if ($is_edit) {
                    $this->logAction('payment_plugin_edit', $log_params);
                } else {
                    $this->logAction('payment_plugin_add', $log_params);
                }

                $this->response['message'] = _w('Saved');
            } catch (waException $ex) {
                $this->setError($ex->getMessage());
            }
        }
    }
    
    public function trimSettingsValue(&$settings){
        foreach ($settings as &$value) {
            if(is_array($value)){
                $this->trimSettingsValue($value);
            } else {
                $value = trim($value);
            }
        }
    }
}
