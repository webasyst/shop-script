<?php
/**
 * Sales channels / Site / Seo section in main menu
 */
class shopSeoActions extends waActions
{
    public function defaultAction()
    {
        $this->checkRight();
        $this->setTemplate('templates/actions/seo/Seo.html', true);
        $routes = wa()->getRouting()->getByApp($this->getAppId());

        $theme_exists = [];
        foreach ($routes as $domain => &$rr) {
            foreach ($rr as $route_id => &$r) {
                $theme = ifset($r, 'theme', 'default');
                if (!isset($theme_exists[$theme])) {
                    $theme_exists[$theme] = waTheme::exists($theme, 'shop');
                }
                if (!$theme_exists[$theme]) {
                    $theme = 'default';
                }
                $r['settings_url'] = self::getStorefrontSettingsUrl($domain, $theme, $route_id);
            }
        }
        unset($rr, $r);

        $installer_url = null;
        if (wa()->getUser()->isAdmin('installer')) {
            $installer_url = $this->getConfig()->getBackendUrl(true);
            $installer_url .= 'installer/?module=plugins&action=view&slug=shop&filter[tag]=seo';
        }

        $backend_storefront_seo = wa('shop')->event('backend_storefront_seo', ref([
            'routes' => &$routes,
        ]));

        $this->display([
            'domains' => $routes,
            'installer_url' => $installer_url,
            'backend_storefront_seo' => $backend_storefront_seo,
            'can_add_storefronts' => $this->hasRightsAddStorefront(),
        ]);
    }

    public function dialogAction()
    {
        $this->checkRight();
        if (!$this->hasRightsAddStorefront()) {
            throw new waRightsException('Access denied');
        }
        $this->setTemplate('templates/actions/seo/DialogAddStorefront.html', true);

        $this->display([
            'domains' => $this->getDomains(),
            'themes' => $this->getThemes(),
        ]);
    }

    public function createStorefrontAction()
    {
        $this->checkRight();
        if (!$this->hasRightsAddStorefront()) {
            throw new waRightsException('Access denied');
        }
        $domain = waRequest::request('domain', null, 'string');
        $theme = waRequest::request('theme', null, 'string');
        $url = ltrim(rtrim(waRequest::request('url', '', 'string_trim'), '/*').'/*', '/');

        $errors = [];
        if (!$domain) {
            $errors[] = [
                'error' => 'field_required',
                'field' => 'domain',
                'error_description' => _wp('This field is required.'),
            ];
        }
        if (!$theme) {
            $errors[] = [
                'error' => 'field_required',
                'field' => 'theme',
                'error_description' => _wp('This field is required.'),
            ];
        }
        if ($errors) {
            return $this->displayJson(null, $errors);
        }

        $routes = [];
        $path = $this->getConfig()->getPath('config', 'routing');
        if (file_exists($path)) {
            if (!is_writable($path)) {
                return $this->displayJson(null, [[
                    'error' => 'routing_not_writable',
                    'error_description' => 'Unable to write to file '.$path,
                ]]);
            }
            $routes = include($path);
        }
        if (!isset($routes[$domain])) {
            return $this->displayJson(null, [[
                'error' => 'domain_not_found',
                'error_description' => 'Unknown domain',
            ]]);
        }
        if (!is_array($routes[$domain])) {
            return $this->displayJson(null, [[
                'error' => 'domain_is_alias',
                'error_description' => 'Domain must not be an alias',
            ]]);
        }
        foreach ($routes[$domain] as $r) {
            if (ifset($r, 'url', null) === $url) {
                return $this->displayJson(null, [[
                    'error' => 'url_already_exists',
                    'error_description' => sprintf_wp('URL %s is already taken.', $url),
                    'field' => 'url',
                ]]);
            }
        }

        $new_route = [
            'url' => $url,
            'app' => 'shop',
            'theme' => $theme,
            'theme_mobile' => $theme,
        ];
        $routing_params = wa('shop')->getConfig()->getInfo('routing_params');
        if ($routing_params && is_array($routing_params)) {
            $new_route += $routing_params;
        }
        if ($url === '*') {
            $routes[$domain][] = $new_route;
        } else {
            array_unshift($routes[$domain], $new_route);
        }
        waUtils::varExportToFile($routes, $path);

        foreach ($routes[$domain] as $k => $r) {
            if (ifset($r, 'url', null) === $url) {
                $route_key = $k;
                break;
            }
        }

        $redirect_url = self::getStorefrontSettingsUrl($domain, $theme, $route_key);
        return $this->displayJson([
            'redirect' => $redirect_url,
        ]);
    }

    protected function getDomains()
    {
        $domains = [];
        $routing = wa()->getRouting();
        foreach ($routing->getDomains() as $d) {
            if (!$routing->isAlias($d)) {
                $domains[$d] = waIdna::dec($d);
            }
        }
        return $domains;
    }

    protected function getThemes()
    {
        $result = [];
        foreach (wa()->getThemes('shop') as $id => $t) {
            $result[$id] = $t['name'];
        }
        return $result;
    }

    protected static function getStorefrontSettingsUrl($domain, $theme, $route_id)
    {
        return sprintf(
            '%s?action=storefronts#/design/theme=%s&domain=%s&route=%s&action=settings',
            wa()->getAppUrl('shop'),
            $theme,
            $domain,
            $route_id
        );
    }

    protected function hasRightsAddStorefront()
    {
        return wa()->getUser()->isAdmin('shop') || wa()->getUser()->isAdmin('site');
    }

    protected function checkRight()
    {
        if (!$this->getRights('design')) {
            throw new waRightsException('Access denied');
        }
    }
}
