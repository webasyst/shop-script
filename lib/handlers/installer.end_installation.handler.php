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
            $plugins_data[$plugin_data['slug']] = $plugin_data;
            unset($plugins_data[$key]);
        }
        foreach ($install_data as $data) {
            $app_id       = ifset($data, 'info', 'app_id', null);
            $plugin_id    = ifset($data, 'info', 'id', null);
            $tags         = ifset($plugins_data, $data['real_slug'], 'tags', []);
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
                && (shopFrac::isEnabled() || shopUnits::isEnabled())
            ) {
                /**
                 * отключение плагина
                 * installerHelper::pluginSetStatus($app_id, $plugin_id);
                 * возвращает true или сообщение об ошибке
                 */
                installerHelper::pluginSetStatus($app_id, $plugin_id);
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
