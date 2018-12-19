<?php
/**
 * Represents all settings of new checkout for a single settlement.
 */
class shopCheckoutConfig implements ArrayAccess
{
    const DESIGN_LOGOS_DIR = 'shop/storefronts/logos';

    const SETTING_TYPE_BOOL = 'bool';
    const SETTING_TYPE_SCALAR = 'scalar';
    const SETTING_TYPE_ARRAY = 'array';
    const SETTING_TYPE_VARIANT = 'variant';

    const FIELD_WIDTH_MINI = 'mini';
    const FIELD_WIDTH_SMALL = 'small';
    const FIELD_WIDTH_MEDIUM = 'medium';
    const FIELD_WIDTH_LARGE = 'lagre';

    const DISCOUNT_ITEM_TYPE_AMOUNT = 'amount';
    const DISCOUNT_ITEM_TYPE_STRIKEOUT = 'strikeout';

    const DISCOUNT_GENERAL_TYPE_AMOUNT = 'total_amount';
    const DISCOUNT_GENERAL_TYPE_SEPARATION = 'separation_amount';

    const ORDER_MODE_TYPE_DEFAULT = 'default';
    const ORDER_MODE_TYPE_MINIMUM = 'minimum';

    const PICKUPPOINT_MAP_TYPE_ALWAYS = 'always';
    const PICKUPPOINT_MAP_TYPE_EXCEPT_GADGETS = 'except_gadgets';
    const PICKUPPOINT_MAP_TYPE_NEVER = 'never';

    const CUSTOMER_TYPE_PERSON = 'person';
    const CUSTOMER_TYPE_COMPANY = 'company';
    const CUSTOMER_TYPE_PERSON_AND_COMPANY = 'person_and_company';

    const CUSTOMER_SERVICE_AGREEMENT_TYPE_NO = '';
    const CUSTOMER_SERVICE_AGREEMENT_TYPE_NOTICE = 'notice';
    const CUSTOMER_SERVICE_AGREEMENT_TYPE_CHECKBOX = 'checkbox';

    const ORDER_WITHOUT_AUTH_CREATE = 'create_contact';
    const ORDER_WITHOUT_AUTH_EXISTING = 'existing_contact';
    const ORDER_WITHOUT_AUTH_CONFIRM = 'confirm_contact';

    const SCHEDULE_MODE_DEFAULT = 'default';
    const SCHEDULE_MODE_CUSTOM = 'custom';

    /**
     * @var array
     */
    protected static $static_cache;

    protected $contact_fields = [];
    protected $contact_address_fields = [];

    protected $config_elements = [
        'design',
        'cart',
        'schedule',
        'order',
        'customer',
        'shipping',
        'payment',
        'confirmation',
    ];

    /**
     * @var string
     */
    protected $storefront;

    /**
     * @var array
     */
    protected $config = [];

    /**
     * The flag indicating the sign that the config is default.
     * @var bool
     */
    protected $is_default = false;

    /**
     * shopCheckoutConfig constructor.
     * @param string|array $storefront string - checkout_storefront_id value from routing.php
     *                                 array  - the config itself
     * @throws waException
     */
    public function __construct($storefront)
    {
        $this->loadDefaultConfig();
        if (empty(self::$static_cache['default'])) {
            throw new waException('Error loading the default settings of the storefront.', 502);
        }
        if (is_array($storefront)) {
            $this->config = $storefront;
        } else {
            $this->storefront = (string)$storefront;
            $valid_storefront = $this->validateStorefrontId();
            if (!$valid_storefront) {
                throw new waException('Invalid storefront id');
            }
            $this->loadConfig();
        }
        $this->ensureConsistency();
        $this->prepareConfig();
    }

    public function getStorefront()
    {
        return $this->storefront;
    }

    public function isDefault()
    {
        return (bool) $this->is_default;
    }

    //

    public function setData($data)
    {
        $data = is_array($data) ? $data : [];
        foreach ($data as $element => $keys) {
            foreach ($keys as $key => $value) {
                $this->setValue($element, $key, $value);
                $this->is_default = false;
            }
        }
    }

    protected function prepareConfig()
    {
        $config = [];
        $settings_map = $this->getSettingsMap();
        foreach ($settings_map as $block => $keys) {
            foreach ($keys as $key => $_) {
                $config[$block][$key] = $this->getValue($block, $key);
            }
        }

        // Build path to logo
        if ($config['design']['custom'] && $config['design']['logo']) {
            $config['design']['logo_url'] = self::getLogoUrl($config['design']['logo']);
        }

        $this->config = $config;
    }

    protected function getValue($element, $key)
    {
        $method = $this->getMethodByKey('get', $element, $key);
        return $method ? $this->{$method}($element, $key) : null;
    }

    protected function setValue($element, $key, $value)
    {
        $method = $this->getMethodByKey('set', $element, $key);
        if ($method) {
            $this->{$method}($element, $key, $value);
        }
    }

    public function commit()
    {
        if (!$this->storefront) {
            waException::dump('In test mode it is impossible to save the config', $this->config);
        }

        $this->ensureConsistency();

        unset($this->config['design']['logo_url']);

        $path = $this->getConfigPath();
        if (file_exists($path)) {
            $full_config = include($path);
        } else {
            $full_config = [];
        }
        $full_config[$this->storefront] = $this->config;
        return waUtils::varExportToFile($full_config, $path);
    }

    //
    // Variants
    //

    public function getFieldWidthVariants()
    {
        return [
            self::FIELD_WIDTH_MINI    => [
                'name' => _w('Mini'),
            ],
            self::FIELD_WIDTH_SMALL  => [
                'name' => _w('Narrow'),
            ],
            self::FIELD_WIDTH_MEDIUM => [
                'name' => _w('Medium'),
            ],
            self::FIELD_WIDTH_LARGE   => [
                'name' => _w('Large'),
            ],
        ];
    }

