<?php

class shopSettingsGetPaymentTypesMethod extends shopApiMethod
{
    public function execute()
    {
        $this->response = [
            'methods' => $this->getMethods(),
        ];
    }

    public function getMethods()
    {
        $return_pos_only = waRequest::request('pos', null);

        $payment_methods = shopHelper::getPaymentMethods([], false, false);
        usort($payment_methods, function($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });
        $payment_methods = array_map(function($m) {
            $data = array_intersect_key($m, [
                'id' => 1,
                'plugin' => 1,
                'name' => 1,
                'description' => 1,
                'logo' => 1,
            ]);
            if (!empty($data['logo'])) {
                $data['logo'] = wa()->getConfig()->getRootUrl(true).ltrim($data['logo'], '/');
            } else {
                $data['logo'] = null;
            }
            $data['plugin_type'] = ifset($m, 'info', 'type', null);

            // Point of Sale support: either payment declares that manager initiates payment; or plugin supports payment by QR image.
            $data['pos_enabled'] = !empty($m['info']['pos_initiates_payment']);
            if (!$data['pos_enabled']) {
                try {
                    $plugin = waPayment::factory($m['plugin'], $m['id']);
                    $data['pos_enabled'] = $plugin instanceof waIPaymentImage;
                } catch (waException $e) {
                }
            }
            return $data;
        }, $payment_methods);

        if ($return_pos_only) {
            $payment_methods = array_filter($payment_methods, function($m) {
                return !empty($m['pos_enabled']);
            });
        }
        return $payment_methods;
    }
}
