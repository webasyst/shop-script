<?php

class shopWebPushNotifications
{
    protected $domain;
    protected $is_https;
    protected $settings;
    protected $push_clients_model;
    protected $user_agent;
    protected $root_url;
    protected $supported_browser = '';

    /**
     * One signal Google project number
     * @see official documentation
     * @var string
     */
    private $gcm_sender_id = '482941778795';

    const CURRENT_DOMAIN = 0;
    const SERVER_SEND_DOMAIN = 1;

    protected $options;

    public function __construct($domain = self::CURRENT_DOMAIN, $options = array())
    {
        $this->options = $options;

        if ($domain === self::CURRENT_DOMAIN) {
            $this->domain = wa()->getConfig()->getDomain();
        } else if ($domain === self::SERVER_SEND_DOMAIN) {
            $this->domain = wa()->getConfig()->getDomain();
            foreach ($this->getRawDomainSettings() as $d => $s) {
                if (!empty($s['active']) && !empty($s['app_id']) && !empty($s['rest_api_key'])) {
                    $this->domain = $d;
                    break;
                }
            }
        } else {
            $this->domain = $domain;
        }
        $this->is_https = wa()->getRequest()->isHttps();
        $this->user_agent = wa()->getRequest()->getUserAgent();
        $this->root_url = wa()->getRootUrl();
        $this->push_clients_model = new shopPushClientModel();

        $settings = $this->getDomainsSettings();
        $this->settings = $settings[$this->domain];
    }

    public function getSettings()
    {
        return $this->settings;
    }

    public function saveSettings($settings)
    {
        $this->settings = $settings;

        $root_path = wa()->getConfig()->getRootPath();

        $domain_settings = $this->getDomainsSettings();

        foreach ($domain_settings as $d => &$s) {
            $s['active'] = false;
        }
        unset($s);

        $domain_settings[$this->domain] = $settings;

        $asm = new waAppSettingsModel();
        $asm->set('shop', 'web_push_domains', json_encode($domain_settings));

        if ($this->canSendServerRequest()) {

            $manifest = array_merge(array(
                'name' => '',
                'short_name' => '',
                'start_url' => '/',
                'display' => 'standalone',
                'gcm_sender_id' => ''
            ), ifset($domain_settings[$this->domain]['manifest'], array()));

            if (empty($manifest['gcm_sender_id'])) {
                $manifest['gcm_sender_id'] = $this->gcm_sender_id;
            }

            file_put_contents($root_path . '/manifest.json', json_encode($manifest));
            file_put_contents($root_path . '/OneSignalSDKUpdaterWorker.js', "importScripts('https://cdn.onesignal.com/sdks/OneSignalSDK.js');");
            file_put_contents($root_path . '/OneSignalSDKWorker.js', "importScripts('https://cdn.onesignal.com/sdks/OneSignalSDK.js');");

        } else {
            waFiles::delete($root_path . '/manifest.json');
            waFiles::delete($root_path . '/OneSignalSDKUpdaterWorker.js');
            waFiles::delete($root_path . '/OneSignalSDKWorker.js');
        }

        $settings = $this->getDomainsSettings();
        $this->settings = $settings[$this->domain];
    }

    public function isAllowed()
    {
        return $this->settings['allowed'];
    }

    public function isActive()
    {
        return !empty($this->settings['active']);
    }

    public function isBrowserSupported()
    {
        return $this->settings['browser_supported'];
    }

    public function isOn()
    {
        return $this->isAllowed() && $this->isActive() && $this->isBrowserSupported();
    }

    public function isOff()
    {
        return !$this->isOn();
    }

    public function getAppId()
    {
        return !empty($this->settings['app_id']) ? $this->settings['app_id'] : '';
    }

    public function getSafariWebId()
    {
        return !empty($this->settings['safari_web_id']) ? $this->settings['safari_web_id'] : '';
    }

