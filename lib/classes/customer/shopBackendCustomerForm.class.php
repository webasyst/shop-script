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
            'storefront'      => null
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
            'url' => $this->options['storefront']
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
     * In this list also included 'address.shipping' and 'address.billing'
     *
     * @return array $result
     *   - string $result['type'] - what type of logic worked for forming of field list
     *   - array $result['field_list'] - field list itself
     * @throws waException
     */
    protected function getFieldList()
    {
        $res = $this->getStorefrontFieldList();

        if ($res['status']) {
            return array(
                'type' => 'storefront',
                'field_list' => $res['field_list']
            );
        }

        $result = array(
            'type' => 'union',
            'field_list' => $this->getUnitedFieldList()
        );

        return $result;
    }

    /**
     * Get list of fields that is pretty match UNION of fields for all storefronts
     * But there are some nuances:
     *   1. There is specially not presented 'address' field
     *   2. There is always presented 'address.shipping' field with ALL sub-fields
     *   3. There is always presented 'address.billing' field with ALL sub-fields
     * @return array
     * @throws waException
     */
    protected function getUnitedFieldList()
    {
        $contact_type = $this->options['contact_type'];

        // Init form fields var: prepare fields places for preserving original sorting of fields for this type of contact
        $form_fields = array();
        $all_fields = waContactFields::getAll($contact_type);
        foreach ($all_fields as $field_id => $_) {
            $form_fields[$field_id] = null;
        }

        // Object to work with list of storefronts
        $list = $this->newStorefrontList();

        // will get storefronts only for this contact_type
        $list->addFilter(array('contact_type' => $contact_type));

        // will get storefronts only for new checkout version
        $list->addFilter(function($storefront) {
            $checkout_version = ifset($storefront, 'route', 'checkout_version', false);
            return $checkout_version >= 2;
        }, 'new_checkout_filter');


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

            $is_v1_checkout = $list->count() > 0;

            if ($is_v1_checkout) {
                // mix-in OLD checkout fields - they all the same for all OLD checkouts (so, don't need iterate)
                $form_fields = array_merge($form_fields, self::getOldCheckoutFieldList(array(
                    'need_shipping_address' => false,
                    'need_billing_address' => false
                )));
            }
        }

        // there could be NULL values, cause in init step of current method we preserve places to preserve original sorting of fields
        // but now we not need them
        $form_fields = $this->dropNulls($form_fields);

        // address never needed in backend customer form
        unset($form_fields['address']);

        // to ensure address.shipping is first in order than address.billing
        unset($form_fields['address.shipping'], $form_fields['address.billing']);

        // shipping address always needed
        $form_fields['address.shipping'] = array('fields' => $this->getAllAddressSubfields());

        // billing address always need
        $form_fields['address.billing'] = array('fields' => $this->getAllAddressSubfields());

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
                'status' => false,
                'field_list' => array()
            );
        }

        $checkout_version = ifset($storefront, 'route', 'checkout_version', false);
        if ($checkout_version < 2) {
            return array(
                'status' => true,
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
            'status' => true,
            'field_list' => $field_list
        );
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
            return $this->getOldCheckoutShippingAddressSubfields();
        } else {
            return $this->getSettlementShippingAddressSubfields($storefront['checkout_config']);
        }
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

        return $this->getOldCheckoutBillingAddressSubfields();
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
            $fields_config['address.shipping'] = array('fields' => $this->getOldCheckoutShippingAddressSubfields());
        }

        if (isset($options['need_billing_address'])) {
            $need_billing_address = $options['need_billing_address'] ? 'required' : null;
        } else {
            $need_billing_address = 'optional';
        }

        if ($need_billing_address === 'required') {
            $subfields = $this->getOldCheckoutBillingAddressSubfields();
            if (!$subfields) {
                $subfields = $this->getAllAddressSubfields();
            }
            $fields_config['address.billing'] = array('fields' => $subfields);
        } elseif ($need_billing_address === 'optional') {
            $subfields = $this->getOldCheckoutBillingAddressSubfields();
            if ($subfields) {
                $fields_config['address.billing'] = array('fields' => $subfields);
            }
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
            $subfields = $this->getAllAddressSubfields();
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
     * Get all subfields of address fields
     * @return array - array of array indexed by IDs of subfields
     */
    protected function getAllAddressSubfields()
    {
        $address = waContactFields::get('address');
        if ($address instanceof waContactAddressField) {
            return array_fill_keys(array_keys($address->getFields()), array());
        }
        return array();
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

    protected function dropNulls($values, $reindex = false)
    {
        if (!is_array($values)) {
            return array();
        }
        foreach ($values as $index => $value) {
            if ($value === null) {
                unset($values[$index]);
            }
        }
        if ($reindex) {
            $values = array_values($values);
        }
        return $values;
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
        $result = $this->getFieldList();

        $fields_config = $result['field_list'];

        /**
         * @var waContact $contact
         */
        $contact = $this->options['contact'];

        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();

        // set current country as a value
        $current_country_value = $config->getGeneralSettings('country');
        if (empty($fields_config['address.shipping']['fields']['country']['value'])) {
            $fields_config['address.shipping']['fields']['country']['value'] = $current_country_value;
        }

        // for NOT storefront case all fields are NOT required, otherwise don't touch, leave as in fields config
        if ($result['type'] !== 'storefront') {

            foreach ($fields_config as &$field) {
                $field['required'] = false;
            }
            unset($field);

            foreach ($fields_config['address.shipping']['fields'] as &$subfield) {
                $subfield['required'] = false;
            }
            unset($subfield);

            foreach ($fields_config['address.billing']['fields'] as &$subfield) {
                $subfield['required'] = false;
            }
            unset($subfield);
        }

        $form = waContactForm::loadConfig(
            $fields_config,
            array(
                'namespace' => $this->options['namespace'],
            )
        );

        if ($contact) {
            $form->setValue($contact);
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
            'contact_type_selector_info' => $contact_type_selector_info
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