    public function getCartDiscountItemVariants()
    {
        return [
            self::DISCOUNT_ITEM_TYPE_STRIKEOUT => [
                'name' => _w('Only compare at price without discount'),
            ],
            self::DISCOUNT_ITEM_TYPE_AMOUNT    => [
                'name' => _w('Compare at price without discount and discount amount'),
            ],
        ];
    }

    public function getCartDiscountGeneralVariants()
    {
        return [
            self::DISCOUNT_GENERAL_TYPE_AMOUNT     => [
                'name' => _w('Only common discount amount'),
            ],
            self::DISCOUNT_GENERAL_TYPE_SEPARATION => [
                'name' => _w('Extra information about discounts by coupons and bonus points'),
            ],
        ];
    }

    public function getOrderModeVariants()
    {
        return [
            self::ORDER_MODE_TYPE_DEFAULT => [
                'name'        => _w('Default'),
                'description' => _w('Available shipping options are grouped by type—“courier”, “pickup”, “post”.'),
            ],
            self::ORDER_MODE_TYPE_MINIMUM => [
                'name'        => _w('Minimal'),
                'description' => _w('Shipping options are not grouped by types—“courier”, “pickup”, “post”. There is no option to select a pickup point on a map.'),
            ],
        ];
    }

    public function getOrderShowPickuppointMapVariants()
    {
        return [
            self::PICKUPPOINT_MAP_TYPE_ALWAYS         => [
                'name' => _w('Always show'),
            ],
            self::PICKUPPOINT_MAP_TYPE_EXCEPT_GADGETS => [
                'name' => _w('Only on screens over 760 pixels wide'),
            ],
            self::PICKUPPOINT_MAP_TYPE_NEVER          => [
                'name' => _w('Never show'),
                'description' => _w('A small map with one pickup point will be shown by a click on an address in the pickup point selection dialog.'),
            ],
        ];
    }

    public function getCustomerTypeVariants()
    {
        return [
            self::CUSTOMER_TYPE_PERSON             => [
                'name' => _w('Persons'),
            ],
            self::CUSTOMER_TYPE_COMPANY            => [
                'name' => _w('Companies'),
            ],
            self::CUSTOMER_TYPE_PERSON_AND_COMPANY => [
                'name' => _w('Persons & companies'),
            ],
        ];
    }

    public function getCustomerServiceAgreementVariants()
    {
        return [
            self::CUSTOMER_SERVICE_AGREEMENT_TYPE_NO       => [
                'name' => _w('Do not require consent to personal data protection policy'),
            ],
            self::CUSTOMER_SERVICE_AGREEMENT_TYPE_NOTICE   => [
                'name'         => _w('Show only notice and link to policy'),
                'default_text' => _w('By submitting this form I agree to <a href="---INSERT A LINK HERE!---" target="_blank">personal data protection policy</a>'),
            ],
            self::CUSTOMER_SERVICE_AGREEMENT_TYPE_CHECKBOX => [
                'name'         => _w('Show mandatory checkbox, notice, and link'),
                'default_text' => _w('I agree to <a href="---INSERT A LINK HERE!---" target="_blank">personal data protection policy</a>'),
            ],
        ];
    }

    public function getConfirmationOrderWithoutAuthVariants()
    {
        return [
            self::ORDER_WITHOUT_AUTH_CREATE   => [
                'name' => _w('Create new customer profile for every guest order'),
            ],
            self::ORDER_WITHOUT_AUTH_EXISTING => [
                'name' => _w('Add an order to existing customer profile with the same phone number or email address'),
                'description' => _w('Existing customer’s data will be updated by the data from a new order.'),
            ],
            self::ORDER_WITHOUT_AUTH_CONFIRM  => [
                'name' => _w('Checkout is not allowed without email address or phone number confirmation'),
            ],
        ];
    }

    public function getScheduleModeVariants()
    {
        return [
            self::SCHEDULE_MODE_DEFAULT => [
                'name'        => _w('Common working schedule'),
                'description' => sprintf(_w('Section “<a href="?action=settings#/schedule/" target="_blank">%s</a>” <i class="icon16 new-window"></i> settings are used'), _w('Working schedule')),
            ],
            self::SCHEDULE_MODE_CUSTOM  => [
                'name'        => _w('Custom working schedule for this storefront'),
                'description' => _w('Select if your online store has several storefronts with different working schedules'),
            ],
        ];
    }

    //
    //
    //

    /**
     * @return waStorage
     */
    public function getStorage()
    {
        return new waPrefixStorage(['namespace'=>'shop_checkout2']);
    }

    /**
     * Return steps of new (8.0+) checkout
     * @return shopCheckoutStep[]
     * @throws waException
     */
    public function getCheckoutSteps()
    {
        $array = [];
        foreach ([
                     'auth',     // shopCheckoutAuthStep
                     'region',   // shopCheckoutRegionStep
                     'shipping', // shopCheckoutShippingStep
                     'details',  // shopCheckoutDetailsStep
                     'payment',  // shopCheckoutPaymentStep
                     'confirm',  // shopCheckoutConfirmStep
                 ] as $step_id) {
            $class = 'shopCheckout'.ucfirst(strtolower($step_id)).'Step';
            if (class_exists($class)) {
                $array[$step_id] = new $class($this);
            }
            if (empty($array[$step_id]) || !($array[$step_id] instanceof shopCheckoutStep)) {
                throw new waException('Incorrect checkout step: '.$class);
            }
        }

        wa('shop')->event('checkout_steps', ref([
            'steps' => &$array,
        ]));

        return $array;
    }

