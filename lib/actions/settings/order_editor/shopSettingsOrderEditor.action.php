<?php

class shopSettingsOrderEditorAction extends waViewAction
{
    /**
     * @var shopOrderEditorConfig
     */
    protected $config;

    /**
     * @var shopBackendCustomerForm
     */
    protected $customer_form;

    /**
     * @var array
     */
    protected $fields_config;

    /**
     * Cache with contact/company fields
     * @var array
     */
    protected $contact_fields;

    public function preExecute()
    {
        $this->config = new shopOrderEditorConfig();
        $this->customer_form = new shopBackendCustomerForm();
    }

    public function execute()
    {
        // Person fields
        $all_person_fields = $this->getContactFields(shopCustomer::TYPE_PERSON);
        $person_fields_config = $this->getFieldsConfig(shopOrderEditorConfig::FIELDS_TYPE_PERSON);
        $person_fields = $this->sortFields($all_person_fields, $person_fields_config);

        // Company fields
        $all_company_fields = $this->getContactFields(shopCustomer::TYPE_COMPANY);
        $company_fields_config = $this->getFieldsConfig(shopOrderEditorConfig::FIELDS_TYPE_COMPANY);
        $company_fields = $this->sortFields($all_company_fields, $company_fields_config);

        // Address fields
        $all_address_fields = $this->getContactAddressFields();
        $address_fields_config = $this->getFieldsConfig(shopOrderEditorConfig::FIELDS_TYPE_ADDRESS);
        $address_fields = $this->sortFields($all_address_fields, $address_fields_config);

        $this->view->assign([
            'config'               => $this->config,
            'name_format_variants' => $this->config->getNameFormatVariants(),

            'person_fields'        => $person_fields,
            'person_fields_config' => $person_fields_config,

            'company_fields'        => $company_fields,
            'company_fields_config' => $company_fields_config,

            'address_fields'        => $address_fields,
            'address_fields_config' => $address_fields_config,

            'field_types' => waContactFields::getTypes(),

            'wa_settings' => $this->getUser()->getRights('webasyst', 'backend'),

            'countries' => $this->getCountries(),
            'regions'   => $this->getRegions(),
        ]);
    }

    protected function getFieldsConfig($fields_type)
    {
        if (!isset($this->fields_config[$fields_type])) {
            if ($fields_type !== shopOrderEditorConfig::FIELDS_TYPE_ADDRESS) {
                $this->customer_form->setContactType($fields_type);
            }

            try {
                $field_list = $this->customer_form->getFieldsConfig();
            } catch (waException $e) {
                $field_list = [];
            }

            $field_list = is_array($field_list) ? $field_list : [];

            if ($fields_type === shopOrderEditorConfig::FIELDS_TYPE_ADDRESS) {
                $field_list = !empty($field_list['address.shipping']['fields']) ? $field_list['address.shipping']['fields'] : [];
            } else {
                unset($field_list['address.shipping'], $field_list['address.billing']);
            }

            $this->fields_config[$fields_type] = $field_list;
        }

        return $this->fields_config[$fields_type];
    }

    protected function getContactFields($contact_type)
    {
        if (empty($this->contact_fields[$contact_type])) {
            $fields = [];
            /**
             * @var $contact_fields waContactField[]
             */
            $contact_fields = waContactFields::getAll($contact_type);
            foreach ($contact_fields as $field) {

                // For company Name fields not any sense
                // because it is alias for 'company' field, but could cause problems when it is and 'company' are in form at the same time
                // The same with hidden fields
                if ($field->isHidden() || $field->getId() == 'address' || ($field->getId() === 'name' && $contact_type === shopCustomer::TYPE_COMPANY)) {
                    continue;
                }

                /** @var waContactField $field */
                $fields[$field->getId()] = $field;
            }

            $this->contact_fields[$contact_type] = $fields;
        }

        return $this->contact_fields[$contact_type];
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

                    // Ignore hidden fields
                    if ($sub_field->isHidden()) {
                        continue;
                    }

                    $address_fields[$sub_field->getId()] = $sub_field;
                }
            }
        }

        return $address_fields;
    }

    protected function sortFields($all_fields, $sorted_fields)
    {
        $fields = [];
        foreach ($sorted_fields as $field_id => $field_params) {
            if (isset($all_fields[$field_id])) {
                $fields[$field_id] = $all_fields[$field_id];
            }
        }

        $fields = array_merge($fields, $all_fields);

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
}