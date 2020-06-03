<?php
class shopSettingsCurrenciesSaveController extends waJsonController
{
    public function execute()
    {
        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->set('shop', 'use_product_currency', waRequest::post('use_product_currency', 0, waRequest::TYPE_INT));
        $app_settings_model->set('shop', 'round_discounts', waRequest::post('round_discounts', 0, 'int'));
        $app_settings_model->set('shop', 'round_shipping', waRequest::post('round_shipping', 0, 'int'));
        $app_settings_model->set('shop', 'round_services', waRequest::post('round_services', 0, 'int'));

        switch(waRequest::post('discount_distribution', '', 'string')) {
            case 'increase_discount':
                $app_settings_model->set('shop', 'discount_distrbution_rounding', 1);
                $app_settings_model->set('shop', 'discount_distrbution_split', 0);
                break;
            case 'split_order_item':
                $app_settings_model->set('shop', 'discount_distrbution_rounding', 0);
                $app_settings_model->set('shop', 'discount_distrbution_split', 1);
                break;
            case 'increase_discount_no_rounding':
                $app_settings_model->set('shop', 'discount_distrbution_rounding', 0);
                $app_settings_model->set('shop', 'discount_distrbution_split', 0);
                break;
            case 'split_order_item_with_rounding':
                $app_settings_model->set('shop', 'discount_distrbution_rounding', 1);
                $app_settings_model->set('shop', 'discount_distrbution_split', 1);
                break;
        }
    }
}
