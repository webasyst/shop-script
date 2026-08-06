<?php
/**
 * Implements sales channel type 'max:<id>'
 * MAX messenger storefront.
 */
class shopMaxSalesChannel extends shopTelegramSalesChannel
{
    protected function getFormFieldsConfig($values = []): array
    {
        return parent::getFormFieldsConfig($values);
    }

    public function getFormHtml(array $channel): string
    {
        $view = wa('shop')->getView();

        $view->assign([
            'is_waid' => $this->isWaid(),
            'channel' => $channel,
            'form_fields' => $this->getFormFields($channel),
        ]);

        return $view->fetch('file:templates/actions/channels/max_channel.include.html');
    }
}
