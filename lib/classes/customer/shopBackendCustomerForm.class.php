<?php

/**
 * Class shopBackendCustomerForm - wrapper around waContactForm
 *
 * shopBackendCustomerForm implicitly implement waContactForm interface
 *
 * @property $post
 *
 * @method setValue($field_id, $value = null)
 * @method errors($field_id = '', $error_text = null)
 * @method function isValid($contact = null)
 * @method fields($field_id = null)
 * @method opt($name = null, $default = null)
 *
 * @see waContactForm
 */

class shopBackendCustomerForm {

    /**
     * @var array
     */
    protected $cache = array();

    protected static $person_main_field_ids = array(
        'firstname',
        'middlename',
        'lastname',
        'title',
        'email',
        'phone',
        'jobtitle',
        'company'
    );

    protected static $company_main_field_ids = array(
        'company',
        'email',
        'phone'
    );

    protected static $address_main_subfield_ids = array(
        'street',
        'city',
        'region',
        'zip',
        'country'
    );

    /**
     * @var array
     */
    protected $options;

    /**
     * Option that can't be re-set after first time set
     * <option_name> => bool
     * @var bool[]
     */
    protected $is_const = array();

    public function __construct($options = array())
    {
        $options = is_array($options) ? $options : array();
        $options['namespace'] = isset($options['namespace']) ? $options['namespace'] : 'customer';

        $options = array_merge($options, array(
            'contact_type'    => shopCustomer::TYPE_PERSON,
            'contact'         => null,
            'storefront'      => null,
            'address_display_type' => 'all',                        // 'first', 'all', 'none'
        ));

        $this->options = $options;
    }

    /**
     * Set type of contact for which form will be presented
     * @param string $contact_type const shopCustomer::TYPE_*
     * @param bool $const
     * @return shopBackendCustomerForm
     */
    public function setContactType($contact_type, $const = false)
    {
        if (!empty($this->is_const['contact_type'])) {
            return $this;
        }

        // nothing to change, set same contact type
        if (isset($this->options['contact_type']) && $this->options['contact_type'] === $contact_type) {
            return $this;
        }

        $this->options['contact_type'] = $contact_type === shopCustomer::TYPE_COMPANY ? shopCustomer::TYPE_COMPANY : shopCustomer::TYPE_PERSON;

        // new contact type, new waContactForm - reset cache
        $this->cache['form'] = null;

        if ($const) {
            $this->is_const['contact_type'] = true;
        }

        return $this;
    }

    /**
     * @return string shopCustomer::TYPE_*
     */
    public function getContactType()
    {
        return $this->options['contact_type'] === shopCustomer::TYPE_COMPANY ? shopCustomer::TYPE_COMPANY : shopCustomer::TYPE_PERSON;
    }

    /**
     * Show all shipping and billing addresses OR only first shipping and billing addresses OR none
     * @param string $type 'all', 'first', 'none'
     */
    public function setAddressDisplayType($type)
    {
        $this->options['address_display_type'] = $type;
    }

    /**
     * What storefront will be used to form list of fields
     * If storefront is empty (or not scalar) OR not existed OR not compatible with contact type
     *  then list of fields will formed by 'union' logic
     *
     * @param string $url
     * @param bool $const
     * @return shopBackendCustomerForm
     */
    public function setStorefront($url, $const = false)
    {
        if (!empty($this->is_const['storefront'])) {
            return $this;
        }

        $url = is_scalar($url) ? trim($url) : '';
        if (strlen($url) > 0) {
            $this->options['storefront'] = $url;
        } else {
            $this->options['storefront'] = null;
        }

        // nothing to change, set same storefront
        if (isset($this->options['storefront']) && $this->options['storefront'] === $url) {
            return $this;
        }

        // new storefront, new waContactForm - reset cache
        $this->cache['form'] = null;

        if ($const) {
            $this->is_const['storefront'] = true;
        }

        return $this;
    }

