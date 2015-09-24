<?php

class shopCheckoutContactinfo extends shopCheckout
{
    protected $step_id = 'contactinfo';
    /**
     * @var waContactForm
     */
    protected $form;

    public function display()
    {
        if (!$this->form) {
            $this->form = shopHelper::getCustomerForm(null, false, true);
        }

        $contact = $this->getContact();
        if ($contact) {
            $this->form->setValue($contact);

            // Make sure there are no more than one address of each type in the form
            foreach(array('address', 'address.shipping', 'address.billing') as $fld) {
                if (isset($this->form->values[$fld]) && count($this->form->values[$fld]) > 1) {
                    $this->form->values[$fld] = array(reset($this->form->values[$fld]));
                }
            }
        }

        $billing_matches_shipping = false;
        if ($this->form->fields('address.shipping') && $this->form->fields('address.billing')) {
            if (empty($this->form->values['address.shipping'])
                || empty($this->form->values['address.billing'][0]['value'])
                || $this->form->values['address.shipping'][0]['value'] == $this->form->values['address.billing'][0]['value'])
            {
                $billing_matches_shipping = true;
            }
        }

        $view = wa()->getView();
        $view->assign('checkout_contact_form', $this->form);
        $view->assign('billing_matches_shipping', $billing_matches_shipping);
        $view->assign('customer', $contact ? $contact : new waContact());
        if (!$view->getVars('error')) {
            $view->assign('error', array());
        }

        $checkout_flow = new shopCheckoutFlowModel();
        $step_number = shopCheckout::getStepNumber('contactinfo');
        // IF no errors
        $checkout_flow->add(array(
            'step' => $step_number
        ));
        // ELSE
//        $checkout_flow->add(array(
//            'step' => $step_number,
//            'description' => ERROR MESSAGE HERE
//        ));
    }

    public function getErrors()
    {
        $errors = array();

        $contact = $this->getContact();
        if (!$contact) {
            $contact = new waContact();
        }

        $form = shopHelper::getCustomerForm();
        $contact_info = array();
        foreach ($form->fields() as $key => $f) {
            $contact_info[$key] = $contact->get($key);
            if (is_array($contact_info[$key]) && isset($contact_info[$key]['data'])) {
                $contact_info[$key] = $contact_info[$key]['data'];
            }
        }
        if (!$contact_info) {
            $contact_info = array();
        }
        $form->post = $contact_info;

        if (!$form->isValid($contact)) {
            if ($contact_info) {
                $errors[] = _w('Some required contact info fields were not provided. Please return to the contact information checkout step to finalize your order.');
            } else {
                $errors[] = _w('Oops! For some reason your contact information was lost during the checkout. Please return to the contact information checkout step to finalize your order.');
            }
        }
        if (wa('shop')->getSetting('checkout_antispam') && !wa()->getUser()->isAuth()) {
            if (!$this->getSessionData('antispam')) {
                $errors[] = _w('Oops! For some reason your contact information was lost during the checkout. Please return to the contact information checkout step to finalize your order.');
            }
        }
        return $errors;
    }

    protected function sendSpamAlert()
    {
        $email = wa('shop')->getSetting('checkout_antispam_email');
        if (!$email) {
            return;
        }
        try {
            $customer = new waContact();
            foreach ((array)waRequest::post('customer') as $k => $v) {
                $customer->set($k, $v);
            }

            $customer_fields = $this->form->fields();
            $view = wa()->getView();

            $subject = _w('Spammy order alert');
            $view->assign(array(
                'customer' => $customer,
                'customer_fields' => $customer_fields
            ));
            $body = $view->fetch(wa()->getAppPath('templates/mail/AntispamAlert.html', 'shop'));

            $m = new waMailMessage($subject, $body);
            $m->setTo($email);
            $m->send();
        } catch (Exception $e) {
            waLog::log($e->getMessage());
        }
    }

