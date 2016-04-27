<?php

class shopOrdersPrintformsController extends waJsonController
{
    public function execute()
    {
        $order_ids = $this->getRequest()->request('order_id', null, waRequest::TYPE_ARRAY_INT);
        if ($order_ids === null) {
            $printforms = self::getAllPrintforms();
        } else {
            $printforms = self::getUnionOfPrintforms($order_ids);
        }
        $this->response = array(
            'printforms' => $printforms
        );
    }

    public static function getUnionOfPrintforms($order_ids = null)
    {
        $order_ids = array_map('intval', (array) $order_ids);

        $printforms = array();
        $count_map = array();

        foreach ($order_ids as $order_id) {
            $order = shopPayment::getOrderData($order_id);
            $order_printforms = shopPrintforms::getOrderPrintforms($order);

            // calculate union
            foreach ($order_printforms as $printform_id => $printform) {
                if (!isset($printforms[$printform_id])) {
                    $printforms[$printform_id] = $printform;
                    $count_map[$printform_id] = 0;
                }
                $count_map[$printform_id] += 1;
            }
        }

        foreach ($printforms as $plugin_id => &$plugin) {
            if (strpos($plugin_id, '.')) {
                $plugin['url'] = "?module=order&action=printform&form_id={$plugin_id}&order_id=:order_id";
            } else {
                $plugin['url'] = "?plugin={$plugin_id}&module=printform&action=display&order_id=:order_id";
            }
        }
        unset($plugin);

        $count = count($order_ids);
        foreach ($printforms as $printform_id => &$printform) {
            $printform['all'] = $count_map[$printform_id] >= $count;
        }
        unset($printform);

        return $printforms;

    }

    public static function getAllPrintforms()
    {
        $plugins = wa('shop')->getConfig()->getPlugins();
        foreach ($plugins as $id => $plugin) {
            $printform = ifset($plugin['printform']);
            $printform = $printform === true ? 'order' : $printform;
            if ($printform !== 'order') {
                unset($plugins[$id]);
            }
        }

        $model = new shopPluginModel();

        foreach (array(shopPluginModel::TYPE_PAYMENT, shopPluginModel::TYPE_SHIPPING) as $plugin_type) {
            foreach ($model->listPlugins($plugin_type, array('all' => true)) as $id => $info) {
                $plugin_id = $info['plugin'];
                try {
                    if ($plugin_type === shopPluginModel::TYPE_PAYMENT) {
                        $plugin = shopPayment::getPlugin($plugin_id);
                    } else {
                        $plugin = shopShipping::getPlugin($plugin_id);
                    }

                    if (!$plugin && !method_exists($plugin, 'getPrintForms')) {
                        continue;
                    }

                    $forms = $plugin->getPrintForms();
                    foreach ($forms as $id => $form) {
                        $key = $plugin_type . '.' . $id;
                        $plugins[$key] = $form;
                        $plugins[$key]['owner_name'] = $info['name'];
                    }
                } catch (Exception $e) {
                    // ignore
                }
            }
        }

        foreach ($plugins as $plugin_id => &$plugin) {
            if (strpos($plugin_id, '.')) {
                $plugin['url'] = "?module=order&action=printform&form_id={$plugin_id}&order_id=:order_id";
            } else {
                $plugin['url'] = "?plugin={$plugin_id}&module=printform&action=display&order_id=:order_id";
            }
            $plugin['all'] = true;
        }
        unset($plugin);

        return $plugins;
    }
}