    /**
     * Ask shipping plugins to provide variants for shipping given items to given address
     * @param $address array with three required keys: country, region, city; zip is required if enabled in checkout settings
     * @param $items array as returned by shopOrder['items']
     * @param string $customer_type ,
     * @param $single_plugin_params array
     * @return array
     */
    public function getShippingRates($address, $items, $customer_type = self::CUSTOMER_TYPE_PERSON_AND_COMPANY, $single_plugin_params = [])
    {
        $params = [
            'timeout'            => $this['shipping']['plugin_timeout'],
            'raw_rate'           => true,
            'filter'             => [
                'services_by_type' => true,
            ],
            'departure_datetime' => shopDepartureDateTimeFacade::getDeparture($this['schedule']),
            'customer_type'      => $customer_type == self::CUSTOMER_TYPE_PERSON_AND_COMPANY ? '' : $customer_type,
            'currency'           => $this->getFrontendCurrency(),
        ];

        if ($single_plugin_params) {
            // Ask a single specific plugin for its rates
            $params['shipping'] = ['id' => $single_plugin_params['id']];
            $params['shipping_params'][$single_plugin_params['id']] = $single_plugin_params['shipping_params'];
            if (isset($single_plugin_params['service'])) {
                $params['shipping_params'][$single_plugin_params['id']]['service'] = $single_plugin_params['service'];
            }
        } else {
            // filter plugins by what's specified in routing
            $allowed_shipping_id = waRequest::param('shipping_id');
            if ($allowed_shipping_id && is_array($allowed_shipping_id)) {
                $params['shipping']['id'] = $allowed_shipping_id;
            }
        }

        $result = $this->getShippingMethods($address, $items, $params);
        foreach ($result as $r_id => &$r) {
            $r['variant_id'] = $r_id;
            if (!array_key_exists('rate', $r) || $r['rate'] === false) {
                if ($single_plugin_params) {
                    $r['rate'] = null;
                } else {
                    unset($result[$r_id]);
                }
            }
        }
        unset($r);
        return $result;
    }

    // Overridden in unit tests
    protected function getShippingMethods($address, $items, $params)
    {
        $call_limit = 0.2;
        if (defined('SHOP_SHIPPING_PLUGINS_CACHE_TIME_LIMIT')) {
            $call_limit = (float) SHOP_SHIPPING_PLUGINS_CACHE_TIME_LIMIT;
        }
        $time_start = microtime(true);
        $function_cache = new waFunctionCache(['shopHelper', 'getShippingMethods'], [
            'call_limit' => $call_limit,
            'namespace'  => 'shop/shipping_methods',
            'ttl'        => 300, // 5 min
            'hard_clean' => true,
            'hash_salt'  => wa()->getLocale().$this->getStorefront().$this->getShopConfig()->getCurrency(false),
        ]);
        $result = $function_cache->call($address, $items, $params);
        $time_delta = round(microtime(true) - $time_start, 3);
        if (defined('SHOP_CHECKOUT2_PROFILING')) {
            waLog::log('getShippingMethods('.(isset($params['shipping']['id']) ? 'single' : 'all').') - '.$function_cache->last_call_cache_status.' - '.$time_delta, 'checkout2-time.log');
        }
        return $result;
    }

    /**
     * Available payment options based on selected shipping plugin.
     *
     * @param int|null    $selected_shipping_plugin_id
     * @param string      $customer_type
     * @param string|null $shipping_type
     * @return array
     */
    public function getPaymentRates($selected_shipping_plugin_id = null, $customer_type = self::CUSTOMER_TYPE_PERSON_AND_COMPANY, $shipping_type = null)
    {
        $methods = $this->getPaymentMethods($selected_shipping_plugin_id, $customer_type, $shipping_type);
        $currencies = $this->getCurrencies();
        foreach ($methods as $key => &$m) {
            try {
                /** @var waPayment $plugin */
                $plugin = $m['__instance'];
                $plugin_info = $m['__plugin_info'];
                $m['icon'] = $plugin_info['icon'];

                $allowed_currencies = $plugin->allowedCurrency();
                if ($allowed_currencies !== true) {
                    $allowed_currencies = (array)$allowed_currencies;
                    if (!array_intersect($allowed_currencies, array_keys($currencies))) {
                        $format = _w('Payment procedure cannot be processed because required currency %s is not defined in your store settings.');
                        $m['error'] = sprintf($format, implode(', ', $allowed_currencies));
                    }
                }
            } catch (waException $ex) {
                waLog::log($ex->getMessage(), 'shop/checkout.error.log');
                unset($methods[$key]);
            }
        }
        unset($m);
        return $methods;
    }

    // Overridden in unit tests
    public function getFrontendCurrency()
    {
        return $this->getShopConfig()->getCurrency(false);
    }

    // Overridden in unit tests
    public function getCurrencies()
    {
        return $this->getShopConfig()->getCurrencies();
    }

    protected function getShopConfig()
    {
        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();
        return $config;
    }

