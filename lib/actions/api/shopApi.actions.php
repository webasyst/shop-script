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

        $this->display([
            'domains' => $routes
        ]);
    }

    public function switchAction()
    {
        $this->checkRight();
        if ($id = waRequest::post('id', 0, waRequest::TYPE_INT)) {
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
}
