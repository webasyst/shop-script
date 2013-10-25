<?php

class shopRightConfig extends waRightConfig
{
    public function init()
    {
        $this->addItem('orders', _w('Can manage orders'));
        $this->addItem('settings', _w('Can manage settings'));
        $this->addItem('reports', _w('Can view reports'));
        $this->addItem('pages', _ws('Can edit pages'));
        $this->addItem('design', _ws('Can edit design'));

        $type_model = new shopTypeModel();
        $types = $type_model->getNames();
        $this->addItem('type', _w('Can manage products'), 'list', array('items' => $types, 'hint1' => 'all_checkbox'));

        /**
         * @event rights.config
         * @param waRightConfig $this Rights setup object
         * @return void
         */
        wa()->event('rights.config', $this);
    }
}