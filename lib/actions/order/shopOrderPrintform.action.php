<?php

class shopOrderPrintformAction extends waViewAction
{
    /**
     * Params of order-list context of order
     * @var array|null
     */
    private $filter_params;
    /**
     * @var shopOrderModel
     */
    private $model;

    public function execute()
    {
        $id = waRequest::get('order_id');
        if (!$id) {
            throw new waException("Unknown order", 404);
        }

        $order = $this->getOrder($id);
        if (!$order) {
            $id = shopHelper::decodeOrderId($id);
            $order = $this->getOrder($id);
            if (!$order) {
                throw new waException("Unkown order", 404);
            }
        }

        $product_ids = array();
        foreach ($order['items'] as $item) {
            if ($item['type'] == 'product') {
                $product_ids[] = $item['product_id'];
            }
        }
        $product_ids = array_unique($product_ids);

        $form_id = waRequest::get('form_id');
        if (strpos($form_id, '.')) {
            list($type, $form) = explode('.', $form_id, 2);
        } else {
            $form = null;
            $type = $form_id;
        }

        $order_params_model = new shopOrderParamsModel();
        $params = $order_params_model->get($order['id']);

        $plugin = self::getPlugin($type, ifempty($params[$type . '_id']));
        if ($type == 'shipping') { /* add weight info only for shipping modules */
            /**
             * @var waShipping $plugin
             */

            $feature_model = new shopFeatureModel();
            $f = $feature_model->getByCode('weight');
            if (!$f) {
                $weights = array();
            } else {
                $values_model = $feature_model->getValuesModel($f['type']);
                $weights = $values_model->getProductValues($product_ids, $f['id']);
            }

            if ($weights) {
                $dimension = shopDimension::getInstance()->getDimension('weight');
                $weight_unit = $plugin->allowedWeightUnit();
                $m = null;
                if ($weight_unit != $dimension['base_unit']) {
                    $m = $dimension['units'][$weight_unit]['multiplier'];
                }
                foreach ($order['items'] as &$item) {
                    if ($item['type'] == 'product') {
                        if (isset($weights['skus'][$item['sku_id']])) {
                            $w = $weights['skus'][$item['sku_id']];
                        } else {
                            $w = isset($weights[$item['product_id']]) ? $weights[$item['product_id']] : 0;
                        }
                        if ($m !== null) {
                            $w = $w / $m;
                        }
                        $item['weight'] = $w;
                    }
                }
                unset($item);
            }
        }

        if (!$plugin) {
            throw new waException(_w('Printform not found'), 404);
        }
        print $plugin->displayPrintForm(ifempty($form, $plugin->getId()), shopPayment::getOrderData($order, $plugin));
        exit;
    }

    /**
     *
     * @param string $type
     * @param int $key
     * @return waSystemPlugin
     */
    private static function getPlugin($type, $key)
    {
        $plugin = null;
        if ($key) {
            switch ($type) {
                case 'payment':
                    $plugin = shopPayment::getPlugin(null, $key);
                    break;
                case 'shipping':
                    $plugin = shopShipping::getPlugin(null, $key);
                    break;
            }
        }
        return $plugin;
    }

    /**
     * @return shopOrderModel
     */
    public function getModel()
    {
        if ($this->model === null) {
            $this->model = new shopOrderModel();
        }
        return $this->model;
    }

    public function getOrder($id)
    {
        $order = $this->getModel()->getOrder($id);
        if (!$order) {
            return false;
        }
        return $order;

    }
}
