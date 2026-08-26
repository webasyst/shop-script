<?php

class shopChannelsFreeShippingInformerMethodsController extends waJsonController
{
    public function execute()
    {
        if (!wa()->getUser()->isAdmin('shop')) {
            throw new waRightsException('Access denied');
        }

        $storefront = waRequest::post('storefront', '', waRequest::TYPE_STRING_TRIM);
        $per_method = waRequest::post('per_method', [], waRequest::TYPE_ARRAY);

        /** @var shopTelegramSalesChannel $channel_type */
        $channel_type = shopSalesChannelType::factory('telegram');
        $this->response['html'] = $channel_type->renderFreeShippingInformerMethods($storefront, $per_method);
    }
}
