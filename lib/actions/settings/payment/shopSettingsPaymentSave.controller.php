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
        if (!waRequest::request('move_checkout_payment')) {
            return false;
        }

        $something_changed = false;
        foreach (wa()->getRouting()->getByApp('shop') as $domain => $routes) {
            foreach ($routes as $r) {
                if (empty($r['checkout_storefront_id'])) {
                    // ignore storefronts with step-by-step checkout
                    continue;
                }
                // Disable 'payment' step in single-page checkout with WA Pay enabled.
                // This allows customer to select payment option after order is created.
                $checkout_config = new shopCheckoutConfig($r['checkout_storefront_id']);
                if (!empty($checkout_config['payment']['used'])) {
                    $checkout_config->setData([
                        'payment' => ['used' => false],
                        'confirmation' => ['auto_submit' => true],
                    ]);
                    $checkout_config->commit();
                    $something_changed = true;
                }
            }
        }
        return $something_changed;
    }
}
