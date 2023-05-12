<?php

/**
 * /order/calculate/ and /order/create/ in frontend:
 * JSON API for new single-page checkout.
 *
 * Both `calculate` and `create` endpoints accept same data but do validation
 * slightly differently.
 *
 * The main work is not done here, though.
 * See shopCheckoutStep::processAll() and all the steps in lib/classes/checkout2
 */
class shopFrontendOrderActions extends waJsonActions
{
    public function calculateAction()
    {
        $input = waRequest::post();

        if (!empty($input['use_session_input'])) {
            // Immediately after fast_render, use data from session storage
            $session_checkout = wa()->getStorage()->get('shop/checkout');
            $input = (!empty($session_checkout['order']) && is_array($session_checkout['order'])) ? $session_checkout['order'] : [];

            // Make sure all steps return HTML.
            // This helps in case session is empty (first time here or session lost).
            $input['auth']['html'] = 1;
            $input['region']['html'] = 'only';
            $input['shipping']['html'] = 'only';
            $input['details']['html'] = 'only';
            $input['payment']['html'] = 1;
            $input['confirm']['html'] = 1;
            unset($input['fast_render']); // paranoid
        } else {
            // Save checkout order block data in session
            $session_checkout = wa()->getStorage()->get('shop/checkout');
            $session_checkout = is_array($session_checkout) ? $session_checkout : [];
            $session_checkout['order'] = $input;
            wa()->getStorage()->set('shop/checkout', $session_checkout);
        }

        $format = strtolower(waRequest::request('response', 'json', 'string'));
        $data = shopCheckoutStep::processAll($format === 'html' ? 'form' : 'calculate', $this->makeOrderFromCart(), $input);
        if ($format === 'json') {
            $this->response = $data['result'];
            return;
        }

        // Render FrontendOrderForm.html
        $view = wa('shop')->getView();
        $old_vars = $view->getVars();

        $opts = waRequest::post('opts', [], 'array');
        $view->assign((new shopCheckoutViewHelper())->prepareFormVars($data) + [
                'options' => [
                    'DEBUG'   => ifset($opts, 'DEBUG', false),
                    'wrapper' => ifset($opts, 'wrapper', ''),
                ],
            ]);
        if(wa()->whichUI() === '1.3') {
            $html = $view->fetch(wa()->getAppPath('templates/actions-legacy/frontend/FrontendOrderForm.html', 'shop'));
        }else{
            $html = $view->fetch(wa()->getAppPath('templates/actions/frontend/FrontendOrderForm.html', 'shop'));
        }

        $view->clearAllAssign();
        $view->assign($old_vars);
        if ($format == 'html') {
            echo $html;
            exit;
        }

        // $format == 'both'
        $this->response = $data['result'];
        $this->response['html'] = $html;
    }

