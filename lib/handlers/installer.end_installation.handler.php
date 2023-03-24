<?php

class shopInstallerEnd_installationHandler extends waEventHandler
{
    /**
     * @param $install_data
     * @param $event_name
     * @return array
     * @throws waException
     */
    public function execute(&$install_data, $event_name = null)
    {
        $result = [];
        $redirect = false;

        if (!wa()->appExists('installer')) {
            return $result;
        }
        wa('installer');
        $plugins_data = installerHelper::getStoreProductsData(array_column($install_data, 'real_slug'), ['tags'], true);
        foreach ($plugins_data as $key => $plugin_data) {
            foreach ($install_data as &$inst_data) {
                if ($plugin_data['slug'] == $inst_data['real_slug']) {
                    $inst_data['app_id'] = $plugin_data['app_id'];
                    $inst_data['id']     = $plugin_data['ext_id'];
                    $inst_data['type']   = $plugin_data['type'];
                    $inst_data['tags']   = $plugin_data['tags'];
                }
            }
        }
        foreach ($install_data as $data) {
            $app_id       = ifset($data, 'app_id', null);
            $plugin_id    = ifset($data, 'id', null);
            $tags         = ifset($data, 'tags', []);
            $support_frac = shopHelper::getShopSupportJson($data['target']);

            if (in_array(shopSettingsCompatibilityAction::TAG_SHOP_PREMIUM_YES, $tags)) {
                $compatibility = true;
            } elseif (in_array(shopSettingsCompatibilityAction::TAG_SHOP_PREMIUM_NO, $tags)) {
                $compatibility = false;
            } elseif (!empty($support_frac)) {
                $compatibility = (shopSettingsCompatibilityAction::COMPATIBILITY['yes'] === ifset($support_frac, 'compatibility', '0'));
            } else {
                $compatibility = false;
            }

            if (
                !$compatibility
                && isset($data['type'])
                && (shopFrac::isEnabled() || shopUnits::isEnabled())
            ) {
                /**
                 * отключение продукта
                 * возвращается true или сообщение об ошибке
                 */
                if ($data['type'] === 'PLUGIN') {
                    installerHelper::pluginSetStatus($app_id, $plugin_id);
                } else {
                    installerHelper::appSetStatus($app_id, true);
                }
                $redirect = true;
            }
        }

        if ($redirect) {
            $result = [
                'redirect' => wa()->getRootUrl(true).wa()->getConfig()->getBackendUrl().'/shop/?action=settings#/compatibility/'
            ];
        }

        return $result;
    }
}
