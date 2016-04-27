<?php
return array(
    'primary_currency' => array(
        'value'            => '',
        'title'            => 'Основная валюта',
        'description'      => 'Основная валюта товарных предложений.<br/>
 Все цены, указанные в валюте, отличной от RUB, UAH, BYR, KZT, USD и EUR, будут сконвертированы в цены в основной валюте.',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopYandexmarketPlugin', 'settingsPrimaryCurrencies'),
    ),

    'convert_currency' => array(
        'value'        => false,
        'title'        => 'Конвертировать цены',
        'description'  => 'Конвертировать все цены в основную валюту.<br/>
 Полезно при использовании правил округления для основной валюты.',
        'control_type' => waHtmlControl::CHECKBOX,
    ),

    'market_token' => array(
        'value'        => '',
        'placeholder'  => 'B3000001C136C238',
        'title'        => 'Авторизационный токен',
        'description'  => 'Укажите <i>авторизационный токен</i> сгенерированный на странице «Настройки API» заказа в кабинете Яндекс.Маркета.',
        'control_type' => waHtmlControl::INPUT,
    ),
    'contact_id'       => array(
        'value'        => '',
        'placeholder'  => '123',
        'title'        => 'ID контакта',
        'description'  => 'Укажите <i>id</i> контакта, от имени которого будут проводиться действия программы «Заказ на Маркете».',
        'control_type' => waHtmlControl::INPUT,
    ),

    'api_oauth_token' => array(
        'value'       => '',
        'placeholder' => '',
        'title'       => 'Авторизационный токен',
        'description' => <<<HTML
Укажите <i>авторизационный токен</i> приложения, имеющего доступ к  Яндекс.Маркет.<br/>
Используется для отображения статистики и обновления статусов заказов<br/>
Для получения токена пройдите по 
        <a href="https://oauth.yandex.ru/authorize?response_type=token&client_id=%api_client_id%" target="_blank">ссылке</a>, подтвердите права и введите токен в это поле.
<script type="text/javascript">
var input = $('input[name$="\[api_client_id\]"');

var href = $('input[name$="\[api_oauth_token\]"').parent().find('a:first'); 
$.shop.trace('input',[input, href]);
if(input.length && input.val()!=''){
    href.attr('href', href.attr('href').replace(/%api_client_id%/, input.val()));
} else {
    href.hide();
}

</script>
HTML
        ,

        'control_type' => waHtmlControl::INPUT,
        'autocomplete' => 'off',
    ),

    //https://tech.yandex.ru/market/partner/
    //https://oauth.yandex.ru/client/new
    //https://oauth.yandex.ru/verification_code
    'api_client_id'   => array(
        'value'        => '',
        'title'        => 'ID приложения',
        'description'  => 'TODO: оставить единый идентификатор приложения (ключи сохраняются в контексте установок, поэтому это достаточно безопасно).',
        'control_type' => waHtmlControl::INPUT,
    ),
);
