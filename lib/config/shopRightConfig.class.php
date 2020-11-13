<?php

class shopRightConfig extends waRightConfig
{
    const RIGHT_READ = 0;
    const RIGHT_EDIT = 1;
    const RIGHT_FULL = 2;

    public function init()
    {
        $this->addItem('orders', _w('Can manage orders'));
        $this->addItem('customers', _w('Can manage customers'));
        $this->addItem('settings', _w('Can manage settings'));
        $this->addItem('services', _w('Can manage services'));
        $this->addItem('setscategories', _w('Can manage product sets, categories'));
        $this->addItem('importexport', _w('Can import and export data'));
        $this->addItem('marketing', _w('Can use marketing tools'));
        $this->addItem('setup_marketing', _w('Can set up marketing tools'));
        $this->addItem('reports', _w('Can view reports'));
        $this->addItem('pages', _ws('Can edit pages'));
        $this->addItem('design', _ws('Can edit design'));

        $type_model = new shopTypeModel();
        $types = $type_model->getNames();
        $this->addItem('type', _w('Can manage products'), 'selectlist', array(
            'items'    => $types,
            'position' => 'right',
            'options'  => array(
                self::RIGHT_READ => _w('Read only'),
                self::RIGHT_EDIT => _w('Edit products and add new products'),
                self::RIGHT_FULL => _w('Full access'),
            ),
            'hint1'    => 'all_select',
        ));

        $workflow = new shopWorkflow();
        $actions = $workflow->getAvailableActions();
        $items = array();
        $internal_actions = array(
            'settle',
            'capture',
            'cancel',
        );

        foreach ($actions as $action_id => $action) {
            if (empty($action['internal'])
                || !empty($action['rights'])
                || in_array($action_id, $internal_actions)
            ) {

                if (isset($action['name'])) {
                    $action_name = $action['name'];
                } elseif (isset($action['classname'])) {
                    $action_name = $action['classname'];
                } else {
                    $action_name = $action_id;
                }
                
                $items[$action_id] = $action_name;
            }
        }

        $this->addItem('workflow_actions', _w('Can perform order actions'), 'list', array(
            'items'    => $items,
            'position' => 'right',
            'hint1'    => 'all_checkbox',
        ));

        /**
         * @event rights.config
         * @param waRightConfig $this Rights setup object
         * @return void
         */
        wa('shop')->event('rights.config', $this);
    }
}
