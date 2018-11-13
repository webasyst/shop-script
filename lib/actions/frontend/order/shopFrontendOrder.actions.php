<?php
/**
 * /order/calculate/ page in frontend: JSON API for new single-page checkout
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
            'config' => $config,
            'contact' => $data['contact'],
            'options' => [
                'DEBUG' => ifset($opts, 'DEBUG', false),
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
        $contact_field_values = array_map(function($f) {
            return $f['value'];
        }, $data['result']['auth']['fields']);
        $contact_field_values['address.shipping'] = $data['shipping']['address'];

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
                    foreach(['phone', 'email'] as $field_id) {
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
            'id'                   => null,
            'contact_id'           => $contact_id,
            'currency'             => $data['order']['currency'],
            'payment_params'       => ifempty($data, 'payment', 'params', null),
            'shipping_params'      => ifempty($data, 'shipping', 'params', null),
            'params'               => [
                'shipping_id'  => ifset($data, 'shipping', 'selected_variant', 'variant_id', null),
                'payment_id'   => ifset($data, 'payment', 'id', null),
                // stock_id, virtualstock_id see below
            ] + $this->getOrderParamsFromRequest(),

            'comment'  => ifset($data, 'result', 'confirm', 'comment', ''),

            'customer' => $contact_field_values,
            'items'    => $data['order']['items'],
        ];

        $options = [
            'customer_validation_disabled' => true,
            'customer_is_company' => $data['contact']['is_company'],
            'customer_form_fields' => array_keys($contact_field_values),
            'ignore_stock_validate' => true,
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
                'stock_id'     => $stock_id,
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
                    'general' => _w('Unable to save order.'),
                    'exception' => $ex->getMessage(),
                ];
            }
        }
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

        // Template path
        $template_path = wa()->getAppPath("templates/actions/frontend/order/form/dialog/{$type}.html", 'shop');

        // Render itself
        $view = wa('shop')->getView();
        $old_vars = $view->getVars();
        $view->assign(array(
            'type' => $type,
            'hash' => waRequest::request('hash')    // need for setpassword form
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
            'ip' => waRequest::getIp(),
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

    public function makeOrderFromCart($cart_items=null, $session_data=null)
    {
        if ($cart_items === null) {
            $cart_items = (new shopCart())->items(true);
        }

        if ($session_data === null) {
            $session_data = wa()->getStorage()->get('shop/checkout', []);
        }

        return new shopOrder([
            'contact_id' => ifempty(ref(wa()->getUser()->getId()), null),
            'currency' => wa('shop')->getConfig()->getCurrency(false),
            'items' => $cart_items,
            'discount' => 'calculate',
        ], [
            'items_format' => 'cart',
            'items_extend_round' => true,
        ]);
    }

    protected function getCheckoutConfig()
    {
        return new shopCheckoutConfig(ifset(ref(wa()->getRouting()->getRoute()), 'checkout_storefront_id', null));
    }
}