    public function createAction()
    {
        $data = shopCheckoutStep::processAll('create', $this->makeOrderFromCart(), waRequest::post());
        $config = $this->getCheckoutConfig();
        $options = [
            'is_company' => (int)$data['contact']['is_company'],
            'address'    => [
                'email' => ifset($data, 'result', 'auth', 'fields', 'email', 'value', null),
                'phone' => ifset($data, 'result', 'auth', 'fields', 'phone', 'value', null),
            ]
        ];
        $confirmation = new shopConfirmationChannel($options);

        if ($data['error_step_id']) {
            $this->response = $data['result'];
            return;
        }

        // Prepare customer data
        $contact_field_values = array_map(function ($f) {
            return $f['value'];
        }, $data['result']['auth']['fields']);
        if (isset($data['shipping']['address'])) {
            $contact_field_values['address.shipping'] = $data['shipping']['address'];
        }

        // In case customer is not logged in, should we
        // create new contact for this order or use an existing contact?
        $contact_id = $data['contact']['id'];
        if (!$contact_id) {
            switch ($config['confirmation']['order_without_auth']) {
                case 'existing_contact':
                case 'confirm_contact':
                    $saved_contact_id = $confirmation->getContact();
                    $contact_id = $saved_contact_id['id'] ? $saved_contact_id['id'] : $contact_id;
                    break;
                case 'create_contact':
                default:
                    // all fine: create new contact for this order
                    break;
            }
        }

        $contact_field_values = $this->updateUserPhone($contact_field_values, $contact_id);

        $order_data = [
            'id'              => null,
            'contact_id'      => $contact_id,
            'currency'        => $data['order']['currency'],
            'payment_params'  => ifempty($data, 'payment', 'params', null),
            'shipping_params' => ifempty($data, 'shipping', 'params', null),
            'params'          => [
                    'shipping_id' => ifset($data, 'shipping', 'selected_variant', 'variant_id', null),
                    'payment_id'  => ifset($data, 'payment', 'id', null),
                    'departure_datetime' => shopDepartureDateTimeFacade::getDeparture($config['schedule'])->getDepartureDateTime(),
                    // stock_id, virtualstock_id see below
                ] + $this->getOrderParamsFromOrder($data['order']) + $this->getOrderParamsFromRequest(),

            'comment'  => ifset($data, 'result', 'confirm', 'comment', ''),
            'shipping' => $data['order']['shipping'],

            'customer' => $contact_field_values,
            'items'    => $data['order']['items'],
            'discount' => 'calculate',
            'tax'      => 'calculate',
        ];

        $options = [
            'customer_validation_disabled' => true,
            'customer_is_company'          => $data['contact']['is_company'],
            'customer_form_fields'         => array_keys($contact_field_values),
            'customer_add_multifields'     => true,
            'ignore_stock_validate'        => true,
        ];

        try {
            $order = new shopOrder($order_data, $options);
        } catch (waException $ex) {
            $this->errors = [
                'general' => _w('Order creation error.'),
            ];
        }

        if ($this->errors) {
            return;
        }

        list($stock_id, $virtualstock_id) = shopFrontendCheckoutAction::determineStockIds($order);
        if ($stock_id || $virtualstock_id) {
            $order['params'] = [
                    'stock_id'        => $stock_id,
                    'virtualstock_id' => $virtualstock_id,
                ] + $order['params'];
        }

        try {
            $saved_order = $order->save();
            $this->response['order_id'] = $saved_order->getId();
            //In the one-time contact mode, you do not need to register them.
            if ($config['confirmation']['order_without_auth'] !== 'create_contact') {
                $this->sendRegisterMail($saved_order['contact_id']);
            }
            $confirmation->postConfirm($saved_order['contact_id'], $contact_id);

            // Clear cart and checkout data
            // Remove everything from cart
            (new shopCart())->clear();
            // Remove data we kept during checkout process
            wa()->getStorage()->remove('shop/checkout');
            // This tells checkout/success page to show proper order number
            wa()->getStorage()->set('shop/order_id', $this->response['order_id']);
            // This tells checkout/success to add google analytics code (separate key to only add it once)
            // see shopFrontendCheckoutAction->addGoogleAnalytics()
            wa()->getStorage()->set('shop/success_order_id', $this->response['order_id']);
        } catch (waException $ex) {
            $this->errors['shop_order'] = $order->errors();
            if (!empty($this->errors['shop_order']['order']['common'])) {
                $this->errors = [
                    'general' => $this->errors['shop_order']['order']['common'],
                ];
            } elseif (!$this->errors['shop_order']) {
                $this->errors = [
                    'general'   => _w('Unable to save order.'),
                    'exception' => $ex->getMessage(),
                ];
            }
        }
    }

