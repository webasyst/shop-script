<?php
/**
 * Shows v1.yaml file
 */
class shopFrontendApiYamlController extends waController
{
    public function execute()
    {
        $api_version_number = waRequest::param('api_version_number', null, 'int');
        if ($api_version_number) {
            $spec_path = wa()->getAppPath("lib/actions/frontend/frontApi/swagger/v{$api_version_number}.yaml", 'shop');
            if (file_exists($spec_path) && is_readable($spec_path)) {
                $spec = file_get_contents($spec_path);
                if ($spec) {
                    wa()->getResponse()->addHeader('Content-Type', 'application/x-yaml; charset=utf-8');
                    wa()->getResponse()->sendHeaders();
                    $api_domain_url = wa()->getRouteUrl('shop/frontend/apiErr404', true);
                    echo str_replace('http://localhost/api/v1', rtrim($api_domain_url, '/'), $spec);
                    exit;
                }
            }
        }
        throw new waException('Not found', 404);
    }
}
