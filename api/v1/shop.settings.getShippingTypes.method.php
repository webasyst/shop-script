<?php

class shopSettingsGetShippingTypesMethod extends shopApiMethod
{
    public function execute()
    {
        $this->response = [
            'methods' => $this->getMethods(),
        ];
    }

    public function getMethods()
    {
        $shipping_methods = shopHelper::getShippingMethods();
        usort($shipping_methods, function($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });
        $shipping_methods = array_map(function($m) {
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
            return $data;
        }, $shipping_methods);
        return $shipping_methods;
    }
}
