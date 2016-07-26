<?php

class shopSettingsGetMethod extends shopApiMethod
{
    public function execute()
    {
        $config = wa('shop')->getConfig();
        $this->response = array(
            'version' => wa('shop')->getVersion(),
            'debug_mode' => waSystemConfig::isDebug(),
            'default_currency' => $config->getCurrency(true),
            'settings' => $config->getGeneralSettings(),
            'currencies' => $config->getCurrencies(),
            'order_states' => self::getOrderStates(),
        );
    }

    protected static function getOrderStates()
    {
        $result = array();
        $cfg = shopWorkflow::getConfig();
        $default_options = array(
            'icon' => '',
            'style' => array(),
        );
        foreach(ifset($cfg['states'], array()) as $id => $state) {
            $result[] = array(
                'id' => $id,
                'name' => ifempty($state['name'], $id),
                'options' => array_merge($default_options, ifempty($state['options'], array())),
                'available_actions' => ifempty($state['available_actions'], array()),
            );
        }
        return $result;
    }
}