    /**
     * Get current storefront info if that info is available for current contact type
     * @return array|null - in result array-info also will be presented 'checkout_config' key (shopCheckoutConfig|null)
     */
    protected function getCurrentStorefrontInfo()
    {
        if (!$this->options['storefront']) {
            return null;
        }

        $contact_type = $this->options['contact_type'];

        $list = $this->newStorefrontList();
        $list->addFilter(array(
            'contact_type' => $contact_type,
            'url'          => $this->options['storefront']
        ));

        $storefront = $list->fetchFirst(array('checkout_config', 'contact_type'));

        // storefront by these constraints not found
        if (!$storefront) {
            return null;
        }

        return $storefront;
    }

    /**
     * Contact whose data must be pre-filled in contact form.
     * @param int|waContact $contact
     * @return shopBackendCustomerForm
     * @throws waException
     */
    public function setContact($contact)
    {
        if (wa_is_int($contact) && $contact > 0) {
            $contact = new waContact($contact);
        }
        if ($contact instanceof waContact) {
            if (!$contact->exists()) {
                $contact = null;
            }
        } elseif ($contact !== null) {
            $contact = null;
        }

        // nothing to change, set same contact (check by link address, not deep equal)
        if (isset($this->options['contact']) && $this->options['contact'] === $contact) {
            return $this;
        }

        $this->options['contact'] = $contact;
        if ($contact instanceof waContact) {
            $this->options['contact_type'] = $contact['is_company'] ? shopCustomer::TYPE_COMPANY : shopCustomer::TYPE_PERSON;
        }

        // new contact type, new waContactForm - reset cache
        $this->cache['form'] = null;

        return $this;
    }

    /**
     *
     * @return shopStorefrontList
     */
    protected function newStorefrontList()
    {
        return new shopStorefrontList();
    }

    /**
     * @param $namespace
     * @return $this
     */
    public function setNamespace($namespace)
    {
        $namespace = is_scalar($namespace) ? (string)$namespace : '';
        $namespace = strlen($namespace) > 0 ? $namespace : null;
        $this->options['namespace'] = $namespace;

        // new namespace, new waContactForm - reset cache
        $this->cache['form'] = null;

        return $this;
    }

    /**
     * @return mixed|null
     */
    public function getNamespace()
    {
        return ifset($this->options['namespace']);
    }

    /**
     * Get field list for customer form
     * In this list also included 'address.shipping' and 'address.billing' (if needed)
     *
     * @return array $result
     *   - string $result['type'] - what type of logic worked for forming of field list
     *   - array $result['field_list'] - field list itself
     * @throws waException
     */
    protected function getFieldList()
    {
        $contact_type = $this->options['contact_type'];

        $res = $this->getStorefrontFieldList();

        if ($res['status']) {
            $result = array(
                'type'       => 'storefront',
                'field_list' => $res['field_list']
            );
        } else {
            $order_editor_config = $this->getOrderEditorConfig();

            if ($order_editor_config['use_custom_config']) {
                $field_list = $order_editor_config->getFieldList($contact_type);

                $field_list['address.shipping']['fields'] = $order_editor_config->getFieldList(shopOrderEditorConfig::FIELDS_TYPE_ADDRESS);

                if (!empty($order_editor_config['billing_address'][$contact_type])) {
                    $field_list['address.billing'] = $field_list['address.shipping'];
                }

                $result = array(
                    'type'       => 'custom_config',
                    'field_list' => $field_list,
                );
            } else {
                $result = array(
                    'type'       => 'union',
                    'field_list' => $this->getUnitedFieldList()
                );
            }
        }

        // Title field -- only for person!
        if ($contact_type !== shopCustomer::TYPE_PERSON) {
            unset($result['field_list']['title']);
        }

        return $result;
    }

