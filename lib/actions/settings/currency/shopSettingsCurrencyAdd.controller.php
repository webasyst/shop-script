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
        $this->response = $currency_model->add($code);
        if (!$this->response) {
            $this->errors[] = _w("Unknown code");
            return;
        }
    }
}
