<?php

class shopSettingsCheckout2Action extends shopSettingsCheckoutAbstractAction
{
    /**
     * @var shopCheckoutConfig
     */
    protected $checkout_config;

    public function execute()
    {
        $route = $this->getRoute();
        try {
            $this->checkout_config = new shopCheckoutConfig(ifset($route, 'checkout_storefront_id', null));
        } catch (waException $e) {
            $this->view->assign([
                'route'           => false,
                'checkout_config' => false,
            ]);
            return;
        }

        $this->view->assign([
            'route'                                    => $route,
            'checkout_config'                          => $this->checkout_config,
            'demo_terms'                               => $this->getDemoTerms(),
            'field_width_variants'                     => $this->checkout_config->getFieldWidthVariants(),
            'schedule_mode_variants'                   => $this->checkout_config->getScheduleModeVariants(),
            'shipping_mode_variants'                   => $this->checkout_config->getShippingModeVariants(),
            'shipping_show_pickuppoint_map_variants'   => $this->checkout_config->getShippingShowPickuppointMapVariants(),
            'customer_type_variants'                   => $this->checkout_config->getCustomerTypeVariants(),
            'customer_service_agreement_variants'      => $this->checkout_config->getCustomerServiceAgreementVariants(),
            'cart_discount_item_variants'              => $this->checkout_config->getCartDiscountItemVariants(),
            'cart_discount_general_variants'           => $this->checkout_config->getCartDiscountGeneralVariants(),
            'confirmation_order_without_auth_variants' => $this->checkout_config->getConfirmationOrderWithoutAuthVariants(),
            'field_types'                              => waContactFields::getTypes(),
            'contact_fields'                           => $this->getContactFields(),
            'address_fields'                           => $this->getContactAddressFields(),
            'system_address_field_names'               => $this->checkout_config->getSystemAddressFieldNames(),
            'format_contact_fields'                    => $this->getFormatContactFields(),
            'format_address_fields'                    => $this->getFormatContactAddressFields(),
            'countries'                                => $this->getCountries(),
            'regions'                                  => $this->getRegions(),
            'timezones'                                => wa()->getDateTime()->getTimezones(),
            'logo_url'                                 => shopCheckoutConfig::getLogoUrl(),
            'shipping_plugins'                         => $this->getPlugins(shopPluginModel::TYPE_SHIPPING),
            'payment_plugins'                          => $this->getPlugins(shopPluginModel::TYPE_PAYMENT),
        ]);
    }

    protected function getRouteDomain()
    {
        $domain = waRequest::get('domain', null, waRequest::TYPE_STRING_TRIM);
        return waIdna::enc($domain);
    }

    protected function getRouteId()
    {
        return waRequest::get('route', null, waRequest::TYPE_INT);
    }

    protected function getRoute()
    {
        $route_domain = $this->getRouteDomain();

        $route_id = $this->getRouteId();
        $shop_routes = wa()->getRouting()->getByApp('shop');
        $domain_routes = ifset($shop_routes, $route_domain, []);
        foreach ($domain_routes as $r_id => $route) {
            if ($route_id == $r_id) {
                $route['route_id'] = $r_id;
                $route['domain'] = waIdna::dec($route_domain);
                return $route;
            }
        }
        return null;
    }

    protected function getDemoTerms()
    {
        $terms = [];
        $terms_path = wa('shop')->getConfig()->getAppPath('lib/config/data/terms.php');
        if (file_exists($terms_path)) {
            $terms = include($terms_path);
            if (!is_array($terms)) {
                $terms = [];
            }
        }
        $locale = wa()->getLocale();
        if (!isset($terms[$locale])) {
            $locale = 'en_US';
        }

        $terms = isset($terms[$locale]) ? $terms[$locale] : reset($terms);

        return $terms;
    }

    protected function getContactFields()
    {
        static $contact_fields;

        if ($contact_fields === null) {
            $contact_fields = waContactFields::getAll('all');
        }

        return $contact_fields;
    }

    protected function getContactAddressFields()
    {
        static $address_fields;

        if ($address_fields === null) {
            $address_field = waContactFields::get('address');
            $address_fields = [];
            if ($address_field instanceof waContactAddressField && is_array($address_field->getFields())) {
                foreach ($address_field->getParameter('fields') as $sub_field) {
                    /**
                     * @var waContactField $sub_field
                     */
                    $address_fields[$sub_field->getId()] = $sub_field;
                }
            }
        }

        return $address_fields;
    }

    protected function getFormatContactFields()
    {
        $fields = $this->getContactFields();

        /**
         * @var waContactField $field
         */
        foreach ($fields as $field_id => $field) {
            $field_data = $field->getInfo();
            $field_data['php_class'] = get_class($field);
            $fields[$field_id] = $field_data;
        }

        $fields = $this->checkout_config->formatContactFields($fields);

        return $fields;
    }

    protected function getFormatContactAddressFields()
    {
        $fields = $this->getContactAddressFields();

        /**
         * @var waContactAddressField $field
         */
        foreach ($fields as $field_id => $field) {
            $field_data = $field->getInfo();
            $field_data['php_class'] = get_class($field);
            $fields[$field_id] = $field_data;
        }

        $fields = $this->checkout_config->formatContactFields($fields);

        return $fields;
    }

    protected function getCountries()
    {
        $cm = new waCountryModel();
        return $cm->all();
    }

    protected function getRegions()
    {
        $rm = new waRegionModel();
        return $rm->getAll();
    }

    protected function getPlugins($type)
    {
        static $model;

        if ($model === null) {
            $model = new shopPluginModel();
        }

        $plugins = $model->listPlugins($type);
        return $plugins;
    }
}