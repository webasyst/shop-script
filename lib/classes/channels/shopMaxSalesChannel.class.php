<?php
/**
 * Implements sales channel type 'max:<id>'
 * (point of sale)
 */
class shopMaxSalesChannel extends shopSalesChannelType
{
    public function getFormHtml(array $channel): string
    {
        $view = wa('shop')->getView();

        return $view->fetch('file:templates/actions/channels/max_channel.include.html');
    }
}
