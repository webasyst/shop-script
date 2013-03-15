<?php
class shopYandexmarketPluginFrontendActions extends waActions
{

    public function catalogAction()
    {
        $path = null;
        if (waRequest::param('hash') == shopYandexmarketPlugin::uuid()) {
            $path = shopYandexmarketPlugin::path();
        }
        waFiles::readFile($path, waRequest::get('download') ? 'yandexmarket.xml' : null);
    }

    public function dtdAction()
    {
        waFiles::readFile(shopYandexmarketPlugin::path('shops.dtd'));
    }
}
