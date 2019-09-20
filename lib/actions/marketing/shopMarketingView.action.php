<?php

abstract class shopMarketingViewAction extends waViewAction
{
    public function __construct($params = null)
    {
        parent::__construct($params);
        $this->view->assign('marketing_url', self::getMarketingUrl());
        $this->setLayout(new shopBackendMarketingLayout());
        $this->getLayout()->assign('no_level2', true);
    }

    public static function getMarketingUrl()
    {
        return wa()->getAppUrl('shop', true).'marketing/';
    }
}