    /**
     * Get fields config for customer form
     * @return array
     * @throws waException
     */
    public function getFieldsConfig()
    {
        $result = $this->getFieldList();

        $fields_config = $result['field_list'];

        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();

        $fix_delivery_area = $this->getFixDeliveryArea();

        // set current country as a value
        $current_country_value = $config->getGeneralSettings('country');
        foreach (array('address.shipping', 'address.billing') as $field_id) {
            if (isset($fields_config[$field_id]['fields']['country']) && empty($fields_config[$field_id]['fields']['country']['value'])) {
                $fields_config[$field_id]['fields']['country']['value'] = $current_country_value;
            }

            if (isset($fields_config[$field_id]['fields']) && is_array($fields_config[$field_id]['fields'])) {
                foreach ($fields_config[$field_id]['fields'] as $fld_id => $fld_params) {
                    if (!empty($fix_delivery_area[$fld_id])) {
                        $fields_config[$field_id]['fields'][$fld_id]['value'] = $fix_delivery_area[$fld_id];
                    }
                }
            }
        }

        // for union case all fields are NOT required, otherwise don't touch, leave as in fields config
        // for storefront and custom_config cases the 'required' flag is indicated in the config
        if ($result['type'] === 'union') {

            foreach ($fields_config as &$field) {
                $field['required'] = false;
            }
            unset($field);

            foreach ($fields_config['address.shipping']['fields'] as &$subfield) {
                $subfield['required'] = false;
            }
            unset($subfield);

            if (isset($fields_config['address.billing'])) {
                foreach ($fields_config['address.billing']['fields'] as &$subfield) {
                    $subfield['required'] = false;
                }
                unset($subfield);
            }
        }

        return $fields_config;
    }

    /**
     * Get list of fields that is pretty match UNION of fields for all storefronts
     * But there are some nuances:
     *   1. There is specially not presented 'address' field
     *   2. There is always presented 'address.shipping' field with ALL sub-fields
     *   3. There is always presented 'address.billing' field with ALL sub-fields
     * @return array
     */
    protected function getUnitedFieldList()
    {
        $contact_type = $this->options['contact_type'];

        // Init form fields var: prepare fields places for preserving original sorting of fields for this type of contact
        $form_fields = array();

        // Object to work with list of storefronts
        $list = $this->newStorefrontList();

        // will get storefronts only for this contact_type
        $list->addFilter(array('contact_type' => $contact_type));

        // will get storefronts only for new checkout version
        $list->addFilter(function($storefront) {
            $checkout_version = ifset($storefront, 'route', 'checkout_version', false);
            return $checkout_version >= 2;
        }, 'new_checkout_filter');

        // count of storefronts with checkout v2
        $v2_checkout_count = $list->count();

        // REDUCE callback that merge each storefront contact fields into one result array, i.e. implement UNION logic
        $that = $this;
        $merger = function (&$result, $storefront) use($contact_type, $that) {
            /**
             * Checkout config is always shopCheckoutConfig cause we filter by checkout version >= 2
             * @var shopCheckoutConfig $config
             */
            $config = $storefront['checkout_config'];
            $contact_fields = $that->getSettlementFieldsByContactType($config, $contact_type);
            $result = array_merge($result, $contact_fields);
            return $result;
        };

        $form_fields = $list->mapReduce(array('checkout_config'), $merger, $form_fields);

        // for person may need also merge with OLD checkout version contact fields
        if ($contact_type === shopCustomer::TYPE_PERSON) {

            // delete filter by checkout_version >= 2
            $list->deleteFilter('new_checkout_filter');

            $is_v1_checkout = $list->count() - $v2_checkout_count > 0;

            if ($is_v1_checkout) {
                // mix-in OLD checkout fields - they all the same for all OLD checkouts (so, don't need iterate)
                $form_fields = array_merge($form_fields, self::getOldCheckoutFieldList(array(
                    'need_shipping_address' => false,
                    'need_billing_address' => false
                )));
            }
        }

        // For person one of 'firstname', 'middlename', 'lastname' is primary then 'name'
        if ($contact_type === shopCustomer::TYPE_PERSON) {
            foreach (array('firstname', 'middlename', 'lastname') as $alt_name_field_id) {
                if (isset($form_fields[$alt_name_field_id])) {
                    unset($form_fields['name']);
                }
            }
        } else {
            // For company 'company' is primary then 'name'
            if (isset($form_fields['company'])) {
                unset($form_fields['name']);
            }
        }

        // Merge with main fields
        $form_fields = $this->mergeWithMainFields($form_fields, true);


        // address never needed in backend customer form
        unset($form_fields['address']);

        // to ensure address.shipping is first in order than address.billing
        unset($form_fields['address.shipping'], $form_fields['address.billing']);

        // shipping address always needed
        $form_fields['address.shipping'] = array('fields' => $this->getAddressSubfields());

        // billing address always need
        $form_fields['address.billing'] = array('fields' => $this->getAddressSubfields());

        return $form_fields;
    }

