<?php

class shopSettingsCurrencyChangeController extends waJsonController
{
    public function execute()
    {
        $code = waRequest::post('code', '', waRequest::TYPE_STRING_TRIM);

        if (!$code) {
            $this->errors[] = _w("Unknown currency");
            return;
        }

        $currency_model = new shopCurrencyModel();
        if (!$currency_model->setPrimaryCurrency($code, (bool)waRequest::post('convert'))) {
            $this->errors[] = _w("Error when change");
            return;
        }
    }
}