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

        $order = $this->getOrder();
        if (!$order) {
            throw new waException(_w('Order not found'), 404);
        }

        $order_id = $order['id'];
        $encoded_order_id = shopHelper::encodeOrderId($order_id);


        // When user is authorized, check if order belongs to him.
        // When it does, redirect to plain order page.
        if (wa()->getUser()->isAuth()) {
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

            // signup
            $this->trySignUp($order['contact_id']);

            parent::execute();
            if (!waRequest::isXMLHttpRequest()) {
                $this->layout->assign('breadcrumbs', self::getBreadcrumbs());
            }
            return;
        } else {
            // Provide at least basic info about the order for template
            $order['id_str'] = shopHelper::encodeOrderId($order['id']);
            $this->view->assign('order', $order);
        }

        //
        // No pin or pin is incorrect: show form to enter pin
        //
        
        $this->view->assign('wrong_pin', !!$pin);
        $this->view->assign('pin_required', true);
        $this->view->assign('encoded_order_id', $encoded_order_id);
        $this->view->assign('frontend_my_order', array()); // avoids notice in theme

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

    /**
     * @return array|null
     */
    protected function getOrder()
    {
        $code = waRequest::param('code');
        $order_id = substr($code, 16, -16);
        $om = new shopOrderModel();
        $order = $om->getOrder($order_id);
        if ($order && $order['state_id'] !== 'deleted') {
            return $order;
        } else {
            return null;
        }
    }

    /**
     * @param int $contact_id
     * @throws waException
     */
    protected function trySignUp($contact_id)
    {
        // already auth in session - no need to signup
        if (wa()->getUser()->isAuth()) {
            return;
        }

        // contact was deleted
        $contact = new waContact($contact_id);
        if (!$contact->exists()) {
            return;
        }

        // contact is already singed up
        $is_signed_up = (bool)$contact->get('password');
        if ($is_signed_up) {
            return;
        }

        // if auth in this site is disabled or auth is by onetime passwords
        $site_auth_config = waDomainAuthConfig::factory();
        if (!$site_auth_config->isAuthEnabled() || $site_auth_config->getAuthType() == waAuthConfig::AUTH_TYPE_ONETIME_PASSWORD) {
            return;
        }

        // well, we good to go try and signup contact
        $is_signed_up = $this->signup($contact, $site_auth_config);
        if ($is_signed_up) {
            wa()->getAuth()->auth(['id' => $contact->getId()]);
        }
    }

    /**
     * Signup contact (save generated password) and sent notification about it
     * @param waContact $contact
     * @param waDomainAuthConfig $auth_config
     * @return bool
     * @throws waException
     */
    protected function signup(waContact $contact, waDomainAuthConfig $auth_config)
    {
        $password = null;
        $sent = null;

        $addresses = [
            'email' => $contact->get('email', 'default'),
            'phone' => $contact->get('phone', 'default')
        ];

        $channels = $auth_config->getVerificationChannelInstances();
        foreach ($channels as $channel) {

            // options for send method
            $options = array(
                'site_url' => $auth_config->getSiteUrl(),
                'site_name' => $auth_config->getSiteName(),
                'login_url' => $auth_config->getLoginUrl([], true)
            );

            if ($channel->isEmail() && !empty($addresses['email'])) {
                $address = $addresses['email'];

                $password = waContact::generatePassword();
                $options['password'] = $password;

                $sent = $channel->sendSignUpSuccessNotification($address, $options);
            } elseif ($channel->isSMS() && !empty($addresses['phone'])) {

                // generate password on "not extended" alphabet, len is slightly greater to compensate lag of diversity
                $password = waContact::generatePassword(13, false);
                $options['password'] = $password;

                $phone = $addresses['phone'];
                $is_international = substr($phone, 0, 1) === '+';

                $sent = $channel->sendSignUpSuccessNotification($phone, $options);

                // Not sent, maybe because of sms adapter not work correct with not international phones
                if (!$sent && !$is_international) {
                    // If not international phone number - transform 8 to code (country prefix)
                    $transform_result = $auth_config->transformPhone($phone);
                    if ($transform_result['status']) {
                        $phone = $transform_result['phone'];
                        $sent = $channel->sendSignUpSuccessNotification($phone, $options);
                    }
                }
            }

            if ($sent) {
                break;
            }
        }

        if ($sent && $password) {
            $contact->save(array('password' => $password));
            return true;
        }

        return false;
    }
}