    /**
     * If storefront is incorrect or not consistent with 'contact_type' will be return FALSE 'status'
     * @return array $return
     *   - bool $return['status']
     *   - array $return['field_list']
     * @throws waException
     */
    protected function getStorefrontFieldList()
    {
        $storefront = $this->getCurrentStorefrontInfo();

        // storefront by these constraints not found
        if (!$storefront) {
            return array(
                'status'     => false,
                'field_list' => []
            );
        }

        $checkout_version = ifset($storefront, 'route', 'checkout_version', false);
        if ($checkout_version < 2) {
            return array(
                'status'     => true,
                'field_list' => $this->getOldCheckoutFieldList()
            );
        }

        /**
         * @var shopCheckoutConfig $config
         */
        $config = $storefront['checkout_config'];
        $field_list = $this->getSettlementFieldsByContactType($config, $this->options['contact_type']);

        // address must not be in result fields list
        unset($field_list['address']);

        $shipping_subfields = $this->getStorefrontShippingAddressSubfields();
        if ($shipping_subfields) {
            $field_list['address.shipping'] = array('fields' => $shipping_subfields);
        }

        $billing_subfields = $this->getStorefrontBillingAddressSubfields();
        if ($billing_subfields) {
            $field_list['address.billing'] = array('fields' => $billing_subfields);
        }

        return array(
            'status'     => true,
            'field_list' => $field_list
        );
    }

    protected function mergeWithMainFields($fields, $system_order = false)
    {
        $contact_type = $this->options['contact_type'];

        $fields = is_array($fields) ? $fields : array();

        if ($contact_type === shopCustomer::TYPE_PERSON) {
            $alt_name_field_ids = array('firstname' => true, 'middlename' => true, 'lastname' => true);
        } else {
            $alt_name_field_ids = array('company' => true);
        }

        if ($contact_type === shopCustomer::TYPE_PERSON) {
            $main_field_ids = self::$person_main_field_ids;
        } else {
            $main_field_ids = self::$company_main_field_ids;
        }

        foreach ($main_field_ids as $main_field_id) {

            // is alternative "name field" ID
            if (isset($alt_name_field_ids[$main_field_id])) {
                // mix-in alternative "name field(s)", but only when "name" field is not presented
                if (!isset($fields['name'])) {
                    $fields[$main_field_id] = ifset($fields, $main_field_id, array());
                }
                continue;
            }

            $fields[$main_field_id] = ifset($fields, $main_field_id, array());

        }

        // Order: either all fields sort as in system config, or only main fields order as in system config and move to TOP

        $system_field_ids = array_keys(waContactFields::getAll($contact_type));
        if ($system_order) {
            $fields = waUtils::orderKeys($fields, $system_field_ids);
        } else {
            $main_field_ids = array_fill_keys($main_field_ids, true);
            $main_field_ids = waUtils::orderKeys($main_field_ids, $system_field_ids);
            $main_field_ids = array_keys($main_field_ids);
            $fields = waUtils::orderKeys($fields, $main_field_ids);
        }

        return $fields;
    }

    protected function mergeWithAddressMainSubfields($subfields)
    {
        // merge with main subfields
        $main_subfields = $this->getAddressSubfields(self::$address_main_subfield_ids);
        foreach ($main_subfields as $subfield_id => $subfield) {
            $subfields[$subfield_id] = ifset($subfields, $subfield_id, $subfield);
            if (isset($subfields[$subfield_id]['hidden'])) {
                unset($subfields[$subfield_id]['hidden']);
            }
        }
        $subfields = waUtils::orderKeys($subfields, array_keys($main_subfields));
        return $subfields;
    }

