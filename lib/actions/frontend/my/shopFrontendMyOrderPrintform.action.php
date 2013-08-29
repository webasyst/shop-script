<?php
class shopFrontendMyOrderPrintformAction extends waViewAction
{
    public function execute()
    {
        $om = new shopOrderModel();
        $encoded_order_id = waRequest::param('id');
        $code = waRequest::param('code');

        $order_id = shopHelper::decodeOrderId($encoded_order_id);
        if (!$order_id) {
            // fall back to non-encoded id
            $order_id = $encoded_order_id;
            $encoded_order_id = shopHelper::encodeOrderId($order_id);
        }

        $order = $om->getOrder($order_id);
        if (!$order) {
            throw new waException(_w('Order not found'), 404);
        } elseif (!$this->isAuth($order, $code)) {
            if ($code && ($order_id != substr($code, 16, -16))) {
                throw new waException(_w('Order not found'), 404);
            } else {
                $redirect = array(
                    'id' => $order_id,
                );
                if (!empty($code)) {
                    $redirect['code'] = $code;
                }
                $url = $code ? '/frontend/myOrderByCode' : '/frontend/myOrder';
                $this->redirect(wa()->getRouteUrl($url, $redirect));
            }
        } elseif ($code && ($order['contact_id'] == wa()->getUser()->getId())) {
            $redirect = array(
                'id'        => $order_id,
                'form_type' => waRequest::param('form_type'),
                'form_id'   => waRequest::param('form_id'),
            );
            $this->redirect(wa()->getRouteUrl('/frontend/myOrderPrintform', $redirect));
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

    protected function isAuth($order, &$code)
    {
        $result = false;
        if (!$result) {
            $result = ($order['id'] == wa()->getStorage()->get('shop/order_id'));
        }
        if (!$result) {
            // Check that order exists and belongs to this user
            $result = ($order['contact_id'] == wa()->getUser()->getId()) && ($order['state_id'] != 'deleted');
        }
        if (!$result && $code) {
            // Check auth code
            $opm = new shopOrderParamsModel();
            $params = $opm->get($order['id']);
            if (!empty($params['auth_pin']) && (ifset($params['auth_code']) === $code)) {
                $pin = wa()->getStorage()->get('shop/pin/'.$order['id']);
                if ($pin && $pin == $params['auth_pin']) {
                    $result = true;
                }
            } else {
                $code = false;
            }
        }
        return $result;
    }
}
