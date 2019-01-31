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

        // Save checkout order block data in session
        $session_checkout = wa()->getStorage()->get('shop/checkout');
        $session_checkout = is_array($session_checkout) ? $session_checkout : [];
        $session_checkout['order'] = $input;
        wa()->getStorage()->set('shop/checkout', $session_checkout);

        $data = shopCheckoutStep::processAll('calculate', $this->makeOrderFromCart(), $input);
        $this->response = $data['result'];

        $format = strtolower(waRequest::request('response', 'json', 'string'));
        if ($format == 'json') {
            return;
        }

        // Render FrontendOrderForm.html
        $view = wa('shop')->getView();
        $old_vars = $view->getVars();
        $opts = waRequest::post('opts', [], 'array');
        $config = $this->getCheckoutConfig();
        $view->assign($this->response + [
                'config'  => $config,
                'contact' => $data['contact'],
                'options' => [
                    'DEBUG'   => ifset($opts, 'DEBUG', false),
                    'wrapper' => ifset($opts, 'wrapper', ''),
                ],
            ]);
        $html = $view->fetch(wa()->getAppPath('templates/actions/frontend/FrontendOrderForm.html', 'shop'));
        $view->clearAllAssign();
        $view->assign($old_vars);
        if ($format == 'html') {
            echo $html;
            exit;
        }

        $this->response['html'] = $html;
    }

    public function createAction()
    {
        $data = shopCheckoutStep::processAll('create', $this->makeOrderFromCart(), waRequest::post());
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
            $config = $this->getCheckoutConfig();
            switch ($config['confirmation']['order_without_auth']) {
                case 'confirm_contact':
                    // Must be authorised.
                    $this->errors = [
                        'please_log_in' => [_w('You must be logged in to complete this order.')],
                    ];
                    return;
                case 'existing_contact':
                    // Attempt to find existing contact by phone and then by email
                    // !!! TODO: should probably look into which auth scheme is currently preferred...
                    foreach (['phone', 'email'] as $field_id) {
                        if (!empty($contact_field_values[$field_id])) {
                            $c = new waContact();
                            $c[$field_id] = $contact_field_values[$field_id];
                            $value = ifset($c, $field_id, 0, 'value', null);
                            if ($value) {
                                $c = new waContactsCollection('search/'.$field_id.'='.str_replace('&', '\\&', $value).'&is_company='.((int)$data['contact']['is_company']));
                                $c = reset(ref($c->getContacts('id,is_company', 0, 1)));
                                if (!empty($c['id'])) {
                                    $contact_id = $c['id'];
                                    break;
                                }
                            }
                        }
                    }
                    break;
                case 'create_contact':
                default:
                    // all fine: create new contact for this order
                    break;
            }
        }

        $order_data = [
            'id'              => null,
            'contact_id'      => $contact_id,
            'currency'        => $data['order']['currency'],
            'payment_params'  => ifempty($data, 'payment', 'params', null),
            'shipping_params' => ifempty($data, 'shipping', 'params', null),
            'params'          => [
                    'shipping_id' => ifset($data, 'shipping', 'selected_variant', 'variant_id', null),
                    'payment_id'  => ifset($data, 'payment', 'id', null),
                    // stock_id, virtualstock_id see below
                ] + $this->getOrderParamsFromRequest(),

            'comment'  => ifset($data, 'result', 'confirm', 'comment', ''),
            'shipping' => $data['order']['shipping'],

            'customer' => $contact_field_values,
            'items'    => $data['order']['items'],
            'discount' => 'calculate',
        ];

        $options = [
            'customer_validation_disabled' => true,
            'customer_is_company'          => $data['contact']['is_company'],
            'customer_form_fields'         => array_keys($contact_field_values),
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

            // Clear cart anc checkout data
            (new shopCart())->clear();
            wa()->getStorage()->remove('shop/checkout');
            wa()->getStorage()->set('shop/order_id', $this->response['order_id']);
        } catch (waException $ex) {
            $this->errors['shop_order'] = $order->errors();
            if (!$this->errors['shop_order']) {
                $this->errors = [
                    'general'   => _w('Unable to save order.'),
                    'exception' => $ex->getMessage(),
                ];
            }
        }
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

        echo $view->fetch(wa()->getAppPath('templates/actions/frontend/order/form/dialog/map.html', 'shop'));
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
        $template_path = wa()->getAppPath("templates/actions/frontend/order/form/dialog/{$type}.html", 'shop');


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

    public function makeOrderFromCart($cart_items = null, $session_data = null)
    {
        if ($cart_items === null) {
            $cart_items = (new shopCart())->items(true);
        }

        if ($session_data === null) {
            $session_data = wa()->getStorage()->get('shop/checkout', []);
        }

        return new shopOrder([
            'contact_id' => ifempty(ref(wa()->getUser()->getId()), null),
            'currency'   => wa('shop')->getConfig()->getCurrency(false),
            'items'      => $cart_items,
            'discount'   => 'calculate',
        ], [
            'items_format'       => 'cart',
            'items_extend_round' => true,
        ]);
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
        $this->execute($this->action);
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