    // Overridden in unit tests
    protected function getPaymentMethods($selected_shipping_plugin_id = null, $customer_type = self::CUSTOMER_TYPE_PERSON_AND_COMPANY, $shipping_type = null)
    {
        if ($customer_type == self::CUSTOMER_TYPE_PERSON_AND_COMPANY) {
            $customer_type = '';
        }

        // Payment plugins can be enabled or disabled based on selected shipping option
        $options = [
            'customer_type'                => $customer_type,
        ];
        if ($selected_shipping_plugin_id) {
            $options[shopPluginModel::TYPE_SHIPPING] = $selected_shipping_plugin_id;
        }
        if (!empty($shipping_type)) {
            $options['shipping_type'] = $shipping_type;
        }

        // Payment plugins can be enabled or disabled on current storefront
        $payment_ids = waRequest::param('payment_id');
        if ($payment_ids && is_array($payment_ids)) {
            $options['id'] = $payment_ids;
        }

        // Fetch plugin data from DB
        $plugin_model = new shopPluginModel();
        $methods = $plugin_model->listPlugins(shopPluginModel::TYPE_PAYMENT, $options);

        foreach ($methods as $key => &$m) {
            // Some plugins are disabled
            if (empty($m['available'])) {
                unset($methods[$key]);
                continue;
            }

            try {
                // Instantiate and fetch plugin info from plugin config files
                $m['__instance'] = shopPayment::getPlugin($m['plugin'], $m['id']);
                $m['__plugin_info'] = $m['__instance']->info($m['plugin']);
            } catch (waException $ex) {
                waLog::log($ex->getMessage(), 'shop/checkout.error.log');
                unset($methods[$key]);
                continue;
            }
        }
        unset($m);

        return $methods;
    }


    /**
     * @param array $variant as returned by shopCheckoutShippingStep::prepareShippingVariant()
     * @return waShipping
     */
    public function getShippingPluginByRate($variant)
    {
        list($shop_plugin_id, $internal_variant_id) = explode('.', $variant['variant_id'], 2) + [1 => ''];
        $plugin = shopShipping::getPlugin($variant['plugin'], $shop_plugin_id);
        if (!$plugin || !$plugin instanceof waShipping) {
            return null;
        }
        return $plugin;
    }

    /**
     * Return settings of fields used in Auth step (before shipping).
     *
     * @return array field_id => array of field settings used by $this->formatContactFields()
     */
    public function getAuthFields()
    {
        $populate = function ($type, $config_fields) {
            $result = [];
            foreach ($config_fields as $fld_id => $fld_cfg) {
                if (empty($fld_cfg['used'])) {
                    continue;
                }
                $field = waContactFields::get($fld_id, $type);
                if (!$field) {
                    continue;
                }
                $field_data = $field->getInfo();
                $field_data['required'] = $fld_cfg['required'];
                $field_data['width'] = $fld_cfg['width'];
                $field_data['php_class'] = get_class($field);
                $result[$fld_id] = $field_data;
            }
            return $result;
        };

        $result = [];
        $type = $this['customer']['type'];
        if ($type == shopCheckoutConfig::CUSTOMER_TYPE_PERSON || $type == shopCheckoutConfig::CUSTOMER_TYPE_PERSON_AND_COMPANY) {
            $result[shopCheckoutConfig::CUSTOMER_TYPE_PERSON] = $populate(shopCheckoutConfig::CUSTOMER_TYPE_PERSON, $this['customer']['fields_person']);
        }
        if ($type == shopCheckoutConfig::CUSTOMER_TYPE_COMPANY || $type == shopCheckoutConfig::CUSTOMER_TYPE_PERSON_AND_COMPANY) {
            $result[shopCheckoutConfig::CUSTOMER_TYPE_COMPANY] = $populate(shopCheckoutConfig::CUSTOMER_TYPE_COMPANY, $this['customer']['fields_company']);
        }

        return $result;
    }

    /**
     * Return settings of address fields used in Details step (after shipping variant is selected).
     *
     * @param array $plugin_required_address_fields as returned by waShipping->requestedAddressFieldsForService()
     * @return array field_id => array of field settings used by $this->formatContactFields()
     */
    public function getAddressFields($plugin_required_address_fields)
    {
        $address = waContactFields::get('address', 'person');
        if (!$address || !$address instanceof waContactAddressField) {
            $fields = [];
        } else {
            $fields = $address->getParameter('fields');
        }

        // Address subfield settings for current storefront
        $storefront_address_fields = $this['shipping']['address_fields'];

        $result = [];
        foreach ($fields as $field_id => $field) {
            // Show address field if either plugin asked for it or checkout settings asked for it
            $used = isset($plugin_required_address_fields[$field_id]) || !empty($storefront_address_fields[$field_id]['used']);
            if ($used) {
                $field_data = $field->getInfo();
                $field_data['required'] = !empty($plugin_required_address_fields[$field_id]['required']) || !empty($storefront_address_fields[$field_id]['required']);
                $field_data['width'] = ifset($storefront_address_fields, $field_id, 'width', self::FIELD_WIDTH_MEDIUM);
                $field_data['php_class'] = get_class($field);
                $result[$field_id] = $field_data;
            }
        }

        // Plugin requires address field that is not enabled in contact address field settings.
        // This is exceptional and should not happen. Show it as a simple input.
        foreach (array_diff_key($plugin_required_address_fields, $result) as $field_id => $field_data) {
            if (!empty($field_data['required'])) {
                $result[$field_id] = [
                    'id'           => $field_id,
                    'name'         => $field_id,
                    'multi'        => false,
                    'type'         => 'String',
                    'unique'       => false,
                    'required'     => true,
                    'input_height' => 1,
                    'width'        => self::FIELD_WIDTH_MEDIUM,
                    'php_class'    => 'waContactStringField',
                ];
            }
        }

        return $result;
    }