    /**
     * Execute step
     *
     * @return bool
     */
    public function execute()
    {
        $contact = $this->getContact();
        if (!$contact) {
            $contact = new waContact();
        }

        $this->form = shopHelper::getCustomerForm(null, false, true);

        // Do not validate required subfields of billing address
        // when billing is set up to match shipping address.
        if (waRequest::request('billing_matches_shipping') && $this->form->fields('address.shipping') && $this->form->fields('address.billing')) {
            $subfields = $this->form->fields('address.billing')->getParameter('fields');
            foreach($subfields as $i => $sf) {
                if ($sf->isRequired()) {
                    $subfields[$i] = clone $sf;
                    $subfields[$i]->setParameter('required', false);
                }
            }
            $this->form->fields('address.billing')->setParameter('fields', $subfields);
        }

        if (!wa()->getUser()->isAuth() && ($this->form instanceof shopContactForm)) {
            if (!$this->form->isValidAntispam()) {
                $errors = $this->form->errors();
                if (!empty($errors['spam'])) {
                    $this->sendSpamAlert();
                    wa()->getView()->assign('errors', array(
                        'all' => $errors['spam']
                    ));
                }
            }
        }
        if (!$this->form->isValid($contact)) {
            return false;
        }
        if (wa('shop')->getSetting('checkout_antispam') && !wa()->getUser()->isAuth()) {
            $this->setSessionData('antispam', true);
        }

        $data = waRequest::post('customer');
        if ($data && is_array($data)) {
            // When both shipping and billing addresses are enabled,
            // there's an option to only edit one address copy.
            if (waRequest::request('billing_matches_shipping') && $this->form->fields('address.shipping') && $this->form->fields('address.billing') && !empty($data['address.shipping'])) {
                $data['address.billing'] = $data['address.shipping'];
            }

            foreach ($data as $field => $value) {
                $contact->set($field, $value);
            }
        }

        if ($shipping = $this->getSessionData('shipping') && !waRequest::post('ignore_shipping_error')) {
            $shipping_step = new shopCheckoutShipping();
            $rate_id = isset($shipping['rate_id']) ? $shipping['rate_id'] : null;
            $rate = $shipping_step->getRate($shipping['id'], $rate_id, $contact);
            if (!$rate || is_string($rate)) {
                // remove selected shipping method
                $this->setSessionData('shipping', null);
                /*
                $errors = array();
                $errors['all'] = sprintf(_w('We cannot ship to the specified address via %s.'), $shipping['name']);
                if ($rate) {
                    $errors['all'] .= '<br> <strong>'.$rate.'</strong><br>';
                }
                $errors['all'] .= '<br> '._w('Please double-check the address above, or return to the shipping step and select another shipping option.');
                $errors['all'] .= '<input type="hidden" name="ignore_shipping_error" value="1">';
                wa()->getView()->assign('errors', $errors);
                return false;
                */
            }
        }

        if (wa()->getUser()->isAuth()) {
            $contact->save();
        } else {
            $errors = array();
            if (waRequest::post('create_user')) {
                $login = waRequest::post('login');
                if (!$login) {
                    $errors['email'][] = _ws('Required');
                }
                if (!waRequest::post('password')) {
                    $errors['password'] = _ws('Required');
                }
                $email_validator = new waEmailValidator();
                if (!$email_validator->isValid($login)) {
                    $errors['email'] = $email_validator->getErrors();
                }
                if (!$errors) {
                    $contact_model = new waContactModel();
                    if ($contact_model->getByEmail($login, true)) {
                        $errors['email'][] = _w('Email already registered');
                    }
                }
                if (!$errors) {
                    $contact->set('email', $login);
                    $contact->set('password', waRequest::post('password'));
                } else {
                    if (isset($errors['email'])) {
                        $errors['email'] = implode(', ', $errors['email']);
                    }
                    wa()->getView()->assign('errors', $errors);
                    return false;
                }
            }
            $this->setSessionData('contact', $contact);
        }

        if ($comment = waRequest::post('comment')) {
            $this->setSessionData('comment', $comment);
        }

        return true;
    }

    public function getOptions($config)
    {
        $action = new shopSettingsCheckoutContactFormAction($config);
        return $action->display();
    }

