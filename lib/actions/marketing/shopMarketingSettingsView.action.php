<?php

abstract class shopMarketingSettingsViewAction extends shopMarketingViewAction
{
    public function __construct($params = null)
    {
        if (!wa()->getUser()->getRights('shop', 'setup_marketing')) {
            throw new waRightsException(_ws('Access denied'));
        }
        parent::__construct($params);
    }
}