    /**
     * Get list of shipping address sub-fields for current storefront
     * If storefront is null (not chosen as a part of state/context) or chosen storefront not available for current contact type
     * then returns result with status false and empty list of sub-fields
     * @return array
     * @throws waException
     */
    protected function getStorefrontShippingAddressSubfields()
    {
        $storefront = $this->getCurrentStorefrontInfo();

        // storefront by these constraints not found
        if (!$storefront) {
            return array();
        }

        $checkout_version = ifset($storefront, 'route', 'checkout_version', false);
        if ($checkout_version < 2) {
            $subfields = $this->getOldCheckoutShippingAddressSubfields();
        } else {
            $subfields = $this->getSettlementShippingAddressSubfields($storefront['checkout_config']);
        }

        $subfields = $this->mergeWithAddressMainSubfields($subfields);

        return $subfields;
    }

    /**
     * Get list of billing address sub-fields for current storefront
     * If
     *   storefront is null (not chosen as a part of state/context) OR
     *   chosen storefront not available for current contact type OR
     *   new checkout
     * then returns result with status false and empty list of sub-fields
     * @return array
     * @throws waException
     */
    protected function getStorefrontBillingAddressSubfields()
    {
        $storefront = $this->getCurrentStorefrontInfo();

        // storefront by these constraints not found
        if (!$storefront) {
            return array();
        }

        $checkout_version = ifset($storefront, 'route', 'checkout_version', false);
        if ($checkout_version >= 2) {
            return array();
        }

        $subfields = $this->getOldCheckoutBillingAddressSubfields();
        $subfields = $this->mergeWithAddressMainSubfields($subfields);

        return $subfields;
    }

    /**
     * Get list of contact fields for old checkout
     * @param array $options = array()
     *   bool 'need_shipping_address' - Default is TRUE
     *   bool 'need_billing_address' - Optional, if missed than billing address include in field list only when checkout settings told so
     * @return array
     */
    protected function getOldCheckoutFieldList($options = array())
    {
        $options = is_array($options) ? $options : array();

        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();

        $settings = $config->getCheckoutSettings();

        if (!isset($settings['contactinfo'])) {
            $settings = $config->getCheckoutSettings(true);
        }

        // just in case - to prevent fatal error in $settings is not array
        $settings = is_array($settings) ? $settings : array();

        $fields_config = ifset($settings['contactinfo']['fields'], array());

        // type consistency: must be always array type
        $fields_config = is_array($fields_config) ? $fields_config : array();

        // address must not be in result fields config
        unset($fields_config['address']);

        // to ensure address.shipping is first in order than address.billing
        unset($fields_config['address.shipping'], $fields_config['address.billing']);

        if (isset($options['need_shipping_address'])) {
            $need_shipping_address = $options['need_shipping_address'];
        } else {
            $need_shipping_address = true;
        }

        if ($need_shipping_address) {
            $subfields = $this->getOldCheckoutShippingAddressSubfields();
            $subfields = $this->mergeWithAddressMainSubfields($subfields);

            $fields_config['address.shipping'] = array('fields' => $subfields);
        }

        if (isset($options['need_billing_address'])) {
            $need_billing_address = $options['need_billing_address'] ? 'required' : null;
        } else {
            $need_billing_address = 'optional';
        }


        if ($need_billing_address === 'required') {
            $subfields = $this->getOldCheckoutBillingAddressSubfields();
            if (!$subfields) {
                $subfields = $this->getAddressSubfields();
            }
        } elseif ($need_billing_address === 'optional') {
            $subfields = $this->getOldCheckoutBillingAddressSubfields();
        } else {
            $subfields = array();
        }

        if ($subfields) {
            $subfields = $this->mergeWithAddressMainSubfields($subfields);
            $fields_config['address.billing'] = array('fields' => $subfields);
        }

        return $fields_config;
    }