    public function setOptions($config)
    {
        if (!waRequest::post()) {
            return $config;
        }

        $options = waRequest::post('options');
        if (!is_array($options)) {
            return $config;
        }

        $fields_unsorted = waContactFields::getAll('all');
        $config['fields'] = array();
        $cfvm = new waContactFieldValuesModel();
        foreach($options as $fld_id => $opts) {
            if ($fld_id == '%FID%') {
                continue;
            }
            $fld_id_no_ext = explode('.', $fld_id, 2);
            $field_ext = empty($fld_id_no_ext[1]) ? '' : '.'.$fld_id_no_ext[1];
            $fld_id_no_ext = $fld_id_no_ext[0];

            $field = ifset($fields_unsorted[$fld_id_no_ext]);

            if ($field && $fld_id_no_ext == 'address') {
                $existing_subfields = $field->getFields();

                // Special treatment for subfields of shipping and billing address:
                // copy actual settings from address field.
                if ($field_ext) {
                    // Sanity check
                    if (!is_array($opts) || empty($options['address']) || !is_array($options['address']) || empty($options['address']['fields']) || !is_array($options['address']['fields'])) {
                        continue;
                    }

                    // Copy settings if subfield is turned on, or required, or is hidden
                    $fields = array();
                    foreach($options['address']['fields'] as $sf_id => $sf_opts) {
                        if (!empty($sf_opts['required']) || ( !empty($sf_opts['_disabled']) && !empty($sf_opts['_default_value_enabled']) && empty($sf_opts['_deleted']) ) || !empty($opts['fields'][$sf_id])) {
                            if (isset($existing_subfields[$sf_id])) {
                                $fields[$sf_id] = $sf_opts;
                            }
                        }
                    }

                    $opts['fields'] = $fields;
                } else {
                    // Actual address field with no ext.
                    // Do not allow to completely delete standard set of address subfields, just disable.
                    foreach($existing_subfields as $sf) {
                        if ($sf->getParameter('app_id') !== 'shop' && empty($opts['fields'][$sf->getId()])) {
                            $opts['fields'][$sf->getId()] = $sf->getParameters();
                            $opts['fields'][$sf->getId()]['_disabled'] = 1;
                        }
                    }
                }
            }

            if ($field) {
                if (!empty($opts['_deleted'])) {
                    waContactFields::deleteField($field);
                    unset($fields_unsorted[$fld_id_no_ext]);
                    continue;
                }
                $new_field = false;
            } else {
                $field = self::createFromOpts($opts, $fields_unsorted);
                if (!$field || $field instanceof waContactCompositeField) {
                    continue;
                }

                // For conditional fields, update ID in database: replace temporary id with new one
                if ($field instanceof waContactConditionalField) {
                    $cfvm->changeField($fld_id_no_ext, $field->getId());
                }

                $fld_id = $field->getId().$field_ext;
                $new_field = true;
            }
            list($local_opts, $sys_opts) = self::tidyOpts($field, $fld_id, $opts);
            if ($local_opts === null || $sys_opts === null) {
                continue;
            }

            // Write to system config.
            if (!$field_ext) {
                $field->setParameters($sys_opts);
                $fields_unsorted[$fld_id_no_ext] = $field;
                if ($new_field) {
                    waContactFields::createField($field);
                    waContactFields::enableField($field, 'person');
                    $fields_unsorted[$field->getId()] = $field;
                } else if ($sys_opts) {
                    waContactFields::updateField($field);
                    waContactFields::enableField($field, 'person');
                }
            }
            $config['fields'][$fld_id] = $local_opts;
        }

        // Delete garbage from wa_contact_field_values
        $cfvm->exec("DELETE FROM wa_contact_field_values WHERE field RLIKE '__[0-9]+$'");

        return $config;
    }

    /**
     * Create new waContactField of appropriate type from given array of options.
     */
    public static function createFromOpts($opts, $occupied_keys=array())
    {
        if (!is_array($opts) || empty($opts['_type'])) {
            return null;
        }

        // Generate field_id from name
        $fld_id = shopHelper::transliterate((string) ifset($opts['localized_names'], ''));
        if (!$fld_id) {
            $fld_id = 'f';
        }
        if (strlen($fld_id) > 15) {
            $fld_id = substr($fld_id, 0, 15);
        }
        while (isset($occupied_keys[$fld_id])) {
            if (strlen($fld_id) >= 15) {
                $fld_id = substr($fld_id, 0, 10);
            }
            $fld_id .= mt_rand(0, 9);
        }

        // Create field object of appropriate type
        $options = array(
            'app_id' => 'shop',
        );
        $ftype = strtolower($opts['_type']);
        switch($ftype) {
            case 'textarea':
                $class = 'waContactStringField';
                $options['storage'] = 'waContactDataStorage';
                $options['input_height'] = 5;
                break;
            case 'radio':
                $class = 'waContactRadioSelectField';
                break;
            default:
                $class = 'waContact'.ucfirst($ftype).'Field';
        }
        if (!$ftype || !class_exists($class)) {
            return null;
        }
        return new $class($fld_id, '', $options);
    }

