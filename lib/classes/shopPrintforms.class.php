<?php

class shopPrintforms
{
    /**
     *
     * Returns array of ORDER print forms available for specified order.
     *
     * @param waOrder|array|null $order Order data; if not specified, print forms applicable to any orders are returned
     * @return array
     */
    public static function getOrderPrintforms($order = null)
    {
        $plugins = array();
        foreach (wa('shop')->getConfig()->getPlugins() as $id => $plugin) {
            $printform = ifset($plugin['printform']);
            if ($printform && $printform !== 'transfer') {
                $plugins[$id] = $plugin;
            }
        }

        if ($order) {

            foreach (array(shopPluginModel::TYPE_PAYMENT, shopPluginModel::TYPE_SHIPPING) as $type) {

                // Because of waOrder. Using ifempty/ifset is not correct
                $key = null;
                if (isset($order['params'])) {
                    $order_params = $order['params'];
                    if (isset($order_params[$type.'_id'])) {
                        $key = $order_params[$type.'_id'];
                    };
                }

                if (empty($key)) {
                    continue;
                }

                if (!($order instanceof waOrder)) {
                    $order = shopPayment::getOrderData($order);
                }

                try {

                    if ($type === shopPluginModel::TYPE_PAYMENT) {
                        $plugin = shopPayment::getPlugin(null, $key);
                    } else {
                        $plugin = shopShipping::getPlugin(null, $key);
                    }

                    if ($plugin && method_exists($plugin, 'getPrintForms')) {
                        $forms = $plugin->getPrintForms($order);
                        foreach ($forms as $id => $form) {
                            if (isset($form['emailprintform'])) {
                                $form['mail_url'] = "?module=order&action=sendprintform&form_id={$type}.{$id}";
                            }
                            $plugins["{$type}.{$id}"] = $form;
                        }
                    }
                } catch (waException $e) {
                }
            }

            foreach ($plugins as $plugin_id => & $plugin) {
                if (strpos($plugin_id, '.')) {
                    $plugin['url'] = "?module=order&action=printform&form_id={$plugin_id}&order_id={$order['id']}";
                } else {
                    if (!empty($plugin['emailprintform'])) {
                        $plugin['mail_url'] = "?module=order&action=sendprintform&plugin_id={$plugin_id}";
                    }

                    $plugin['url'] = "?plugin={$plugin_id}&module=printform&action=display&order_id={$order['id']}";
                }
            }
            unset($plugin);
        }

        //TODO separate backend & frontend
        return $plugins;
    }

    public static function getTransferPrintforms()
    {
        $plugins = array();
        foreach (wa('shop')->getConfig()->getPlugins() as $id => $plugin) {
            $printform = ifset($plugin['printform']);
            if ($printform && $printform === 'transfer') {
                $plugins[$id] = $printform;
            }
        }
        return $plugins;
    }

    public static function getAllPrintforms()
    {
        $plugins = array();
        foreach (wa('shop')->getConfig()->getPlugins() as $id => $plugin) {
            $printform = ifset($plugin['printform']);
            if ($printform) {
                $plugins[$id] = $plugin;
            }
        }
        return $plugins;
    }
}