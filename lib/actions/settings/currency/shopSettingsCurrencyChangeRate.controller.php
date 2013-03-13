<?php

class shopSettingsCurrencyChangeRateController extends waJsonController
{
    public function execute() {
        $code = waRequest::post('code', '', waRequest::TYPE_STRING_TRIM);
        $rate = (float)waRequest::post('rate', 0);

        if (!$code) {
            $this->errors[] = _w("Error when change currency");
            return;
        }
        if ($rate <= 0) {
            $this->errors[] = _w("Error when change currency");
        }
        $currency_model = new shopCurrencyModel();
        if (!$currency_model->changeRate($code, $rate)) {
            $this->errors[] = _w("Error when change currency");
            return;
        }
    }
}