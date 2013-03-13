<?php

class shopSettingsCurrencyDeleteController extends waJsonController
{
    public function execute()
    {
        $code = waRequest::get('code', '', waRequest::TYPE_STRING_TRIM);
        $to = waRequest::post('to', null, waRequest::TYPE_STRING_TRIM);

        if ($code == $this->getConfig()->getCurrency()) {
            $this->errors[] = "Couldn't delete primary currency";
            return;
        }

        if (!$code) {
            throw new waException(_w("Unknown currency"));
        }

        $currency_model = new shopCurrencyModel();
        $currency_model->removeCurrency($code, $to);
    }
}