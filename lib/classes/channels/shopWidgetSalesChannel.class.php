<?php
/**
 * Implements sales channel type 'widget:<id>'
 * Universal JavaScript widget for embedding an online store & shopping flow in any website.
 */
class shopWidgetSalesChannel extends shopTelegramSalesChannel
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
            'widget_embed_code_modal' => $this->getWidgetEmbedCode($channel, 'modal'),
            'widget_embed_code_inline' => $this->getWidgetEmbedCode($channel, 'inline'),
        ]);

        return $view->fetch('file:templates/actions/channels/widget_channel.include.html');
    }

    public function getPublicStorefrontParams(array $channel): array
    {
        $result = parent::getPublicStorefrontParams($channel);
        unset($result['is_custom_bot']);
        return $result;
    }

    public function getWaidChannelParams(array $channel): array
    {
        $result = parent::getWaidChannelParams($channel);
        unset($result['is_custom_bot']);
        return $result;
    }

    protected function getWidgetEmbedCode(array $channel, $embed_mode): string
    {
        $script_attributes = [
            'src' => 'https://dev.app.shop-script.ru/embed/embed.js',
            'data-account-id' => ifset($channel, 'wa_channel_id', ''),
            //'data-path' => '', // !!! /product/80
        ];

        if ($embed_mode === 'modal') {
            $script_attributes['data-mode'] = 'modal';
            $script_attributes['data-element'] = "#wa-shop-widget-button";
            $result = '<button id="wa-shop-widget-button">BUY NOW</button>';
        } else {
            $script_attributes['data-container'] = "#wa-shop-widget";
            $result = '<div id="wa-shop-widget"></div>';
        }

        return $result.sprintf("\n<script\n\t%s\n\tasync\n></script>", join("\n\t",
                array_map(
                    function($k, $v) {
                        return $k.'="'.htmlspecialchars($v).'"';
                    },
                    array_keys($script_attributes),
                    array_values($script_attributes)
                )
            )
        );
    }
}
