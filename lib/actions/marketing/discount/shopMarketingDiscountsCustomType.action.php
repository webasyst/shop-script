<?php

/**
 * Class shopMarketingDiscountsCustomTypeAction
 * The action is needed to load a custom type of discount (from plugins)
 */
class shopMarketingDiscountsCustomTypeAction extends shopMarketingDiscountsViewAction
{
    public function __construct($params = null)
    {
        $this->discount_type_id = waRequest::param('custom_type', null, waRequest::TYPE_STRING_TRIM);
        parent::__construct($params);
    }

    public function execute()
    {
        $this->view->assign([
            'custom_type' => $this->discount_type_id,
        ]);
    }
}