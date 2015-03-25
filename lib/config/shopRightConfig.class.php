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
        $this->addItem('setscategories', _w('Can manage product sets, categories, and promos'));
        $this->addItem('importexport', _w('Can import and export data'));
        $this->addItem('reports', _w('Can view reports'));
        $this->addItem('pages', _ws('Can edit pages'));
        $this->addItem('design', _ws('Can edit design'));

        $type_model = new shopTypeModel();
        $types = $type_model->getNames();
        $this->addItem('type', _w('Can manage products'), 'selectlist', array(
            'items' => $types,
            'position'	 => 'right',
            'options'	 => array(
                self::RIGHT_READ => _w('Read only'),
                self::RIGHT_EDIT => _w('Edit and add new products only'),
                self::RIGHT_FULL => _w('Full access'),
            ),
            'hint1' => 'all_select'
        ));

        /**
         * @event rights.config
         * @param waRightConfig $this Rights setup object
         * @return void
         */
        wa()->event('rights.config', $this);
    }
}