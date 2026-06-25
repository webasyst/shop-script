<?php
/**
 * Implements sales channel type 'vk:<id>'
 * (point of sale)
 */
class shopVkSalesChannel extends shopTelegramSalesChannel
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

        return $view->fetch('file:templates/actions/channels/vk_channel.include.html');
    }
}
