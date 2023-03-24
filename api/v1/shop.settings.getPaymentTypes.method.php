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
        $payment_methods = shopHelper::getPaymentMethods([], false, false);
        usort($payment_methods, function($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });
        return array_map(function($m) {
            $data = array_intersect_key($m, [
                'id' => 1,
                'plugin' => 1,
                'name' => 1,
                'description' => 1,
                'logo' => 1,
            ]);
            if (isset($data['logo'])) {
                $data['logo'] = wa()->getConfig()->getRootUrl(true).ltrim($data['logo'], '/');
            } else {
                $data['logo'] = null;
            }
            $data['plugin_type'] = ifset($m, 'info', 'type', null);
            return $data;
        }, $payment_methods);
    }
}
