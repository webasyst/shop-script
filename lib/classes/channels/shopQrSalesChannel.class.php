<?php
/**
 * Implements sales channel type 'qr:<id>'
 * (point of sale)
 */
class shopQrSalesChannel extends shopSalesChannelType
{
    public function getFormHtml(array $channel): string
    {
        $view = wa('shop')->getView();

        return $view->fetch('file:templates/actions/channels/qr_channel.include.html');
    }
}
