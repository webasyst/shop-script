<?php

class shopSettingsAnalyticsAction extends waViewAction
{
    public function execute()
    {
        $settings = array(
            'reports_date_type' => wa()->getSetting('reports_date_type', 'paid', 'shop'),
        );

        if (waRequest::post()) {
            $new_settings = waRequest::post('settings', array(), 'array');
            $new_settings = array_intersect_key($new_settings, $settings);

            // Clear sales report cache if user changed the the date to base reports on
            if ($settings['reports_date_type'] != $new_settings['reports_date_type']) {
                $sales_model = new shopSalesModel();
                $sales_model->deletePeriod(null);
            }

            $app_settings_model = new waAppSettingsModel();
            foreach($new_settings as $k => $v) {
                if (!is_array($v)) {
                    $app_settings_model->set('shop', $k, $v);
                    $settings[$k] = $v;
                }
            }
        }

        $this->view->assign('settings', $settings);
    }
}
