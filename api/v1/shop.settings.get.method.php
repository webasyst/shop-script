<?php

class shopSettingsGetMethod extends shopApiMethod
{
    protected $courier_allowed = true;

    /**
     * @var shopConfig $this ->config
     */
    protected $config;

    public function execute()
    {
        $this->config = wa('shop')->getConfig();

        $this->response = array(
            'version'               => wa('shop')->getVersion(),
            'debug_mode'            => waSystemConfig::isDebug(),
            'default_currency'      => $this->config->getCurrency(true),
            'settings'              => $this->config->getGeneralSettings(),
            'currencies'            => $this->config->getCurrencies(),
            'address_fields'        => self::getAddressSubfieldsOrder(),
            'order_states'          => self::getOrderStates(),
            'server_time'           => date('Y-m-d H:i:s'),
            'user_info'             => $this->getUserInfo(),
            'storefronts'           => shopHelper::getStorefronts(true),
            'ignore_stock_count'    => (int)wa('shop')->getSetting('ignore_stock_count'),
            'stock_counting_action' => $this->getCountingAction(),

        );
    }

    /**
     * Return stock update Settings
     * @return string
     */
    protected function getCountingAction()
    {
        if (wa('shop')->getSetting('disable_stock_count')) {
            $stock_counting_action = 'none';
        } elseif (wa('shop')->getSetting('update_stock_count_on_create_order')) {
            $stock_counting_action = 'create';
        } else {
            $stock_counting_action = 'processing';
        }

        return $stock_counting_action;
    }

    protected function getUserInfo()
    {
        $info = array(
            'id'    => null,
            'name'  => null,
            'photo' => waContact::getPhotoUrl(0, null, 50, 50, 'person'),
        );
        if ($this->courier) {
            if (!empty($this->courier['contact_id'])) {
                try {
                    $user = new waContact($this->courier['contact_id']);
                    $info['name'] = $user->getName();
                    $info['photo'] = $this->getPhotoUrl($info['id']);
                    $info['id'] = $this->courier['contact_id'];
                } catch (waException $e) {
                    // contact does not exist
                }
            }
            if (empty($info['id'])) {
                $info['name'] = $this->courier['name'];
            }
        } else {
            $info['id'] = wa()->getUser()->getId();
            $info['name'] = wa()->getUser()->getName();
            $info['photo'] = $this->getPhotoUrl($info['id']);
        }

        return $info;
    }

    /**
     * @param $id
     * @return string
     */
    public function getPhotoUrl($id)
    {
        $use_gravatar = $this->config->getGeneralSettings('use_gravatar');
        $gravatar_default = $this->config->getGeneralSettings('gravatar_default');

        $contact = new waContact($id);
        if (!$contact->get('photo') && $use_gravatar) {
            $url = shopHelper::getGravatar($contact->get('email', 'default'), 50, $gravatar_default, true);
        } else {
            $url = $contact->getPhoto(50);
        }
        return $url;
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
        foreach ($subfields as $sf) {
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
                'name'              => waLocale::fromArray(ifempty($state['name'], $id)),
                'options'           => array_merge($default_options, ifempty($state['options'], array())),
                'available_actions' => ifempty($state['available_actions'], array()),
            );
        }
        return $result;
    }
}
