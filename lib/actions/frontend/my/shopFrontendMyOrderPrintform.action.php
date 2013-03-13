<?php
class shopFrontendMyOrderPrintformAction extends waViewAction
{
    public function execute()
    {
        // Check that order exists and belongs to this user
        $om = new shopOrderModel();
        $encoded_order_id = waRequest::param('id');
        $order_id = shopHelper::decodeOrderId($encoded_order_id);
        if (!$order_id) {
            // fall back to non-encoded id
            $order_id = $encoded_order_id;
            $encoded_order_id = shopHelper::encodeOrderId($order_id);
        }

        $order = $om->getOrder($order_id);
        if (!$order || !$this->isAuth($order)) {
            throw new waException(_w('Order not found'), 404);
        }

        $order_params_model = new shopOrderParamsModel();
        $order['params'] = $order_params_model->get($order['id']);
        $order['id_str'] = $encoded_order_id;

        switch (waRequest::param('form_type')) {
            case 'payment':

                if (empty($order['params']['payment_id']) || !($payment = shopPayment::getPlugin(null, $order['params']['payment_id']))) {
                    throw new waException(_w('Printform not found'), 404);
                }
                $form_id = waRequest::param('form_id');
                $params = null;
                if (strpos($form_id, '.')) {
                    $form = explode('.', $form_id, 2);
                    $form_id = array_shift($form);
                    $params = array_shift($form);
                }
                print $payment->displayPrintForm(ifempty($form_id, $payment->getId()), shopPayment::getOrderData($order, $payment), intval($params));
                exit;
                break;
            default:
                throw new waException(_w('Printform not found'), 404);
                break;
        }
    }
    protected function isAuth($order)
    {
        $result = false;
        if (!$result) {
            $result = ($order['id'] == wa()->getStorage()->get('shop/order_id'));
        }
        if (!$result) {
            $result = ($order['contact_id'] == wa()->getUser()->getId()) && ($order['state_id'] != 'deleted');
        }
        return $result;
    }
}
