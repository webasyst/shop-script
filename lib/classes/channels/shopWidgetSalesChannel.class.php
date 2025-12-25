<?php
/**
 * Implements sales channel type 'widget:<id>'
 * (point of sale)
 */
class shopWidgetSalesChannel extends shopSalesChannelType
{
    public function getFormHtml(array $channel): string
    {
        $view = wa('shop')->getView();

        return $view->fetch('file:templates/actions/channels/widget_channel.include.html');
    }
}
