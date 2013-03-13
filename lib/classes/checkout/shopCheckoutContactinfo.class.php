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
            $this->form = shopHelper::getCustomerForm();
        }
        $contact = $this->getContact();
        if ($contact) {
            $this->form->setValue($contact);
        }
        $view = wa()->getView();
        $view->assign('checkout_contact_form', $this->form);
    }

    public function validate()
    {

    }

    /**
     * Execute step
     *
     * @return bool
     */
    public function execute()
    {
        if (wa()->getUser()->isAuth()) {
            $contact = wa()->getUser();
        } else {
            $contact = new waContact();
        }

        $this->form = shopHelper::getCustomerForm();
        if (!$this->form->isValid($contact)) {
            return false;
        }

        $data = waRequest::post('customer');
        if ($data && is_array($data)) {
            foreach ($data as $field => $value) {
                $contact->set($field, $value);
            }
        }

        if (wa()->getUser()->isAuth()) {
            $contact->save();
        } else {
            $this->setSessionData('contact', $contact);
        }

        if ($comment = waRequest::post('comment')) {
            $this->setSessionData('comment', $comment);
        }

        return true;
    }

    public function getOptions($config)
    {
        $action = new shopSettingsCheckoutContactFormAction($config['fields']);
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

        $fields_unsorted = waContactFields::getAll();
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

            $field->setParameters($sys_opts);
            if ($new_field) {
                waContactFields::createField($field);
                waContactFields::enableField($field, 'person');
                $fields_unsorted[$field->getId()] = $field;
            } else if ($sys_opts) {
                waContactFields::updateField($field);
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
        while (isset($occupied_keys[$fld_id])) {
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
     * If any of option sets returned is null, this field is skipped altogether.
     */
    protected static function tidyOpts($field, $fld_id, $opts)
    {
        if ($fld_id == '%FID%' || !is_array($opts) || !empty($opts['_disabled']) || !empty($opts['_deleted']) || empty($opts['localized_names'])) {
            return array(null, null);
        }
        unset($opts['_disabled'], $opts['_type'], $opts['_deleted']);

        $sys_opts = array();

        if (in_array(get_class($field), array('waContactSelectField', 'waContactRadioSelectField', 'waContactChecklistField'))) {
            if (empty($opts['options']) || !is_array($opts['options'])) {
                return array(null, null);
            }

            // get rid of empty last element
            if ( ( $el = trim(array_pop($opts['options'])))) {
                $opts['options'][] = $el;
            }

            $select_options = array();
            foreach($opts['options'] as $v) {
                $v = trim($v);
                $select_options[$v] = $v;
            }

            if (!$select_options) {
                return array(null, null);
            }

            $sys_opts['options'] = $select_options;
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
                    $sf = self::createFromOpts($o);
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

        if ($field->getParameter('app_id') == 'shop' && !empty($opts['localized_names'])) {
            $sys_opts['localized_names'] = (string) $opts['localized_names'];
        }

        return array($opts, $sys_opts);
    }
}

