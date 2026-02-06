<?php

class shopChannelsCloseTeaserController extends waJsonController
{
    public function execute()
    {
        $channel_type = waRequest::request('channel_type', waRequest::TYPE_STRING_TRIM);
        if (!$channel_type) {
            throw new waException('channel_type is required', 400);
        }

        $channel_types = shopSalesChannelType::getAllTypes();
        if (!$channel_types) {
            return;
        }
        $channel_types = array_combine(array_column($channel_types, 'id'), $channel_types);
        if (empty($channel_types[$channel_type])) {
            throw new waException('Channel type not found', 404);
        }

        $teasers_closed = wa()->getUser()->getSettings('shop', 'sales_channel_teasers_closed', []);
        if ($teasers_closed) {
            $teasers_closed = explode(',', $teasers_closed);
        }

        $teasers_closed[] = $channel_type;
        $teasers_closed = array_unique($teasers_closed);
        wa()->getUser()->setSettings('shop', 'sales_channel_teasers_closed', $teasers_closed);
    }
}