    /**
     * Make sure given array of options is valid for $field.
     * Return list($local_opts, $sys_opts) to save for this $field.
     * Local options are saved to shop app config. System options to contacts app config.
     * If any of option sets returned is null, this field is skipped all together.
     */
    protected static function tidyOpts($field, $fld_id, $opts)
    {
        if ($fld_id == '%FID%' || !is_array($opts) || !empty($opts['_deleted']) || empty($opts['localized_names'])) {
            return array(null, null);
        }
        if (!empty($opts['_disabled'])) {
            if (!empty($opts['_default_value_enabled']) && isset($opts['_default_value']) && strlen($opts['_default_value'])) {

                // A hack for region field: when user specifies region name, replace it with region code.
                // In case there's a region with code equal to another region's name, prefer the former.
                if ($field instanceof waContactRegionField) {
                    $rm = new waRegionModel();
                    $regions = $rm->select('code, code AS a')->where('code = s:0 OR name = s:0', $opts['_default_value'])->query()->fetchAll('code', true);
                    if ($regions && empty($regions[$opts['_default_value']])) {
                        $opts['_default_value'] = reset($regions);
                    }
                }

                return array(
                    array(
                        'hidden' => true,
                        'value' => $opts['_default_value'],
                    ),
                    array()
                );
            } else {
                return array(null, null);
            }
        }
        unset($opts['_disabled'], $opts['_type'], $opts['_deleted'], $opts['_default_value'], $opts['_default_value_enabled']);

        $sys_opts = array();

        if (in_array(get_class($field), array('waContactSelectField', 'waContactRadioSelectField', 'waContactChecklistField', 'waContactBranchField'))) {
            if (!empty($opts['options']) && is_array($opts['options'])) {

                if ($field instanceof waContactBranchField) {
                    if (empty($opts['hide']) || !is_array($opts['hide'])) {
                        $opts['hide'] = array();
                    }
                }

                // get rid of empty last element
                if ( ( $el = trim(array_pop($opts['options'])))) {
                    $opts['options'][] = $el;
                }

                $branch_hide = array();
                $select_options = array();
                foreach($opts['options'] as $i => $v) {
                    $v = trim($v);
                    $select_options[$v] = $v;
                    if ($field instanceof waContactBranchField && !empty($opts['hide'][$i])) {
                        $branch_hide[$v] = explode(',', (string) $opts['hide'][$i]);
                    }
                }

                if (!$select_options) {
                    return array(null, null);
                }

                $sys_opts['options'] = $select_options;
                if ($field instanceof waContactBranchField) {
                    $sys_opts['hide'] = $branch_hide;
                }
            } else {
                if (!$field->getParameter('options')) {
                    // Never allow select-based field with no options to select from
                    return array(null, null);
                }
            }
            unset($opts['options']);
        } else if ($field instanceof waContactCompositeField) {
            if (empty($opts['fields']) || !is_array($opts['fields'])) {
                return array(null, null);
            }

            $subfields = array();
            $subfields_sys = array();
            $existing_subfields = $field->getFields();
            foreach ($opts['fields'] as $sf_id => $o) {
                if ($sf_id == '%FID%' || !empty($o['_deleted'])) {
                    continue;
                }
                if (empty($existing_subfields[$sf_id])) {
                    $sf = self::createFromOpts($o, $opts['fields'] + $existing_subfields);
                    if (!$sf) {
                        continue;
                    }

                    // For conditional fields, update ID in database: replace temporary id with new one
                    if ($sf instanceof waContactConditionalField) {
                        $cfvm = new waContactFieldValuesModel();
                        $cfvm->changeField($sf_id, $sf->getId());
                    }

                    $sf_id = $sf->getId();
                } else {
                    $sf = $existing_subfields[$sf_id];
                    $subfields_sys[$sf_id] = $sf; // make sure it is saved to system config
                }

                list($o, $sys_o) = self::tidyOpts($sf, $sf_id, $o);
                if ($o === null || $sys_o === null) {
                    continue;
                }
                if ($sf instanceof waContactConditionalField) {
                    $sys_o['parent_id'] = $fld_id;
                }

                $sf->setParameters($sys_o);
                $subfields_sys[$sf_id] = $sf;
                $subfields[$sf_id] = $o;
            }
            if (!$subfields) {
                return array(null, null);
            }

            $opts['fields'] = $subfields;
            $sys_opts['fields'] = $subfields_sys;

        }

        if ($field->getParameter('app_id') == 'shop') {
            $sys_opts += $opts;
            $opts = array();
            foreach(waContactFields::$customParameters as $k => $v) {
                if (isset($sys_opts[$k])) {
                    $opts[$k] = $sys_opts[$k];
                }
            }
        }
        if (empty($opts) && $opts !== null) {
            $opts = array('__dummy__' => 1);
        }
        return array($opts, $sys_opts);
    }
}

