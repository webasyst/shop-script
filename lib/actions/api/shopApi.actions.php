<?php

class shopApiActions extends waActions
{
    private function checkRight()
    {
        if (!$this->getRights('design')) {
            throw new waRightsException('Access denied');
        }
    }

    public function defaultAction()
    {
        $this->checkRight();
        $this->setTemplate('templates/actions/api/Api.html', true);
        $routes = wa()->getRouting()->getByApp($this->getAppId());

        foreach ($routes as $domain => &$rr) {
            foreach ($rr as &$r) {
                $r['yaml_url'] = wa()->getRouteUrl('shop/frontend/apiYaml', [
                    'api_version_number' => '1',
                ], true, $domain, $r['url']);
            }
        }
        unset($rr, $r);

        $antispam_key = wa()->getSetting('headless_api_antispam_key', '', 'shop');
        if (!$antispam_key) {
            $antispam_key = self::generateAntispamKey();
            (new waAppSettingsModel())->set('shop', 'headless_api_antispam_key', $antispam_key);
        }

        $this->display([
            'domains' => $routes,
            'antispam_key' => $antispam_key,
            'antispam_enabled' => wa()->getSetting('headless_api_antispam_enabled', false, 'shop'),
        ]);
    }

    public function switchAction()
    {
        $this->checkRight();
        $id = waRequest::post('id', null, waRequest::TYPE_STRING);
        if ($id !== null) {
            $on = waRequest::post('on', 0, waRequest::TYPE_INT);
            $domain = waRequest::post('domain', '', waRequest::TYPE_STRING_TRIM);

            $path = $this->getConfig()->getPath('config', 'routing');
            if (file_exists($path)) {
                $routes = include($path);
                if (!is_writable($path)) {
                    return;
                }
                if (isset($routes[$domain][$id])) {
                    $routes[$domain][$id]['storefront_mode'] = ($on ? 'storefront_api' : '');
                    waUtils::varExportToFile($routes, $path);
                }
            }
        }
    }

    public function antispamAction()
    {
        $app_settings_model = new waAppSettingsModel();

        $on = waRequest::post('on', null);
        if ($on !== null) {
            $app_settings_model->set('shop', 'headless_api_antispam_enabled', $on ? 1 : null);
        }

        $antispam_key = waRequest::post('key', null, 'string_trim');
        if ($antispam_key !== null) {
            $app_settings_model->set('shop', 'headless_api_antispam_key', $antispam_key);
        }
    }

    public static function generateAntispamKey()
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890-_!#@$%^&*+';
        $result = '';
        while (strlen($result) < 32) {
            $result .= $alphabet[mt_rand(0, strlen($alphabet) - 1)];
        }
        return $result;
    }
}
