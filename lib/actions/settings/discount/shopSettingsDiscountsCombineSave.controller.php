<?php

/**
 * Save setting: how to conbine several applicable discounts, max or sum.
 */
class shopSettingsDiscountsCombineSaveController extends waJsonController
{
    public function execute()
    {
        $asm = new waAppSettingsModel();
        $asm->set('shop', 'discounts_combine', waRequest::request('value') === 'sum' ? 'sum' : 'max');
    }
}

