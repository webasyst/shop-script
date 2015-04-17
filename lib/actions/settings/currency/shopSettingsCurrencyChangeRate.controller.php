<?php

class shopSettingsCurrencyChangeRateController extends waJsonController
{
    public function execute() {
        $code = waRequest::post('code', '', waRequest::TYPE_STRING_TRIM);
        if ($code) {
            $this->changeSingle($code);
        } else {
            $this->changeMultiple();
        }
    }

    protected function changeMultiple()
    {
        $currency = waRequest::post('currency', array(), 'array');
        $round_up_only = waRequest::post('round_up_only', array(), 'array');
        $rounding = waRequest::post('rounding', array(), 'array');
        $rates = waRequest::post('rate', array(), 'array');

        $currency_model = new shopCurrencyModel();
        foreach($currency as $code) {
            $update = array();
            if (isset($round_up_only[$code])) {
                $update['round_up_only'] = (int) !!$round_up_only[$code];
            }
            if (isset($rounding[$code])) {
                $update['rounding'] = ifempty($rounding[$code], null);
            }
            if ($update) {
                $currency_model->updateById($code, $update);
            }


            $rate = (float) str_replace(',', '.', ifset($rates[$code]));
            if ($rate >= 0) {
                $currency_model->changeRate($code, $rate);
            }
        }

        $this->response = $currency_model->getById($currency);
    }

    protected function changeSingle($code)
    {
        $rate = (float) str_replace(',', '.', waRequest::post('rate', '0'));

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

        $this->response = $currency_model->getById($code);
    }
}