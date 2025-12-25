<?php
/**
 * Implements sales channel type 'vk:<id>'
 * (point of sale)
 */
class shopVkSalesChannel extends shopSalesChannelType
{
    public function getFormHtml(array $channel): string
    {
        $view = wa('shop')->getView();

        return $view->fetch('file:templates/actions/channels/vk_channel.include.html');
    }
}
