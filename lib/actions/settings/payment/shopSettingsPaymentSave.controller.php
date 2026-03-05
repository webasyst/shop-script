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
                foreach ($plugin['settings'] as $key => $value) {
                    if (is_scalar($value)) {
                        $value = trim($value);
                    }
                    $plugin['settings'][$key] = $value;
                }
                if ($plugin['plugin'] === 'pay') {
                    $services_api = new waServicesApi();
                    if (!$services_api->isConnected()) {
                        $result = (new waWebasystIDClientManager())->connect();
                        if ($result['status']) {
                            $services_api->unsetIsConnected();
                        } elseif (isset($result['details']['error_message'])) {
                            $this->setError($result['details']['error_message']);
                        }
                    }
                }

                shopPayment::savePlugin($plugin);

                $this->response['message'] = _w('Saved');
            } catch (waException $ex) {
                $this->setError($ex->getMessage());
            }

            $is_edit = intval($plugin['id']) > 0;
            $log_params = array(
                'id' => $plugin['id'],
                'status' => !empty($plugin['status']),
                'plugin' => $plugin['plugin']
            );

            if ($is_edit) {
                $this->logAction('payment_plugin_edit', $log_params);
            } else {
                $this->logAction('payment_plugin_add', $log_params);
                if ($plugin['plugin'] == 'pay') {
                    self::maybeDisablePaymentInCheckout();
                }
            }
        }
    }

    protected static function maybeDisablePaymentInCheckout()
    {
        $something_changed = false;
        $path = wa()->getConfig()->getConfigPath('checkout2.php', true, 'shop');
        if (file_exists($path)) {
            $full_config = include($path);
        } else {
            $full_config = [];
        }

        foreach (wa()->getRouting()->getByApp('shop') as $domain => $routes) {
            foreach ($routes as $r) {
                if (empty($r['checkout_storefront_id'])) {
                    // ignore storefronts with step-by-step checkout
                    continue;
                }
                // Disable 'payment' step in single-page checkout.
                // This allows customer to select payment option after order is created.
                $checkout_config = new shopCheckoutConfig($r['checkout_storefront_id']);
                if (!empty($checkout_config['payment']['used'])) {
                    $checkout_config->setData([
                        'payment' => ['used' => false],
                        'confirmation' => ['auto_submit' => true],
                    ]);
                    $full_config[$checkout_config->getStorefront()] = $checkout_config->getStorefrontConfigStorage();
                    $something_changed = true;
                }
            }
        }
        if ($something_changed) {
            waUtils::varExportToFile($full_config, $path);
        }
        return $something_changed;
    }
}
