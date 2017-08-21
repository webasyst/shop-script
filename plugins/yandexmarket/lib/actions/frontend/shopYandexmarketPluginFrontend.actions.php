<?php

class shopYandexmarketPluginFrontendActions extends waActions
{
    public function catalogAction()
    {
        /**
         * @var shopYandexmarketPlugin $plugin
         */
        $plugin = wa()->getPlugin('yandexmarket');

        $profile_helper = new shopImportexportHelper('yandexmarket');

        list($path, $profile_id) = $plugin->getInfoByHash(waRequest::param('hash'));
        if ($profile_id) {
            $profile = $profile_helper->getConfig($profile_id);
            if (!$profile) {
                throw new waException('Profile not found', 404);
            }
            $lifetime = max(0, ifset($profile['config']['lifetime'], 0));

            if ($lifetime && (!file_exists($path) || (time() - filemtime($path) > $lifetime))) {
                waRequest::setParam('profile_id', $profile_id);

                $runner = new shopYandexmarketPluginRunController();
                $result = $runner->fastExecute($profile_id);
            }

            waFiles::readFile($path, waRequest::get('download') ? 'yandexmarket.xml' : null);
        } else {
            throw new waException('File not found', 404);
        }
    }

    public function dtdAction()
    {
        waFiles::readFile(shopYandexmarketPlugin::path('shops.dtd'));
    }
}