    public function getRestApiKey()
    {
        return !empty($this->settings['rest_api_key']) ? $this->settings['rest_api_key'] : '';
    }

    public function getDomain()
    {
        return $this->domain;
    }

    public function getManifest()
    {
        return !empty($this->settings['manifest']) ? $this->settings['manifest'] : array();
    }

    public function send($data)
    {
        if (!$this->canSendServerRequest()) {
            return false;
        }

        $backend_url = wa()->getConfig()->getBackendUrl();
        $url = "https://{$this->domain}/{$backend_url}/shop?action=orders";
        $notification_text = _w('New order').' '.shopHelper::encodeOrderId($data['order']['id']);

        /**
         * Send push notification from shop
         *
         * @param array $data
         * @param string $notification_text
         *
         * @event web_push_send
         */
        $event_params = [
            'data'  => &$data,
            'notification_text' => &$notification_text,
        ];

        wa('shop')->event('web_push_send', $event_params);

        $request_data = array(
            'app_id' => ifset($this->settings['app_id'], ''),
            'tags' => array(
                array('key' => 'type', 'relation' => '=', 'value' => 'web'),
                array('key' => 'module', 'relation' => '=', 'value' => 'order'),
            ),
            'url' => $url,
            'contents' => array(
                "en" => $notification_text,
            ),
            'headings' => array(
                "en" => _w("Shop orders")
            ),
            'isAnyWeb' => true
        );

        $success = true;

        try {
            $net = new waNet(array('format' => waNet::FORMAT_JSON), array(
                'Authorization' => 'Basic ' . ifset($this->settings['rest_api_key'], ''),
                'Content-Type' => 'application/json'
            ));
            $net->query("https://onesignal.com/api/v1/notifications", $request_data, waNet::METHOD_POST);
            $result = $net->getResponse();
            if (!empty($result['errors'])) {
                waLog::log('Unable to send PUSH notifications: '.wa_dump_helper($result), 'shop/webpush.log');
                $success = false;
            }
        } catch (Exception $ex) {
            $result = $ex->getMessage();
            waLog::log('Unable to send PUSH notifications: '.$result, 'shop/webpush.log');
            $success = false;
        }

        return $success;
    }

    public function getContactClientIds($contact_id = null)
    {
        $contact_id = $contact_id === null ? wa()->getUser()->getId() : $contact_id;
        $rows = $this->push_clients_model->getByField(
            array(
                'contact_id' => $contact_id,
                'type' => 'web'
            ),
            'client_id'
        );
        return array_keys($rows);
    }

    public function addClientIdToContact($client_id, $contact_id = null)
    {
        $contact_id = $contact_id === null ? wa()->getUser()->getId() : $contact_id;
        $res = $this->push_clients_model->insert(
            array(
                'create_datetime' => date('Y-m-d H:i:s'),
                'contact_id' => $contact_id,
                'client_id' => $client_id,
                'type' => 'web',
                'shop_url' => ''
            ),
            1
        );
        return !!$res;
    }

    public function deleteClientIdFromContact($client_id, $contact_id = null)
    {
        $contact_id = $contact_id === null ? wa()->getUser()->getId() : $contact_id;
        $res = $this->push_clients_model->deleteByField(
            array(
                'client_id' => $client_id,
                'contact_id' => $contact_id
            )
        );
        return !!$res;
    }

    public function deleteContactClientIds($contact_id = null)
    {
        $contact_id = $contact_id === null ? wa()->getUser()->getId() : $contact_id;
        $this->push_clients_model->deleteByField('contact_id', $contact_id);
    }

    public function isSupportedBrowserIsChrome()
    {
        return $this->supported_browser === 'chrome';
    }

    public function isSupportedBrowserIsFirefox()
    {
        return $this->supported_browser === 'firefox';
    }

    public function isSupportedBrowserIsSafari()
    {
        return $this->supported_browser === 'safari';
    }

