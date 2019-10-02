<?php

abstract class shopMarketingSettingsJsonController extends waJsonController
{
    public function __construct()
    {
        if (!wa()->getUser()->getRights('shop', 'setup_marketing')) {
            throw new waRightsException(_ws('Access denied'));
        }
    }
}