    /**
     * @param array $fields as returned by getAuthFields() in one of modes, or getAddressFields()
     * @param array $input_values field_id => POST from browser
     * @param array $base_values field_id => values previously saved in waContact
     * @return array field_id => field info array suitable for frontend/order/form/auth.html and details.html templates
     */
    public function formatContactFields($fields, $input_values = [], $base_values = [])
    {
        $form_fields = [];
        foreach ($fields as $field_id => $field_info) {
            // $type defines type of a field - input, select, checkbox, etc.
            // $content_type determines type of validation in input: date, url, email, phone, etc.
            $type = 'input';
            $content_type = null;

            // This is a list of options for certain types: select and radio.
            $options = null;

            // $base_value is from waContact saved in DB. It is considered correct and is nut subject to validation.
            // $value is from POST supplied by customer. It is subject to validation.
            // If $value is invalid, it gets reverted to $base_value.
            $base_value = ifset($base_values, $field_id, '');
            $value = ifset($input_values, $field_id, $base_value);

            switch ($field_info['type']) {
                case 'Name':
                case 'NameSubfield':
                case 'SocialNetwork':
                case 'String':
                    break; // nothing to do

                // Inputs with validation
                case 'Number':
                case 'Email':
                case 'Phone':
                case 'Date':
                case 'Url':
                    $content_type = strtolower($field_info['type']);
                    break;
                case 'Birthday':
                    $content_type = 'date';
                    break;

                // Options-based (note that many of them return 'Select' type, but still have different class names)
                case 'Select':
                case 'Locale':
                case 'Timezone':
                case 'Checklist':
                    switch ($field_info['php_class']) {
                        case 'waContactSelectField':
                        case 'waContactLocaleField':
                        case 'waContactTimezoneField':
                            $type = 'select';
                            break;
                        case 'waContactRadioSelectField':
                            $type = 'radio';
                            break;
                        case 'waContactBranchField':
                        case 'waContactChecklistField':
                        case 'waContactCategoriesField':
                        default:
                            continue 3; // not supported
                    }

                    // Convert options to array for json/template
                    $options = [];
                    foreach ($field_info['options'] as $k => $v) {
                        $options[] = [
                            'label' => $v,
                            'value' => $k,
                        ];
                    }

                    // Make sure user-selected value is allowed
                    if ($field_info['php_class'] != 'waContactChecklistField') {
                        if (!isset($field_info['options'][$value])) {
                            $value = $base_value;
                        }
                    }

                    break;

                // This one is a special case.
                // Sometimes renders as select, sometimes as input
                // depending on value of another field
                case 'Conditional':
                    continue 2; // Not implemented, planned
                    break;

                case 'Text':
                    $type = 'textarea';
                    break;
                case 'Checkbox':
                    $type = 'checkbox';
                    $value = (string)(int)(!!$value);
                    break;
                case 'Hidden':
                    $type = 'hidden';
                    $value = $base_value;
                    break;

                // Not supported by frontend checkout
                case 'Country':
                case 'Region':
                case 'Address':
                case 'Composite':
                case 'Password':
                default:
                    if ($field_info['php_class'] == 'waContactStringField') {
                        // This is a simple text wield with fancy type
                        // passed in options[type]. We can render that.
                        break;
                    }
                    continue 2;
            }

            $form_fields[$field_id] = [
                'id'           => $field_id,
                'type'         => $type,
                'content_type' => $content_type,
                'options'      => $options,
                'label'        => $field_info['name'],

                'required' => !!ifempty($field_info, 'required', false),
                'width'    => ifempty($field_info, 'width', 'average'),

                'name'  => 'auth[data]['.$field_id.']',
                'value' => $value,

                'affects'    => [],
                'depends_on' => null,
            ];

        }
        return $form_fields;
    }

    public static function getLogoUrl($name = null)
    {
        return wa()->getDataUrl($name, true, shopCheckoutConfig::DESIGN_LOGOS_DIR);
    }

    public static function getLogoPath($name = null)
    {
        return wa()->getDataPath($name, true, shopCheckoutConfig::DESIGN_LOGOS_DIR);
    }


    //
    // Ensure consistency
    //

    protected function ensureConsistency()
    {
        $this->ensureDesignLogoConsistency();
        $this->ensureScheduleConsistency();
        $this->ensureOrderFixedDeliveryAreaConsistency();
        $this->ensureOrderLocationsListConsistency();
        $this->ensureCustomerFieldsConsistency();
        $this->ensureShippingAddressFieldsConsistency();
        $this->ensureOrderWithoutAuthConsistency();
    }

    protected function ensureDesignLogoConsistency()
    {
        $logo = ifset($this->config, 'design', 'logo', null);
        if ($logo) {
            $logo_path = self::getLogoPath($logo);
            if (!file_exists($logo_path)) {
                $this->unsetKey('design', 'logo');
            }
        }
    }

    protected function ensureScheduleConsistency()
    {
        if (isset($this->config['schedule']['mode']) && $this->config['schedule']['mode'] == self::SCHEDULE_MODE_CUSTOM) {
            $storefront_schedule = $this->config['schedule'];
        } else {
            $storefront_schedule = null;
        }

        /** @var shopConfig $shop_config */
        $shop_config = wa('shop', 1)->getConfig();
        $storefront_schedule = $shop_config->getSchedule($storefront_schedule);
        $this->config['schedule'] = $storefront_schedule;
    }

    protected function ensureOrderFixedDeliveryAreaConsistency()
    {
        $countries = $this->getCountries();
        $fixed_delivery_area = $this->getValue('order', 'fixed_delivery_area');

        $invalid_country = !isset($fixed_delivery_area['country']) || !is_string($fixed_delivery_area['country']) || !isset($countries[$fixed_delivery_area['country']]);
        if ($invalid_country) {
            $fixed_delivery_area['country'] = null;
        }

        $invalid_region = !$fixed_delivery_area['country'] || !isset($fixed_delivery_area['region']) || !is_string($fixed_delivery_area['region']);
        $region = trim(ifset($fixed_delivery_area, 'region', null));
        if ($invalid_region || empty($region)) {
            $fixed_delivery_area['region'] = null;
        }

        if (!isset($fixed_delivery_area['city']) || !is_scalar($fixed_delivery_area['city']) || empty(trim(ifset($fixed_delivery_area, 'city', null)))) {
            $fixed_delivery_area['city'] = null;
        }

        $this->setValue('order', 'fixed_delivery_area', $fixed_delivery_area);
    }