    public function isTuned()
    {
        if (!$this->getAppId() || !$this->getRestApiKey()) {
            return false;
        }
        if ($this->isSupportedBrowserIsChrome()) {
            return !!$this->getGoogleProjectNumber();
        }
        if ($this->isSupportedBrowserIsSafari()) {
            return !!$this->getSafariWebId();
        }
        return $this->isSupportedBrowserIsFirefox();
    }

    public function getGoogleProjectNumber()
    {
        if (empty($this->settings['manifest']['gcm_sender_id'])) {
            $this->settings['manifest']['gcm_sender_id'] = $this->gcm_sender_id;
        }
        return $this->settings['manifest']['gcm_sender_id'];
    }

    public function canSendServerRequest()
    {
        return $this->isActive() && $this->getAppId() && $this->getRestApiKey();
    }

    protected function getRawDomainSettings()
    {
        // extract domains settings
        $asm = new waAppSettingsModel();
        $web_push_domains = json_decode($asm->get('shop', 'web_push_domains', '{}'), true);
        if (!is_array($web_push_domains)) {
            $web_push_domains = array();
        }
        return $web_push_domains;
    }

    protected function getDomainsSettings()
    {
        $web_push_domains = $this->getRawDomainSettings();

        // full fill current domain settings
        $web_push_domains[$this->domain] = ifset($web_push_domains[$this->domain], array());
        $web_push_domains[$this->domain]['manifest'] = ifset($web_push_domains[$this->domain]['manifest'], array());
        $web_push_domains[$this->domain]['manifest'] = array_merge(array(
            'name' => '',
            'short_name' => '',
            'start_url' => '/',
            'display' => 'standalone',
            'gcm_sender_id' => ''
        ), $web_push_domains[$this->domain]['manifest']);

        if (empty($web_push_domains[$this->domain]['manifest']['gcm_sender_id'])) {
            $web_push_domains[$this->domain]['manifest']['gcm_sender_id'] = $this->gcm_sender_id;
        }

        // check allowance
        $web_push_domains[$this->domain]['allowed'] = false;
        if (($this->root_url === '/' || strlen($this->root_url) <= 0) && $this->is_https) {
            $web_push_domains[$this->domain]['allowed'] = true;
        }

        // check browser support
        $user_agent = strtolower($this->user_agent);
        $browser_supported = false;
        if (preg_match('~chrome/([\d]{1,3})\.~i', $user_agent, $m) && stristr($user_agent, 'edge/') === false && stristr($user_agent, 'opr/') === false) {
            if ($m[1] >= 42) {
                $browser_supported = true;
                $this->supported_browser = 'chrome';
            }
        } else if (preg_match('~firefox/([\d]{1,3})\.~i', $user_agent, $m)) {
            if ($m[1] >= 44) {
                $browser_supported = true;
                $this->supported_browser = 'firefox';
            }
        } else if (stristr($user_agent, 'mac os x') !== false && stristr($user_agent, 'safari/') !== false && preg_match('~version/([\d]{1,3})\.([\d]{1,3})~i', $user_agent, $m)) {
            if ($m[1] >= 7) {
                $browser_supported = true;
                $this->supported_browser = 'safari';
            }
        }
        $web_push_domains[$this->domain]['browser_supported'] = $browser_supported;

        // check activity by checking manifest
        $web_push_domains[$this->domain]['active'] = false;
        $root_path = wa()->getConfig()->getRootPath();
        if (file_exists($root_path . '/OneSignalSDKUpdaterWorker.js') && file_exists($root_path . '/OneSignalSDKWorker.js')) {
            $web_push_domains[$this->domain]['active'] = true;
        }
        if (!$web_push_domains[$this->domain]['active'] && file_exists($root_path . '/manifest.json')) {
            waFiles::delete($root_path . '/manifest.json');
        }

        return $web_push_domains;
    }
}
