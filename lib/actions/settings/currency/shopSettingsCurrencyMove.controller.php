<?php

class shopSettingsCurrencyMoveController extends waJsonController
{
    public function execute()
    {
        $item = waRequest::post('item', '', waRequest::TYPE_STRING_TRIM);
        $before = waRequest::post('before', null, waRequest::TYPE_STRING_TRIM);

        if (!$item) {
            $this->errors[] = _w("Error when move");
            return;
        }

        $currency_model = new shopCurrencyModel();
        if (!$currency_model->move($item, $before)) {
            $this->errors[] = _w("Error when move");
            return;
        }
    }
}