    protected function ensureOrderLocationsListConsistency()
    {
        $countries = $this->getCountries();
        $locations = $this->getValue('order', 'locations_list');

        foreach ($locations as $i => &$location) {
            if (!isset($location['name']) || !is_scalar($location['name']) || empty($location['name'])) {
                unset($location[$i]);
                continue;
            }

            $location['enabled'] = ifset($location, 'enabled', false) ? true : false;
            $location['default'] = ifset($location, 'default', false) ? true : false;

            $invalid_country = !isset($location['country']) || !is_string($location['country']) || !isset($countries[$location['country']]);
            if ($invalid_country) {
                $location['country'] = null;
            }

            $invalid_region = !$location['country'] || !isset($location['region']) || !is_string($location['region']);
            if ($invalid_region || empty(trim(ifset($location, 'region', null)))) {
                $location['region'] = null;
            }

            if (!$location['region'] || !isset($location['city']) || !is_scalar($location['city']) || empty(trim(ifset($location, 'city', null)))) {
                $location['city'] = null;
            }
        }
        unset($location);

        $this->setValue('order', 'locations_list', $locations);
    }

    protected function ensureCustomerFieldsConsistency()
    {
        $person_fields = $this->getContactFields(self::CUSTOMER_TYPE_PERSON);
        $company_fields = $this->getContactFields(self::CUSTOMER_TYPE_COMPANY);

        // Remove the address field from the contact field, because it is filled separately
        unset($person_fields['address'], $company_fields['address']);

        $person_config_fields = $this->getValue('customer', 'fields_person');
        $company_config_fields = $this->getValue('customer', 'fields_company');

        // PERSON
        foreach ($person_fields as $field => $params) {
            $person_field_params = ifset($person_config_fields, $field, []);
            $person_config_fields[$field] = $this->prepareFieldParams($person_field_params);
        }

        // Remove unuse person fields
        foreach ($person_config_fields as $field => $params) {
            if (!isset($person_fields[$field])) {
                unset($person_config_fields[$field]);
            }
        }

        // COMPANY
        foreach ($company_fields as $field => $params) {
            $company_field_params = ifset($company_config_fields, $field, []);
            $company_config_fields[$field] = $this->prepareFieldParams($company_field_params);
        }

        // Remove unuse contact fields
        foreach ($company_config_fields as $field => $params) {
            if (!isset($company_fields[$field])) {
                unset($company_config_fields[$field]);
            }
        }

        $this->setValue('customer', 'fields_person', $person_config_fields);
        $this->setValue('customer', 'fields_company', $company_config_fields);
    }

    protected function ensureShippingAddressFieldsConsistency()
    {
        // Get all contact address fields
        $address_fields = $this->getContactAddressFields();
        unset($address_fields['country'], $address_fields['region'], $address_fields['city']);
        // Get shipping address fields
        $shipping_address_fields = $this->getValue('shipping', 'address_fields');

        foreach ($address_fields as $field => $params) {
            $shipping_address_field_params = ifempty($shipping_address_fields[$field], []);
            $shipping_address_fields[$field] = $this->prepareFieldParams($shipping_address_field_params);
        }

        // Remove unuse address fields
        foreach ($shipping_address_fields as $field => $params) {
            if (!isset($address_fields[$field])) {
                unset($shipping_address_fields[$field]);
            }
        }

        $this->setValue('shipping', 'address_fields', $shipping_address_fields);

        if (!empty($this->config['shipping']['ask_zip']) && isset($this->config['shipping']['address_fields']['zip'])) {
            $this->config['shipping']['address_fields']['zip']['used'] = true;
            $this->config['shipping']['address_fields']['zip']['required'] = true;
        }
    }

    protected function ensureOrderWithoutAuthConsistency()
    {
        $order_without_auth = $this->getValue('confirmation', 'order_without_auth');
        if ($order_without_auth == self::ORDER_WITHOUT_AUTH_CONFIRM) {
            $this->setValue('confirmation', 'auth_with_code', true);
        }
    }

    protected function prepareFieldParams(array $field_params)
    {
        $field_params['used'] = ifset($field_params, 'used', false) ? true : false;
        $field_params['required'] = ifset($field_params, 'required', false) ? true : false;

        $field_width_variants = $this->getFieldWidthVariants();
        $default_field_width = self::FIELD_WIDTH_MEDIUM;
        if (!isset($field_params['width']) || !array_key_exists($field_params['width'], $field_width_variants)) {
            $field_params['width'] = $default_field_width;
        }

        return $field_params;
    }

    // Work with settings

    protected function getBoolValue($element, $key)
    {
        $value = isset($this->config[$element][$key]) ? !!$this->config[$element][$key] : $this->getDefaultValue($element, $key, self::SETTING_TYPE_BOOL);
        return (bool)$value;
    }

    protected function setBoolValue($element, $key, $value)
    {
        $value = (bool)$value;
        $default = $this->getDefaultValue($element, $key, self::SETTING_TYPE_BOOL);
        if ($value !== $default) {
            $this->config[$element][$key] = $value;
        } else {
            $this->unsetKey($element, $key);
        }
    }

    protected function getArrayValue($element, $key)
    {
        $value_exists = isset($this->config[$element][$key]) && is_array($this->config[$element][$key]);
        $value = $value_exists ? $this->config[$element][$key] : [];

        if (!$value) {
            $default_value = $this->getDefaultValue($element, $key, self::SETTING_TYPE_ARRAY);
            $value = $default_value;
        }
        return $value;
    }