    /**
     * Get list of shipping address sub-fields for checkout v1
     * @return array
     */
    protected function getOldCheckoutShippingAddressSubfields()
    {
        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();

        $settings = $config->getCheckoutSettings();
        if (!isset($settings['contactinfo'])) {
            $settings = $config->getCheckoutSettings(true);
        }

        $settings = is_array($settings) ? $settings : array();

        $fields_config = ifset($settings, 'contactinfo', 'fields', 'address.shipping', array());
        $fields_config = is_array($fields_config) ? $fields_config : array();
        $subfields = isset($fields_config['fields']) && is_array($fields_config['fields']) ? $fields_config['fields'] : array();

        if (!$fields_config) {
            $subfields = $this->getAddressSubfields();
        }

        return $subfields;
    }

    /**
     * Get list of billing address sub-fields for checkout v1
     */
    protected function getOldCheckoutBillingAddressSubfields()
    {
        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();

        $settings = $config->getCheckoutSettings();
        if (!isset($settings['contactinfo'])) {
            $settings = $config->getCheckoutSettings(true);
        }

        $settings = is_array($settings) ? $settings : array();

        $fields_config = ifset($settings, 'contactinfo', 'fields', 'address.billing', array());
        $fields_config = is_array($fields_config) ? $fields_config : array();
        $subfields = isset($fields_config['fields']) && is_array($fields_config['fields']) ? $fields_config['fields'] : array();

        return $subfields;
    }

    /**
     * Get subfields of address fields
     * @param null|array $subset what subset of fields needed
     *   If NULL - all subfields
     *   If array - only that subfields, ids of which in this array
     * @return array - array of array indexed by IDs of subfields
     */
    protected function getAddressSubfields($subset = null)
    {
        $subfields = array();
        $address = waContactFields::get('address');
        if ($address instanceof waContactAddressField) {
            $subfields = array_fill_keys(array_keys($address->getFields()), array());
        }
        if (is_array($subset) || is_scalar($subset)) {
            $subfields = waUtils::extractValuesByKeys($subfields, (array)$subset);
        }
        return $subfields;
    }

    /**
     * @param shopCheckoutConfig $settlement
     * @return array
     */
    protected function getSettlementShippingAddressSubfields($settlement)
    {
        $list = $settlement['shipping']['address_fields'];
        $list = is_array($list) ? $list : array();
        $list = array_filter($list, function ($item) {
            return !empty($item['used']);
        });

        $list = array_merge($list, array(
            'country' => array('required' => true),
            'region' => array('required' => true),
            'city' => array('required' => true)
        ));

        // re-order
        $result = array();
        foreach (array('country', 'region', 'city') as $key) {
            $result[$key] = $list[$key];
            unset($list[$key]);
        }
        foreach ($list as $key => $value) {
            $result[$key] = $value;
            unset($list);
        }

        return $result;
    }

    /**
     * @param shopCheckoutConfig $settlement
     * @param string $type
     * @return array
     */
    protected function getSettlementFieldsByContactType($settlement, $type)
    {
        $customer_type = $settlement['customer']['type'];

        $is_for_person = $customer_type === shopCheckoutConfig::CUSTOMER_TYPE_PERSON ||
            $customer_type === shopCheckoutConfig::CUSTOMER_TYPE_PERSON_AND_COMPANY;
        $is_for_company = $customer_type === shopCheckoutConfig::CUSTOMER_TYPE_COMPANY ||
            $customer_type === shopCheckoutConfig::CUSTOMER_TYPE_PERSON_AND_COMPANY;

        if ($type === shopCustomer::TYPE_PERSON && $is_for_person) {
            $result = ifset($settlement, 'customer', 'fields_person', array());
            $result = is_array($result) ? $result : array();
        } elseif ($type === shopCustomer::TYPE_COMPANY && $is_for_company) {
            $result = ifset($settlement, 'customer', 'fields_company', array());
            $result = is_array($result) ? $result : array();
        } else {
            $result = array();
        }

        // filter by 'used' not empty
        return array_filter($result, function ($field) {
            return !empty($field['used']);
        });
    }

