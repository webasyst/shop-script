<?php
class shopSettingsCurrenciesSaveController extends waJsonController
{
    public function execute()
    {
        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->set('shop', 'use_product_currency', waRequest::post('use_product_currency', 0, waRequest::TYPE_INT));

        // primary currency rounding options
        $primary = wa('shop')->getConfig()->getCurrency(true);
        $currency_model = new shopCurrencyModel();
        $cur = $currency_model->getById($primary);
        $rounding = waRequest::request('primary_rounding', '', 'string');
        $round_up_only = waRequest::request('primary_round_up_only', '', 'string');
        if ($cur['rounding'] != $rounding || $cur['round_up_only'] != $round_up_only) {
            $currency_model->updateById($primary, array(
                'rounding' => ifempty($rounding),
                'round_up_only' => $round_up_only,
            ));
            $currency_model->deleteCache();
        }
    }
}