    /**
     * Update phone numbers in international format
     * Occurs in the createAction method to minimize the impact on the user in case of errors during the checkout stages.
     *
     * @param $contact_fields
     * @param $contact_id
     * @return array
     * @throws waException
     */
    public function updateUserPhone($contact_fields, $contact_id)
    {
        $phone = ifset($contact_fields, 'phone', null);

        if (!$phone) {
            return $contact_fields;
        }

        //Try to transform the number in the international format
        $result = waDomainAuthConfig::factory()->transformPhone($phone);
        $transform_phone = $result['phone'];

        if ($result['status']) {
            // This is necessary so that shopOrder does not add the phone in the old format to the contact
            // Or in order for the new contact to keep the phone in the new format
            $contact_fields['phone'] = $transform_phone;

            if ($contact_id) {
                // Update numbers only for existing contact
                $contact = new waContact($contact_id);

                $saved_phones = $contact->get('phone');

                //find saved number
                foreach ($saved_phones as $id => $source) {
                    // Resave only those numbers that are not suitable for transformed
                    if ($source['value'] === $phone && $source['value'] !== $transform_phone) {
                        $saved_phones[$id]['value'] = $transform_phone;

                        // Only 1 number is transmitted in the form. Therefore, you can resave right in the loop.
                        $contact->set('phone', $saved_phones);
                        $contact->removeCache();

                        // If you do not save the contact, the new values ​​will not be available in shopOrder and in shopConfirmationChannel
                        $contact->save();
                        break;
                    }
                }
            }
        }

        return $contact_fields;
    }

    protected function sendRegisterMail($contact_id)
    {
        $contact = new waContact($contact_id);
        $result = $password = $template_variables = null;
        $template_variables = array();

        // If there is a password, do not need to register
        if ($contact['password']) {
            return false;
        }

        $password = null;

        $auth_config = waDomainAuthConfig::factory();

        $signup_notify = $auth_config->getSignUpNotify();
        if ($signup_notify) {
            $channels = $auth_config->getVerificationChannelInstances();
            foreach ($channels as $channel) {

                // for sms channel use not extended alphabet, for simply copy-paste from SMS on phone
                $is_sms_channel = $channel instanceof waVerificationChannelSMS;
                $is_extended = !$is_sms_channel;

                $len = 11;
                if (!$is_extended) {
                    $len = 13;  // lag of diversity compensate with greater length of password
                }

                $password = waContact::generatePassword($len, $is_extended);

                // You do not need to transfer the password in the one-time password mode
                if ($auth_config->getAuthType() !== $auth_config::AUTH_TYPE_ONETIME_PASSWORD) {
                    $template_variables = ['password' => $password];
                }

                $result = $channel->sendSignUpSuccessNotification($contact, $template_variables);
                if ($result) {
                    break;
                }
            }
        }

        if (!$password) {
            $password = waContact::generatePassword();
        }

        $contact->setPassword($password);
        $contact->save();

        return $result;
    }


    /**
     * Dialog to select shipping self-delivery point on a map
     */
    public function shippingDialogAction()
    {
        // Get input from POST or session
        $input = waRequest::post();
        if (empty($input)) {
            $session_checkout = wa()->getStorage()->get('shop/checkout');
            $input = (!empty($session_checkout['order']) && is_array($session_checkout['order'])) ? $session_checkout['order'] : [];
        }

        // Process checkout steps up to shipping
        $input['abort_after_step'] = 'shipping';
        $data = shopCheckoutStep::processAll('form', $this->makeOrderFromCart(), $input);

        // Make sure no step before shipping errs
        if (!empty($data['error_step_id']) && $data['error_step_id'] != 'shipping') {
            throw new waException('Bad input', 400);
        }

        $view = wa('shop')->getView();
        $view->assign($data['result'] + [
                'config'  => $this->getCheckoutConfig(),
                'contact' => $data['contact'],
            ]);

        if(wa()->whichUI() === '1.3') {
            echo $view->fetch(wa()->getAppPath('templates/actions-legacy/frontend/order/form/dialog/map.html', 'shop'));
        }else{
            echo $view->fetch(wa()->getAppPath('templates/actions/frontend/order/form/dialog/map.html', 'shop'));
        }

        exit;
    }