    protected function setArrayValue($element, $key, $value)
    {
        $value = is_array($value) ? $value : [];
        if (!empty($value)) {
            $this->config[$element][$key] = $value;
        } else {
            $this->unsetKey($element, $key);
        }
    }

    protected function getScalarValue($element, $key)
    {
        $value_exists = isset($this->config[$element][$key]) && is_scalar($this->config[$element][$key]);
        $value = $value_exists ? (string)$this->config[$element][$key] : null;
        if (!$value) {
            $default_value = $this->getDefaultValue($element, $key, 'string');
            $value = $default_value;
        }
        return trim($value);
    }

    protected function setScalarValue($element, $key, $value)
    {
        $value = is_scalar($value) ? (string)$value : '';
        $value = trim($value);
        $this->config[$element][$key] = $value;
    }

    protected function getVariantValue($element, $key)
    {
        $variants = $this->getVariantsByKey($element, $key);
        $val = isset($this->config[$element][$key]) ? $this->config[$element][$key] : null;
        if (!isset($variants[$val])) {
            $val = $this->getDefaultValue($element, $key, 'string');
        }
        return $val;
    }

    protected function setVariantValue($element, $key, $value)
    {
        $variants = $this->getVariantsByKey($element, $key);
        if (array_key_exists($value, $variants)) {
            $this->config[$element][$key] = $value;
        } else {
            $this->unsetKey($element, $key);
        }
    }

    protected function getDefaultValue($element, $key, $type)
    {
        $val = null;
        settype($val, $type);
        if (isset(self::$static_cache['default'][$element][$key])) {
            $val = self::$static_cache['default'][$element][$key];
        }

        return $val;
    }

    protected function getVariantsByKey($element, $key)
    {
        $k_parts = explode('_', $key);
        foreach ($k_parts as &$k_part) {
            $k_part = ucfirst($k_part);
        }
        unset($k_part);
        $k_parts = join('', $k_parts);
        $method_name = 'get'.ucfirst($element).$k_parts.'Variants';
        if (method_exists($this, $method_name)) {
            return $this->{$method_name}();
        } else {
            return [];
        }
    }

    protected function unsetKey($element, $key)
    {
        if (isset($this->config[$element][$key])) {
            unset($this->config[$element][$key]);
        }
    }

    /**
     * @param $type 'set' | 'get'
     * @param string $element
     * @param null|string $key
     * @return array
     */
    protected function getMethodByKey($type, $element, $key = null)
    {
        static $methods;

        if (empty($methods[$element])) {
            $map = $this->getSettingsMap();
            $settings = ifset($map[$element], []);
            $methods = [];
            foreach ($settings as $s => $s_type) {
                $get_method = 'get'.ucfirst($s_type).'Value';
                if (method_exists($this, $get_method)) {
                    $methods[$element][$s]['get'] = $get_method;
                }
                $set_method = 'set'.ucfirst($s_type).'Value';
                if (method_exists($this, $set_method)) {
                    $methods[$element][$s]['set'] = $set_method;
                }
            }
        }

        $type = $type === 'get' ? 'get' : 'set';
        if ($key === null) {
            return waUtils::getFieldValues(ifempty($methods[$element], []), $type, true);
        } else {
            return isset($methods[$element][$key][$type]) ? $methods[$element][$key][$type] : null;
        }
    }

    protected function getSettingsMap()
    {
        return [
            'design'       => [
                'custom'            => self::SETTING_TYPE_BOOL,
                'logo'              => self::SETTING_TYPE_SCALAR,
                'business_scope'    => self::SETTING_TYPE_SCALAR,
                'phone'             => self::SETTING_TYPE_SCALAR,
                'phone_hint'        => self::SETTING_TYPE_SCALAR,
                'address'           => self::SETTING_TYPE_SCALAR,
                'working_hours'     => self::SETTING_TYPE_SCALAR,
                'order_background'  => self::SETTING_TYPE_SCALAR,
                'layout_background' => self::SETTING_TYPE_SCALAR,
                'custom_css'        => self::SETTING_TYPE_SCALAR,
            ],
            'cart'         => [
                'block_name'       => self::SETTING_TYPE_SCALAR,
                'empty_text'       => self::SETTING_TYPE_SCALAR,
                'article_change'   => self::SETTING_TYPE_BOOL,
                'discount_item'    => self::SETTING_TYPE_VARIANT,
                'discount_general' => self::SETTING_TYPE_VARIANT,
            ],
            'schedule'     => [
                'mode'            => self::SETTING_TYPE_VARIANT,
                'timezone'        => self::SETTING_TYPE_SCALAR,
                'processing_time' => self::SETTING_TYPE_SCALAR,
                'week'            => self::SETTING_TYPE_ARRAY,
                'extra_workdays'  => self::SETTING_TYPE_ARRAY,
                'extra_weekends'  => self::SETTING_TYPE_ARRAY,
            ],
            'order'        => [
                'block_name'           => self::SETTING_TYPE_SCALAR,
                'mode'                 => self::SETTING_TYPE_VARIANT,
                'fixed_delivery_area'  => self::SETTING_TYPE_ARRAY,
                'show_pickuppoint_map' => self::SETTING_TYPE_VARIANT,
                'locations_list'       => self::SETTING_TYPE_ARRAY,
            ],
            'customer'     => [
                'block_name'             => self::SETTING_TYPE_SCALAR,
                'offer_login'            => self::SETTING_TYPE_SCALAR,
                'offer_logout'           => self::SETTING_TYPE_SCALAR,
                'type'                   => self::SETTING_TYPE_VARIANT,
                'fields_person'          => self::SETTING_TYPE_ARRAY,
                'fields_company'         => self::SETTING_TYPE_ARRAY,
                'person_mode_name'       => self::SETTING_TYPE_SCALAR,
                'company_mode_name'      => self::SETTING_TYPE_SCALAR,
                'company_hint'           => self::SETTING_TYPE_SCALAR,
                'company_terms'          => self::SETTING_TYPE_SCALAR,
                'service_agreement'      => self::SETTING_TYPE_VARIANT,
                'service_agreement_hint' => self::SETTING_TYPE_SCALAR,
            ],
            'shipping'     => [
                'used'                   => self::SETTING_TYPE_BOOL,
                'block_name'             => self::SETTING_TYPE_SCALAR,
                'ask_zip'                => self::SETTING_TYPE_BOOL,
                'courier_name'           => self::SETTING_TYPE_SCALAR,
                'pickuppoint_name'       => self::SETTING_TYPE_SCALAR,
                'post_name'              => self::SETTING_TYPE_SCALAR,
                'address_fields'         => self::SETTING_TYPE_ARRAY,
                'service_agreement'      => self::SETTING_TYPE_BOOL,
                'service_agreement_hint' => self::SETTING_TYPE_SCALAR,
                'plugin_timeout'         => self::SETTING_TYPE_SCALAR,
            ],
            'payment'      => [
                'used'       => self::SETTING_TYPE_BOOL,
                'block_name' => self::SETTING_TYPE_SCALAR,
            ],
            'confirmation' => [
                'order_comment'      => self::SETTING_TYPE_BOOL,
                'terms'              => self::SETTING_TYPE_BOOL,
                'terms_text'         => self::SETTING_TYPE_SCALAR,
                'order_without_auth' => self::SETTING_TYPE_VARIANT,
                'auth_with_code'     => self::SETTING_TYPE_BOOL,
                'recode_timeout'     => self::SETTING_TYPE_SCALAR,
                'thankyou_header'    => self::SETTING_TYPE_SCALAR,
                'thankyou_content'   => self::SETTING_TYPE_SCALAR,
            ],
        ];
    }

