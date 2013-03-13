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

        $form_id = waRequest::get('form_id');
        if (strpos($form_id, '.')) {
            list($type, $form) = explode('.', $form_id, 2);
        } else {
            $form = null;
            $type = $form_id;
        }

        $order_params_model = new shopOrderParamsModel();
        $params = $order_params_model->get($order['id']);

        $plugin = self::getPlugin($type, ifempty($params[$type.'_id']));

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
