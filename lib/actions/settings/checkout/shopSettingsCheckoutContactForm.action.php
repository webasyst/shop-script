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
        $fields_unsorted['address.billing'] = clone $fields_unsorted['address'];
        $fields_unsorted['address.billing']->setParameter('localized_names', _w('Billing address'));
        $fields_unsorted['address.shipping'] = clone $fields_unsorted['address'];
        $fields_unsorted['address.shipping']->setParameter('localized_names', _w('Shipping address'));

        // Load config parameters into cloned fields
        $fields = array();
        foreach($config as $fld_id => $opts) {
            // This allows to specify e.g. 'address.work' as field id in config.
            $real_fld_id = explode('.', $fld_id, 2);
            $real_fld_id = $real_fld_id[0];
            if (empty($fields_unsorted[$real_fld_id]) || !($fields_unsorted[$real_fld_id] instanceof waContactField) || !is_array($opts)) {
                continue;
            }
            $fields[$fld_id] = clone $fields_unsorted[$real_fld_id];
            $fields[$fld_id]->setParameter('always_required', $fields[$fld_id]->getParameter('required'));
            foreach($opts as $k => $v) {
                if ($fields[$fld_id] instanceof waContactCompositeField && $k == 'fields') {
                    if (is_array($v)) {
                        foreach($fields[$fld_id]->getFields() as $sf) {
                            $o = ifset($v[$sf->getId()]);
                            if ($o && is_array($o)) {
                                $sf->setParameters($o);
                            } else {
                                $sf->setParameter('_disabled', true);
                            }
                        }
                    }
                } else {
                    $fields[$fld_id]->setParameter($k, $v);
                }
            }
        }

        // Add to $fields everything that were not specified in config.
        foreach($fields_unsorted as $fld_id => $f) {
            if (empty($fields[$fld_id])) {
                $fields[$fld_id] = clone $f;
                $fields[$fld_id]->setParameter('_disabled', true);
            }
        }

        $billing_address = $fields['address.billing'];
        $shipping_address = $fields['address.shipping'];
        unset($fields['address.billing'], $fields['address.shipping'], $fields['address']);

        $this->view->assign('fields', $fields);
        $this->view->assign('billing_address', $billing_address);
        $this->view->assign('shipping_address', $shipping_address);
    }
}