    /**
     * Render Dialog for Auth Forms (login, signup, forgotpassword, setpassword)
     * @return string
     */
    public function authDialogAction()
    {
        // Get type
        $type = waRequest::request('type');
        $type = is_scalar($type) ? (string)$type : '';

        // All known form types
        $types = array('login', 'signup', 'forgotpassword', 'setpassword');

        // Unknown form type case
        if (!in_array($type, $types, true)) {
            return '';
        }

        // define contact_type (aka customer_type)

        $route_params = wa()->getRouting()->getRoute();
        $checkout_storefront_id = isset($route_params['checkout_storefront_id']) ? $route_params['checkout_storefront_id'] : '';
        $checkout_config = new shopCheckoutConfig($checkout_storefront_id);

        if ($checkout_config['customer']['type'] === 'company') {
            $contact_type = 'company';
        } elseif ($checkout_config['customer']['type'] === 'person') {
            $contact_type = 'person';
        } elseif ($checkout_config['customer']['type'] === 'person_and_company') {
            $contact_type = waRequest::request('contact_type') === 'company' ? 'company' : 'person';
        } else {
            $contact_type = 'person';
        }

        // Template path
        if(wa()->whichUI() === '1.3') {
            $template_path = wa()->getAppPath("templates/actions-legacy/frontend/order/form/dialog/{$type}.html", 'shop');
        }else{
            $template_path = wa()->getAppPath("templates/actions/frontend/order/form/dialog/{$type}.html", 'shop');
        }


        // Render itself
        $view = wa('shop')->getView();
        $old_vars = $view->getVars();
        $view->assign(array(
            'type'         => $type,
            'contact_type' => $contact_type,             // need for signup form, to signup contact of proper type
            'hash'         => waRequest::request('hash')    // need for setpassword form
        ));
        $html = $view->fetch($template_path);
        $view->clearAllAssign();
        $view->assign($old_vars);

        // Result
        echo $html;
        exit;
    }

    protected function getOrderParamsFromRequest()
    {
        $params = [
            'ip'         => waRequest::getIp(),
            'user_agent' => waRequest::getUserAgent(),
        ];

        $routing_url = wa()->getRouting()->getRootUrl();
        $params['storefront'] = wa()->getConfig()->getDomain().($routing_url ? '/'.$routing_url : '');
        if (wa()->getStorage()->get('shop_order_buybutton')) {
            $params['sales_channel'] = 'buy_button:';
        }

        $ref = waRequest::cookie('referer');
        if ($ref) {
            $params['referer'] = $ref;
            $ref_parts = @parse_url($ref);
            $params['referer_host'] = $ref_parts['host'];
            // try get search keywords
            if (!empty($ref_parts['query'])) {
                $search_engines = array(
                    'text' => 'yandex\.|rambler\.',
                    'q'    => 'bing\.com|mail\.|google\.',
                    's'    => 'nigma\.ru',
                    'p'    => 'yahoo\.com',
                );
                $q_var = false;
                foreach ($search_engines as $q => $pattern) {
                    if (preg_match('/('.$pattern.')/si', $ref_parts['host'])) {
                        $q_var = $q;
                        break;
                    }
                }
                // default query var name
                if (!$q_var) {
                    $q_var = 'q';
                }
                parse_str($ref_parts['query'], $query);
                if (!empty($query[$q_var])) {
                    $params['keyword'] = $query[$q_var];
                }
            }
        }

        $utm = waRequest::cookie('utm');
        if ($utm) {
            $utm = json_decode($utm, true);
            if ($utm && is_array($utm)) {
                foreach ($utm as $k => $v) {
                    $params['utm_'.$k] = $v;
                }
            }
        }

        $landing = waRequest::cookie('landing');
        $landing = @parse_url($landing);
        if ($landing) {
            if (!empty($landing['query'])) {
                @parse_str($landing['query'], $arr);
                if (!empty($arr['gclid'])
                    && !empty($params['referer_host'])
                    && strpos($params['referer_host'], 'google') !== false
                ) {
                    $params['referer_host'] .= ' (cpc)';
                    $params['cpc'] = 1;
                } elseif (!empty($arr['_openstat'])
                    && !empty($params['referer_host'])
                    && strpos($params['referer_host'], 'yandex') !== false
                ) {
                    $params['referer_host'] .= ' (cpc)';
                    $params['openstat'] = $arr['_openstat'];
                    $params['cpc'] = 1;
                }
            }

            $params['landing'] = $landing['path'];
        }

        // A/B tests
        $abtest_variants_model = new shopAbtestVariantsModel();
        foreach (waRequest::cookie() as $k => $v) {
            if (substr($k, 0, 5) == 'waabt') {
                $variant_id = $v;
                $abtest_id = substr($k, 5);
                if (wa_is_int($abtest_id) && wa_is_int($variant_id)) {
                    $row = $abtest_variants_model->getById($variant_id);
                    if ($row && $row['abtest_id'] == $abtest_id) {
                        $params['abt'.$abtest_id] = $variant_id;
                    }
                }
            }
        }

        return $params;
    }