    /**
     * @throws waException
     * @return waContactForm
     */
    protected function getContactForm()
    {
        // cache
        if (!isset($this->cache['form'])) {
            $this->cache['form'] = $this->buildContactForm();
        }
        return $this->cache['form'];
    }

    /**
     * @return waContactForm
     * @throws waException
     */
    protected function buildContactForm()
    {
        $fields_config = $this->getFieldsConfig();

        $order_editor_config = new shopOrderEditorConfig();
        $address_types = array('address.shipping', 'address.billing');

        foreach ($address_types as $address_type) {
            if (!isset($fields_config[$address_type]['fields'])) {
                continue;
            }
            $subfields = $this->getAddressSubfields();

            foreach ($subfields as $field_id => $field_options) {
                if (!isset($fields_config[$address_type]['fields'][$field_id])) {
                    $field_options['required'] = false;
                    $fields_config[$address_type]['fields'][$field_id] = $field_options;
                }
            }
        }

        /**
         * @var waContact $contact
         */
        $contact = $this->options['contact'];

        $country_fixed_delivery_area = isset($order_editor_config['fixed_delivery_area']['country'])
            ? $order_editor_config['fixed_delivery_area']['country'] : null;
        $no_storefront = empty($this->options['storefront']);

        // if in context of no concrete storefront (aka "added manually" case) and contact's country is not the same as in fixed_delivery_area setting
        //  then force show country field in any case (even if country field was explicitly turned of
        if (isset($contact) && $no_storefront && $country_fixed_delivery_area) {
            $contact_address = $contact['address'];
            if (!empty($contact_address)) {
                foreach ($contact_address as $address) {
                    $contact_country = $address['data']['country'];
                    if (!empty($contact_country) && $contact_country != $country_fixed_delivery_area) {
                        foreach ($contact_address as $address_data) {
                            $address_type = 'address.' . $address_data['ext'];
                            if (in_array($address_type, $address_types, true) && isset($fields_config[$address_type])) {
                                $fields_config[$address_type]['fields']['country'] = array(
                                    'required' => false,
                                );
                            }
                        }
                        break;
                    }
                }
            }
        }

        $form = waContactForm::loadConfig(
            $fields_config,
            array(
                'namespace' => $this->options['namespace'],
            )
        );

        $config_country_enabled = isset($order_editor_config['fields']['address']['country']);
        if (!$config_country_enabled && !empty($country_fixed_delivery_area) && $no_storefront) {
            foreach ($address_types as $address_type) {
                if (isset($fields_config[$address_type]['fields']['country'])) {
                    $country[$address_type]['data']['country'] = $country_fixed_delivery_area;
                    $form->setValue($country);
                }
            }
        }

        if ($contact) {

            $address_display_type = ifset($this->options['address_display_type']);
            if ($address_display_type === 'first') {
                $shipping_address = $contact['address.shipping'];
                if (count($shipping_address) > 1) {
                    $shipping_address = array_slice($shipping_address, 0, 1);
                    $contact['address.shipping'] = $shipping_address;
                }
                $billing_address = $contact['address.billing'];
                if (count($billing_address) > 1) {
                    $billing_address = array_slice($billing_address, 0, 1);
                    $contact['address.billing'] = $billing_address;
                }
            }

            if ($address_display_type !== 'none') {
                $form->setValue($contact);
            } else {
                // unset address, not touch original waContact link, so do clone
                $clone = clone $contact;
                unset($clone['address.shipping'], $clone['address.billing']);
                $form->setValue($clone);
            }
        }

        return $form;
    }

    /**
     * @param null $field_id
     * @return array|mixed
     * @throws waException
     */
    public function post($field_id = null)
    {
        $form_post = $this->getContactForm()->post($field_id);
        if ($field_id === null && is_array($form_post)) {
            $post = wa()->getRequest()->post($this->opt('namespace'));
            $form_post['email_confirmed'] = !empty($post['email_confirmed']);
            $form_post['phone_confirmed'] = !empty($post['phone_confirmed']);
            $form_post['contact_type'] = ifset($post['contact_type']) === shopCustomer::TYPE_COMPANY ? shopCustomer::TYPE_COMPANY : shopCustomer::TYPE_PERSON;
        }
        return $form_post;
    }

