<?php

/**
 * Helper for shopSettingsCheckoutContactFormAction.
 * One <tr> row with field data and editor.
 */
class shopSettingsCheckoutContactFormRowAction extends waViewAction
{
    public function execute()
    {
        $f = waRequest::param('f');
        $fid = waRequest::param('fid');
        $parent = waRequest::param('parent');
        $css_class = waRequest::param('css_class');

        $new_field = false;
        if (!($f instanceof waContactField)) {
            $new_field = true;
            $f = new waContactStringField($fid, '', array(
                'app_id' => 'shop',
            ));
        }

        $prefix = 'options';
        if ($parent) {
            $prefix .= '['.$parent.'][fields]';
        }

        static $ftypes = null;
        if ($ftypes === null) {
            $ftypes = array(
                'NameSubfield' => _w('Text (input)'),
                'Email' => _w('Text (input)'),
                'Address' => _w('Address'),
                'Branch' => _w('Selectable (radio)'),
                'Text' => _w('Text (textarea)'),
                'String' => _w('Text (input)'),
                'Select' => _w('Select'),
                'Phone' => _w('Text (input)'),
                'IM' => _w('Text (input)'),
                'Url' => _w('Text (input)'),
                'SocialNetwork' => _w('Text (input)'),
                'Date' => _w('Date'),
                'Birthday' => _w('Date'),
                'Composite' => _w('Composite field group'),
                'Checkbox' => _w('Checkbox'),
                'Number' => _w('Number'),
                'Region' => _w('Region'),
                'Country' => _w('Country'),
                'Hidden' => _w('Hidden field'),
                'Name' => _w('Full name'),
            );
        }

        $form = waContactForm::loadConfig(array(
            '_default_value' => $f,
        ), array(
            'namespace' => "{$prefix}[{$fid}]"
        ));

        // Get default value
        $default_value = null;
        if (!$new_field && $f->getParameter('_disabled')) {
            $settings = wa('shop')->getConfig()->getCheckoutSettings();
            if (!isset($settings['contactinfo'])) {
                $settings = wa('shop')->getConfig()->getCheckoutSettings(true);
            }
            $fields_config = ifset($settings['contactinfo']['fields'], array());
            if ($parent) {
                if (!empty($fields_config[$parent]['fields'][$fid]['hidden'])) {
                    $default_value = ifset($fields_config[$parent]['fields'][$fid]['value']);
                }
            } else {
                if (!empty($fields_config[$fid]['hidden'])) {
                    $default_value = ifset($fields_config[$fid]['value']);
                }
            }
            if ($default_value !== null) {
                $form->setValue('_default_value', $default_value);
            }
        }

        $this->view->assign('f', $f);
        $this->view->assign('fid', $fid);
        $this->view->assign('form', $form);
        $this->view->assign('parent', $parent);
        $this->view->assign('prefix', $prefix);
        $this->view->assign('uniqid', str_replace('.', '-', 'f'.uniqid('f', true)));
        $this->view->assign('new_field', $new_field);
        $this->view->assign('tr_classes', $css_class);
        $this->view->assign('default_value', $default_value);
        $this->view->assign('ftypes', $ftypes);
    }
}

