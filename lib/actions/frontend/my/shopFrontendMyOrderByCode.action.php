<?php

/**
 * Single order page with authorization by random code and pin.
 *
 * Link that contains auth_code, and a separate auth_pin are sent to customer by email.
 * Customer opens the link to get to this page, and enters the pin.
 * If the pin is correct, this controller shows order info.
 */
class shopFrontendMyOrderByCodeAction extends shopFrontendMyOrderAction
{
    public function execute()
    {
        $code = waRequest::param('code');
        $encoded_order_id = waRequest::param('id');
        $order_id = shopHelper::decodeOrderId($encoded_order_id);
        if (!$order_id) {
            // fall back to non-encoded id
            $order_id = $encoded_order_id;
            $encoded_order_id = shopHelper::encodeOrderId($order_id);
        }
        if (!$order_id || $order_id != substr($code, 16, -16)) {
            throw new waException(_w('Order not found'), 404);
        }

        // When user is authorized, check if order belongs to him.
        // When it does, redirect to plain order page.
        if (wa()->getUser()->isAuth()) {
            $om = new shopOrderModel();
            $order = $om->getOrder($order_id);
            if (!$order) {
                throw new waException(_w('Order not found'), 404);
            }
            if ($order['contact_id'] == wa()->getUser()->getId()) {
                $this->redirect(wa()->getRouteUrl('/frontend/myOrder', array('id' => $order_id)));
            }
        }

        // Check auth code
        $opm = new shopOrderParamsModel();
        $params = $opm->get($order_id);
        if (ifset($params['auth_code']) !== $code || empty($params['auth_pin'])) {
            throw new waException(_w('Order not found'), 404);
        }

        // Check auth pin and show order page if pin is correct
        $pin = waRequest::request('pin', wa()->getStorage()->get('shop/pin/'.$order_id));
        if ($pin && $pin == $params['auth_pin']) {
            wa()->getStorage()->set('shop/pin/'.$order_id, $pin);
            parent::execute();
            if (!waRequest::isXMLHttpRequest()) {
                $this->layout->assign('breadcrumbs', self::getBreadcrumbs());
            }
            return;
        }

        //
        // No pin or pin is incorrect: show form to enter pin
        //

        $this->view->assign('wrong_pin', !!$pin);
        $this->view->assign('pin_required', true);
        $this->view->assign('encoded_order_id', $encoded_order_id);

        $this->view->assign('my_nav_selected', 'orders');
        // Set up layout and template from theme
        $this->setThemeTemplate('my.order.html');
        if (!waRequest::isXMLHttpRequest()) {
            $this->setLayout(new shopFrontendLayout());
            $this->getResponse()->setTitle(_w('Order').' '.$encoded_order_id);
            $this->view->assign('breadcrumbs', self::getBreadcrumbs());
            $this->layout->assign('nofollow', true);
        }
    }

    public static function getBreadcrumbs()
    {
        return array();
    }

    protected function isAuth($order)
    {
        return $order['state_id'] != 'deleted';
    }
}