    /**
     * HTML for the whole form or single form field.
     * @param string $field_id
     * @param boolean $with_errors whether to add class="error" and error text next to form fields
     * @param bool $placeholders
     * @return string HTML
     * @throws waException
     */
    public function html($field_id = null, $with_errors = true, $placeholders = false)
    {
        if ($field_id === null) {
            return $this->renderForm($with_errors, $placeholders);
        } else {
            return $this->getContactForm()->html($field_id, $with_errors, $placeholders);
        }
    }

    /**
     * @param bool $with_errors
     * @param bool $placeholders
     * @return mixed|string
     * @throws waException
     */
    protected function renderForm($with_errors = true, $placeholders = false)
    {
        $html = $this->getContactForm()->html(null, $with_errors, $placeholders);

        $template_path = wa()->getAppPath('templates/form/customer/backend.html', 'shop');

        // contact for smarty
        $contact = $this->getContact();
        if (!$contact) {
            $contact = new waContact();
        }

        // contact for js
        $contact_info = array(
            'id' => $contact->getId()
        );
        foreach ($this->fields() as $field_id => $_) {
            $contact_info[$field_id] = $contact[$field_id];
        }
        $contact_info['type'] = $this->options['contact_type'];

        $storefront_info = $this->getCurrentStorefrontInfo();

        $contact_type_selector_info = $this->getContactTypeSelectorInfo($contact_info, $storefront_info);

        $html = wa()->getView()->renderTemplate($template_path, array(
            'form'         => $html,
            'options'      => $this->options,
            'contact'      => $contact,
            'contact_info' => $contact_info,  // for js
            'storefront'   => $storefront_info,
            'post'         => $this->post(),
            'form_options' => $this->getContactForm()->opt(),
            'contact_type_selector_info' => $contact_type_selector_info,
            'fields_config' => $this->getFieldsConfig(),
        ));

        return $html;
    }

    /**
     * @return waContact
     */
    protected function getContact()
    {
        $this->options['contact'] = ifset($this->options['contact']);
        return $this->options['contact'];
    }

    /**
     * @param $contact_info
     * @param $storefront_info
     * @return array
     */
    protected function getContactTypeSelectorInfo($contact_info, $storefront_info)
    {
        $result = array(
            shopCustomer::TYPE_PERSON => array(
                'checked' => false,
                'enabled' => true
            ),
            shopCustomer::TYPE_COMPANY => array(
                'checked' => false,
                'enabled' => true
            )
        );

        $result[$contact_info['type']]['checked'] = true;

        if ($storefront_info && isset($storefront_info['contact_type'])) {
            foreach ($storefront_info['contact_type'] as $type => $info) {
                $result[$type]['enabled'] = $info['enabled'];
            }
        }

        return $result;
    }

    protected function getOrderEditorConfig()
    {
        if (empty($this->cache['order_editor_config']) || !$this->cache['order_editor_config'] instanceof shopOrderEditorConfig) {
            $this->cache['order_editor_config'] = new shopOrderEditorConfig();
        }
        return $this->cache['order_editor_config'];
    }


    protected function getFixDeliveryArea()
    {
        if (empty($this->options['storefront'])) {
            $order_editor_config = $this->getOrderEditorConfig();
            return !empty($order_editor_config['fixed_delivery_area']) ? $order_editor_config['fixed_delivery_area'] : [];
        }
        return [];
    }

    public function __call($name, $arguments)
    {
        $form = $this->getContactForm();
        if (method_exists($form, $name)) {
            return call_user_func_array(array($form, $name), $arguments);
        } else {
            return null;
        }
    }

    public function __get($name)
    {
        $form = $this->getContactForm();
        if (property_exists($form, $name)) {
            return $form->{$name};
        } else {
            return null;
        }
    }

    public function __set($name, $value)
    {
        $form = $this->getContactForm();
        if (property_exists($form, $name)) {
            $form->{$name} = $value;
        }
    }
}
