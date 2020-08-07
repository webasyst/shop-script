<?php

class shopOrderMode
{
    const MODE_ENABLED = 'enabled';
    const MODE_TOTAL_LIMITED = 'total_limited';
    const MODE_TOTAL_FIXED = 'total_fixed';
    const MODE_DISABLED = 'disabled';

    // When money are on hold, we may not freely modify the order.
    // Editor is limited by how much money are on hold.
    // Also, payment option may not change at this point.
    public static function getMode($order)
    {
        // Don't restrict anything unless order has money on hold
        if (!$order->auth_date || $order->paid_date) {
            return [
                'mode' => self::MODE_ENABLED,
                'message' => '',
            ];
        }

        // Figure out editor mode based on payment plugin capabilities
        $mode = self::MODE_ENABLED;
        if ($order->payment_plugin) {
            if (!$order->payment_plugin->getProperties('partial_capture')) {
                // Payment plugin only supports full capture. Order total must stay intact.
                // This is mostly legacy. All modern payment plugins should support partial capture.
                $mode = self::MODE_TOTAL_FIXED;
            } else {
                // Payment plugin supports partial capture. Order total may not exceed amount captured,
                // but can decrease.
                $mode = self::MODE_TOTAL_LIMITED;
            }
        }

        // Plugins may further restrict order editor mode
        $order_mode = wa('shop')->event('backend_order_partial_edit', ref([
            'order' => $order,
            'mode' => $mode,
        ]));

        $priority_plugin = 0;
        $all_modes = array(self::MODE_ENABLED, self::MODE_TOTAL_LIMITED, self::MODE_TOTAL_FIXED, self::MODE_DISABLED);
        foreach ($order_mode as $plugin_id => &$params) {
            if (!is_array($params) || !isset($params['mode']) || !in_array($params['mode'], $all_modes)) {
                unset($order_mode[$plugin_id]);
                continue;
            }

            $params = array_intersect_key($params, [
                'message' => '',
                'mode' => '',
            ]) + [
                'message' => '',
            ];

            if (empty($params['message']) && $params['mode'] == self::MODE_DISABLED) {
                $params['message'] = _w("Order editing has been disabled because the customer’s funds have been captured and an installed plugin does not support partial funds capturing.");
            }

            $active_mode = $mode;
            if (!empty($priority_plugin)) {
                $active_mode = $order_mode[$priority_plugin]['mode'];
            }

            if (array_search($params['mode'], $all_modes) > array_search($active_mode, $all_modes)) {
                $priority_plugin = $plugin_id;
            }
        }
        unset($params);

        if (isset($order_mode[$priority_plugin])) {
            return $order_mode[$priority_plugin];
        } else {
            return [
                'mode' => $mode,
                'message' => '',
            ];
        }
    }
}
