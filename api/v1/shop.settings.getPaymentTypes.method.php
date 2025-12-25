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
        $channel_id = waRequest::request('channel_id', null);

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
        if ($channel_id) {
            if (strpos($channel_id, ':') !== false) {
                list($ch_name, $channel_id) = explode(':', $channel_id, 2);
            }
            $id = (int) $channel_id;
            if ($ch_name === 'pos' && $id > 0) {
                $channel_params = (new shopSalesChannelParamsModel())->get($id);
                 if (ifset($channel_params, 'payment_id', null) === 'id') {
                     $payment_ids = [];
                     foreach ($channel_params as $_name_param => $_param) {
                         if (strpos($_name_param, 'payment_id_') !== false) {
                             $payment_ids[] = $_param;
                         }
                     }
                     $payment_methods = array_filter($payment_methods, function ($m) use ($payment_ids) {
                         return in_array($m['id'], $payment_ids);
                     });
                 }
            }
            if (empty($channel_params)) {
                throw new waAPIException('not_found', _w('Channel not found.'), 404);
            }
        }

        return array_values($payment_methods);
    }
}
