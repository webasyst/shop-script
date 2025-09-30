<?php
/** @since 11.6.0 */
class shopSettingsEnableHeadlessMethod extends shopApiMethod
{
    protected $courier_allowed = false;
    protected $method = 'POST';

    public function execute()
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new waAPIException('access_denied', 403);
        }

        $storefront_url = waRequest::request('storefront_url', null, 'string');
        
        $storefronts = shopHelper::getStorefronts(true);
        foreach ($storefronts as $s) {
            if ($s['url'] === $storefront_url) {
                $this->enableForStorefront($s);
                $this->response = ['status' => 'ok'];
                return;
            }
        }

        throw new waAPIException('storefront_not_found', 'Storefront not found', 404);
    }

    protected function enableForStorefront($s)
    {
        if (!empty($s['route']['storefront_mode'])) {
            return; // already enabled
        }
        
        $path = wa('shop')->getConfig()->getPath('config', 'routing');
        if (!file_exists($path) && is_writable($path)) {
            throw new waAPIException('server_error', 'Unable to save settings', 500);
        }
        $routes = include($path);
        if (isset($routes[$s['domain']])) {
            foreach ($routes[$s['domain']] as &$route) {
                if ($route['url'] === $s['route']['url']) {
                    if (empty($route['storefront_mode'])) {
                        $route['storefront_mode'] = 'storefront_api';
                        waUtils::varExportToFile($routes, $path);
                    }
                    return;
                }
            }
        }
    }
}
