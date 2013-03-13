<?php

class shopSettingsCurrencyAddController extends waJsonController
{
    public function execute()
    {
        $code = waRequest::post('code', '', waRequest::TYPE_STRING_TRIM);

        if (!$code) {
            $this->errors[] = _w("Unknown code");
            return;
        }
        $currency_model = new shopCurrencyModel();
        if (!$currency_model->add($code)) {
            $this->errors[] = _w("Unknown code");
            return;
        }
    }
}