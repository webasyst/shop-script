<?php

/**
 * Settings for contact data form. Part of a large checkout settings page.
 * Used via shopCheckoutContactinfo.
 */
class shopSettingsCheckoutContactFormAction extends waViewAction
{
    public function execute()
    {
        // $this->getConfig()->getCheckoutSettings()['contactinfo']
        $config = $this->params;
        if (empty($config)) {
            //$config_steps = $this->getConfig()->getCheckoutSettings();
            //$config = $config_steps['contactinfo']; // debug helper
            $config = array();
        }

        $fields_unsorted = waContactFields::getAll();

        // Allow to disable name field in form, despite that it is normally required.
        $fields_unsorted['name'] = clone $fields_unsorted['name'];
        $fields_unsorted['name']->setParameter('required', false);

        // Ignore hidden subfields of an address. Pretend they don't even exist.
        $subfields = array();
        $fields_unsorted['address'] = clone $fields_unsorted['address'];
        foreach($fields_unsorted['address']->getFields() as $sf) {
            if (!$sf instanceof waContactHiddenField) {
                $subfields[$sf->getId()] = $sf;
            }
        }
        $fields_unsorted['address']->setParameter('fields', $subfields);
        unset($subfields);

        // Clone billing and shipping from main address
        $fields_unsorted['address.billing'] = clone $fields_unsorted['address'];
        $fields_unsorted['address.billing']->setParameter('localized_names', _w('Billing address'));
        $fields_unsorted['address.shipping'] = clone $fields_unsorted['address'];
        $fields_unsorted['address.shipping']->setParameter('localized_names', _w('Shipping address'));

        // Clone contact field objects and load shop config parameters into them
        $fields = array();
        $config_fields = ifempty($config['fields'], array());
        foreach($config_fields as $fld_id => $opts) {

            // Skip hidden fields (they are shown as 'disabled')
            if (!empty($opts['hidden'])) {
                continue;
            }

            // This allows to specify e.g. 'address.shipping' as field id in config.
            $real_fld_id = explode('.', $fld_id, 2);
            $real_fld_id = $real_fld_id[0];
            if (empty($fields_unsorted[$real_fld_id]) || !($fields_unsorted[$real_fld_id] instanceof waContactField) || !is_array($opts)) {
                continue;
            }

            // Clone the field
            $fields[$fld_id] = clone $fields_unsorted[$real_fld_id];

            // Load shop config parameters into cloned field
            foreach($opts as $k => $v) {

                // Clone subfields of a composite field
                if ($fields[$fld_id] instanceof waContactCompositeField && $k == 'fields') {
                    $cloned_subfields = array();
                    foreach($fields[$fld_id]->getFields() as $sf) {
                        $sf = clone $sf;
                        $cloned_subfields[$sf->getId()] = $sf;
                        if (is_array($v)) {
                            $o = ifset($v[$sf->getId()]);
                            if ($o && is_array($o) && empty($o['hidden'])) {
                                $sf->setParameters($o);
                            } else {
                                $sf->setParameter('_disabled', true);
                            }
                        }
                    }
                    $v = $cloned_subfields;
                }

                $fields[$fld_id]->setParameter($k, $v);
            }
        }

        // Add to $fields everything that were not specified in shop config
        foreach($fields_unsorted as $fld_id => $f) {
            if (empty($fields[$fld_id])) {
                $fields[$fld_id] = clone $f;
                $fields[$fld_id]->setParameter('_disabled', true);
            }
        }

        // Address fields are shown separately
        $address = $fields['address'];
        $billing_address = $fields['address.billing'];
        $shipping_address = $fields['address.shipping'];
        unset($fields['address.billing'], $fields['address.shipping'], $fields['address']);
        $shipbill_address = array();
        $shipbill_address['ship'] = array(
            'short_id' => 'ship',
            'id' => 'address.shipping',
            'name' => _w('Shipping address prompt'),
            'f' => $shipping_address,
            'subfields' => array(),
            'show_custom_settings' => false,
        );
        $shipbill_address['bill'] = array(
            'short_id' => 'bill',
            'id' => 'address.billing',
            'name' => _w('Billing address prompt'),
            'f' => $billing_address,
            'subfields' => array(),
            'show_custom_settings' => false,
        );
        $address_subfields = $address->getFields();
        foreach($address_subfields as $sf) {
            $sfa = array(
                'id' => $sf->getId(),
                'name' => $sf->getName(),
                'enabled' => false,
                'f' => $sf,
            );
            $shipbill_address['ship']['subfields'][$sf->getId()] = $sfa;
            $shipbill_address['bill']['subfields'][$sf->getId()] = $sfa;
        }

        foreach($shipping_address->getFields() as $sf) {
            if ($sf->getParameter('_disabled')) {
                $shipbill_address['ship']['show_custom_settings'] = true;
            } else {
                $shipbill_address['ship']['subfields'][$sf->getId()]['enabled'] = true;
            }
        }
        foreach($billing_address->getFields() as $sf) {
            if ($sf->getParameter('_disabled')) {
                if (!empty($address_subfields[$sf->getId()]) && !$address_subfields[$sf->getId()]->getParameter('_disabled')) {
                    $shipbill_address['bill']['show_custom_settings'] = true;
                }
            } else {
                $shipbill_address['bill']['subfields'][$sf->getId()]['enabled'] = true;
            }
        }

        $this->view->assign('fields', $fields);
        $this->view->assign('address', $address);
        $this->view->assign('shipbill_address', $shipbill_address);
    }
}