    //

    protected function getConfigPath()
    {
        return wa()->getConfig()->getConfigPath('checkout2.php', true, 'shop');
    }

    private function validateStorefrontId()
    {
        $storefronts = shopHelper::getStorefronts(true);
        foreach ($storefronts as $storefront) {
            if (!empty($storefront['route']['checkout_storefront_id']) && $storefront['route']['checkout_storefront_id'] == $this->storefront) {
                return true;
            }
        }
        return false;
    }

    private function loadConfig()
    {
        if ($this->config) {
            return;
        }

        $path = $this->getConfigPath();
        if (file_exists($path)) {
            $all_settings = include($path);
        }

        if (!empty($all_settings[$this->storefront])) {
            $storefront_settings = $all_settings[$this->storefront];
        } else {
            $storefront_settings = self::$static_cache['default'];
            // Load contact fields from old checkout here
            list($contact_field, $address_fields) = $this->getOldCheckoutFields();

            foreach ($contact_field as $field_id => $field) {
                $field = [
                    'used'     => true,
                    'required' => !empty($field['required']),
                ];
                $storefront_settings['customer']['fields_person'][$field_id] = $field;
                $storefront_settings['customer']['fields_company'][$field_id] = $field;
            }

            foreach ($address_fields as $field_id => $field) {
                $storefront_settings['shipping']['address_fields'][$field_id] = [
                    'used'     => true,
                    'required' => !empty($field['required']),
                ];
            }

            $this->is_default = true;
        }
        $this->config = $storefront_settings;
    }

    private function loadDefaultConfig()
    {
        $this->loadElement('default');
        return ifempty(self::$static_cache['default'], []);
    }

    private function loadElement($element)
    {
        if (!isset(self::$static_cache[$element])) {
            $path = wa()->getConfig()->getConfigPath("checkout2/{$element}.php", false, 'shop');
            if (file_exists($path)) {
                self::$static_cache[$element] = include($path);
            }
        }
    }

    private function getOldCheckoutFields()
    {
        $old_checkout_steps = $this->getShopConfig()->getCheckoutSettings();

        $contact_fields = ifempty($old_checkout_steps, 'contactinfo', 'fields', []);

        $shipping_fields = ifempty($old_checkout_steps, 'contactinfo', 'fields', 'address.shipping', 'fields', []);

        unset($contact_fields['address'], $contact_fields['address.shipping']);

        return [
            $contact_fields,
            $shipping_fields,
        ];
    }

    // Helpers

    protected function getCountries()
    {
        if (empty(self::$static_cache['countries'])) {
            $cm = new waCountryModel();
            self::$static_cache['countries'] = $cm->all();
        }
        return self::$static_cache['countries'];
    }

    protected function getContactFields($contact_type)
    {
        if (empty($this->contact_fields["contact_{$contact_type}_fields"])) {
            $person_fields = waContactFields::getAll($contact_type);
            $fields = [];
            foreach ($person_fields as $field) {
                /** @var waContactField $field */
                $fields[$field->getId()] = $field;
            }
            $this->contact_fields["contact_{$contact_type}_fields"] = $fields;
        }

        return $this->contact_fields["contact_{$contact_type}_fields"];
    }

    protected function getContactAddressFields()
    {
        if (empty($this->contact_address_fields)) {
            $address_field = waContactFields::get('address');
            $fields = [];
            if ($address_field instanceof waContactAddressField && is_array($address_field->getFields())) {
                foreach ($address_field->getParameter('fields') as $sub_field) {
                    /** @var waContactField $sub_field */
                    $fields[$sub_field->getId()] = $sub_field;
                }
            }
            unset($fields['lng'], $fields['lat']);
            $this->contact_address_fields = $fields;
        }

        return $this->contact_address_fields;
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->config[] = $value;
        } else {
            $this->config[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->config[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->config[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->config[$offset]) ? $this->config[$offset] : null;
    }
}
