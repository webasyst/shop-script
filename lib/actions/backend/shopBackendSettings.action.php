<?php
class shopBackendSettingsAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w("Access denied"));
        }

        $this->setLayout(new shopBackendLayout());

        //TODO get dynamic sections lists
        /**
         * @event backend_settings
         * @return array[string]array $return[%plugin_id%] array of html output
         * @return array[string][string]string $return[%plugin_id%]['sidebar_top_li'] html output
         * @return array[string][string]string $return[%plugin_id%]['sidebar_middle_li'] html output
         * @return array[string][string]string $return[%plugin_id%]['sidebar_bottom_li'] html output
         */

        $model = new waAppSettingsModel();
        $locale = $model->get('webasyst', 'locale');
        $show_marketplaces = (ifempty($locale, wa()->getLocale()) === "ru_RU" && !!$this->getUser()->getRights('installer', 'backend'));

        $this->view->assign([
            "show_marketplaces" => $show_marketplaces,
            "is_premium" => shopLicensing::isPremium(),
            "backend_settings" => wa()->event('backend_settings'),
        ]);
    }
}
