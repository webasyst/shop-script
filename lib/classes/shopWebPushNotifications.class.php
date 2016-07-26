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

    public function __construct()
    {
        $this->domain = wa()->getConfig()->getDomain();
        $this->is_https = wa()->getRequest()->isHttps();
        $this->user_agent = wa()->getRequest()->getUserAgent();
        $this->root_url = wa()->getRootUrl();

        $settings = $this->getDomainsSettings();
        $this->settings = $settings[$this->domain];
        $this->push_clients_model = new shopPushClientModel();
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
        $domain_settings[$this->domain] = $settings;

        $asm = new waAppSettingsModel();
        $asm->set('shop', 'web_push_domains', json_encode($domain_settings));

        $delete_files = false;

        if (!empty($domain_settings[$this->domain]['active'])) {

            $manifest = array_merge(array(
                'name' => '',
                'short_name' => '',
                'start_url' => '/',
                'display' => 'standalone',
                'gcm_sender_id' => ''
            ), ifset($domain_settings[$this->domain]['manifest'], array()));

            if (!empty($manifest['gcm_sender_id'])) {
                file_put_contents($root_path . '/manifest.json', json_encode($manifest));
                file_put_contents($root_path . '/OneSignalSDKUpdaterWorker.js', "importScripts('https://cdn.onesignal.com/sdks/OneSignalSDK.js');");
                file_put_contents($root_path . '/OneSignalSDKWorker.js', "importScripts('https://cdn.onesignal.com/sdks/OneSignalSDK.js');");
            } else {
                $delete_files = true;
            }
        } else {
            $delete_files = true;
        }

        if ($delete_files) {
            waFiles::delete($root_path . '/manifest.json');
            waFiles::delete($root_path . '/OneSignalSDKUpdaterWorker.js');
            waFiles::delete($root_path . '/OneSignalSDKWorker.js');
        }
        
    }

    public function isAllowed()
    {
        return $this->settings['allowed'];
    }

    public function isActive()
    {
        return $this->settings['active'];
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
        $backend_url = wa()->getConfig()->getBackendUrl();
        $url = "https://{$this->domain}/{$backend_url}/shop?action=orders";
        $request_data = array(
            'app_id' => ifset($this->settings['app_id'], ''),
            'tags' => array(
                array('key' => 'type', 'relation' => '=', 'value' => 'web'),
                array('key' => 'module', 'relation' => '=', 'value' => 'order'),
            ),
            'url' => $url,
            'contents' => array(
                "en" => _w('New order').' '.shopHelper::encodeOrderId($data['order']['id']),
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
        } catch (waException $ex) {
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
                'contact_id' => $contact_id,
                'client_id' => $client_id,
                'type' => 'web'
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

    public function isSupportedBrowserIsChromeOrFirefox()
    {
        return $this->supported_browser === 'chrome' || $this->supported_browser === 'firefox';
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
        if ($this->isSupportedBrowserIsChromeOrFirefox()) {
            return !!$this->getGoogleProjectNumber();
        }
        if ($this->isSupportedBrowserIsSafari()) {
            return !!$this->getSafariWebId();
        }
        return false;
    }

    public function getGoogleProjectNumber()
    {
        return $this->settings['manifest']['gcm_sender_id'];
    }

    protected function getDomainsSettings()
    {
        // extract domains settings
        $asm = new waAppSettingsModel();
        $web_push_domains = json_decode($asm->get('shop', 'web_push_domains', '{}'), true);
        if (!is_array($web_push_domains)) {
            $web_push_domains = array();
        }

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

        // check allowance
        $web_push_domains[$this->domain]['allowed'] = false;
        if (($this->root_url === '/' || strlen($this->root_url) <= 0) && $this->is_https) {
            $web_push_domains[$this->domain]['allowed'] = true;
        }

        // check activity by checking manifest
        $web_push_domains[$this->domain]['active'] = false;

        // check browser support
        $user_agent = strtolower($this->user_agent);
        $browser_supported = false;
        if (preg_match('~chrome/([\d]{1,3})\.~', $user_agent, $m) && strpos($user_agent, 'edge/') === false&& strpos($user_agent, 'opr/') === false) {
            if ($m[1] >= 42) {
                $browser_supported = true;
                $this->supported_browser = 'chrome';
            }
        } else if (preg_match('~firefox/([\d]{1,3})\.~', $user_agent, $m)) {
            if ($m[1] >= 44) {
                $browser_supported = true;
                $this->supported_browser = 'firefox';
            }
        } else if (strpos($user_agent, 'mac os x') !== false && strpos($user_agent, 'safari/') !== false && preg_match('~version/([\d]{1,3})\.([\d]{1,3})~', $user_agent, $m)) {
            if ($m[1] >= 7 && $m[2] >= 1) {
                $browser_supported = true;
                $this->supported_browser = 'safari';
            }
        }
        $web_push_domains[$this->domain]['browser_supported'] = $browser_supported;

        $manifest = array();
        $root_path = wa()->getConfig()->getRootPath();
        if (file_exists($root_path .  '/manifest.json')) {
            $manifest = file_get_contents($root_path . '/manifest.json');
            $manifest = json_decode($manifest, true);
            if (!is_array($manifest)) {
                $manifest = array();
            }
        }

        if (!empty($manifest['gcm_sender_id']) && $manifest['gcm_sender_id'] === $web_push_domains[$this->domain]['manifest']['gcm_sender_id']) {
            $web_push_domains[$this->domain]['active'] = true;
        }

        return $web_push_domains;
    }
}