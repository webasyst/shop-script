<?php

class shopSettingsGetMethod extends shopApiMethod
{
    protected $courier_allowed = true;
    public function execute()
    {
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        $this->response = array(
            'version'          => wa('shop')->getVersion(),
            'debug_mode'       => waSystemConfig::isDebug(),
            'default_currency' => $config->getCurrency(true),
            'settings'         => $config->getGeneralSettings(),
            'currencies'       => $config->getCurrencies(),
            'address_fields'   => self::getAddressSubfieldsOrder(),
            'order_states'     => self::getOrderStates(),
        );
    }

    protected static function getAddressSubfieldsOrder()
    {
        $f = waContactFields::get('address');
        if (!$f || !$f instanceof waContactField) {
            return array();
        }
        $subfields = $f->getParameter('fields');
        if (!$subfields || !is_array($subfields)) {
            return array();
        }
        $result = array();
        foreach($subfields as $sf) {
            if (!$sf instanceof waContactHiddenField) {
                $result[] = $sf->getId();
            }
        }
        return $result;
    }

    protected static function getOrderStates()
    {
        $result = array();
        $cfg = shopWorkflow::getConfig();
        $default_options = array(
            'icon'  => '',
            'style' => array(),
        );
        foreach (ifset($cfg['states'], array()) as $id => $state) {
            $result[] = array(
                'id'                => $id,
                'name'              => ifempty($state['name'], $id),
                'options'           => array_merge($default_options, ifempty($state['options'], array())),
                'available_actions' => ifempty($state['available_actions'], array()),
            );
        }
        return $result;
    }
}