    protected function getOrderParamsFromOrder($order)
    {
        $result = $order['params'];
        foreach($result as $k => $v) {
            if (substr($k, 0, 9) == 'shipping_' || substr($k, 0, 8) == 'payment_') {
                unset($result[$k]);
            }
        }
        return $result;
    }

    public function makeOrderFromCart($cart_items = null, $session_data = null)
    {
        if ($cart_items === null) {
            $cart_items = (new shopCart())->items(true);
        }

        if ($session_data === null) {
            $session_data = wa()->getStorage()->get('shop/checkout', []);
        }
        $routing_url = wa()->getRouting()->getRootUrl();
        $order = new shopOrder([
            'contact_id' => ifempty(ref(wa()->getUser()->getId()), null),
            'currency'   => wa('shop')->getConfig()->getCurrency(false),
            'items'      => $cart_items,
            'discount'   => 'calculate',
            'tax'        => 'calculate',
            'params'     => [
                'coupon_code' => ifset($session_data, 'coupon_code', null),
                'storefront' => wa()->getConfig()->getDomain().($routing_url ? '/'.$routing_url : ''),
            ],
        ], [
            'items_format'       => 'cart',
            'items_extend_round' => true,
        ]);

        return $order;
    }

    public function addressAutocompleteAction()
    {
        $result = [];

        /**
         * @event address_autocomplete
         *
         * @params array full or partial address data
         *  country string
         *  region string
         *  city string
         *  country string|int
         *
         * @return array[] arrays
         */
        $params = [
            'country' => waRequest::request('country', null, waRequest::TYPE_STRING),
            'region'  => waRequest::request('region', null, waRequest::TYPE_STRING),
            'city'    => waRequest::request('city', null, waRequest::TYPE_STRING),
            'zip'     => waRequest::request('zip', null),
        ];
        $event = wa('shop')->event('address_autocomplete', $params);

        if ($event && is_array($event)) {
            foreach ($event as $plugin_id => $plugin_results) {
                if ($plugin_results && is_array($plugin_results)) {
                    foreach ($plugin_results as $plugin_result) {
                        $result[] = [
                            'country' => ifset($plugin_result, 'country', ''),
                            'region'  => ifset($plugin_result, 'region', ''),
                            'city'    => ifset($plugin_result, 'city', ''),
                            'zip'     => ifset($plugin_result, 'zip', ''),
                        ];
                    }
                }
            }
        }

        $this->response['result'] = array_unique($result, SORT_REGULAR);
    }

    protected function getCheckoutConfig()
    {
        return new shopCheckoutConfig(ifset(ref(wa()->getRouting()->getRoute()), 'checkout_storefront_id', null));
    }

    public function run($params = null)
    {
        $action = $params;
        if (!$action) {
            $action = 'default';
        }
        $this->action = $action;
        $this->preExecute();
        try {
            $this->execute($this->action);
        } catch (waException $e) {
            $default_controller = new waDefaultViewController();
            $default_controller->executeAction(new shopFrontendPageAction());
            $default_controller->display();
            return;
        }
        $this->postExecute();

        if ($this->action == $action) {
            if (waRequest::isXMLHttpRequest()) {
                $this->getResponse()->addHeader('Content-type', 'application/json');
            }
            $this->getResponse()->sendHeaders();
            if (!$this->errors) {
                echo waUtils::jsonEncode(array('status' => 'ok', 'data' => $this->response), JSON_UNESCAPED_UNICODE); // no pretty print even in debug here
            } else {
                echo waUtils::jsonEncode(array('status' => 'fail', 'errors' => $this->errors), JSON_UNESCAPED_UNICODE);
            }
        }
    }
}
