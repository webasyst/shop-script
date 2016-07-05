<?php
return array(
    'primary_currency' => array(
        'value'            => '',
        'title'            => 'Основная валюта',
        'description'      => 'Основная валюта товарных предложений.<br/>
 Все цены, указанные в валюте, отличной от RUB, UAH, BYR, BYN, KZT, USD и EUR, будут сконвертированы в цены в основной валюте.',
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

    'title1' => array(
        'control_type' => waHtmlControl::HIDDEN,
        'description'  => '<h4>Заказ на Маркете</h4>',
    ),

    'market_token' => array(
        'value'        => '',
        'placeholder'  => 'B3000001C136C238',
        'title'        => 'Авторизационный токен',
        'description'  => 'Укажите <i>авторизационный токен</i>, сгенерированный на странице «Настройки API заказа» в кабинете «Яндекс.Маркета».',
        'control_type' => waHtmlControl::INPUT,
    ),
    'contact_id'   => array(
        'value'        => '',
        'placeholder'  => '123',
        'title'        => 'ID контакта',
        'description'  => 'Укажите <i>id</i> контакта, от имени которого будут проводиться действия программы «Заказ на Маркете».',
        'control_type' => waHtmlControl::INPUT,
    ),

    'title2' => array(
        'control_type' => waHtmlControl::HIDDEN,
        'description'  => '<h4>OAuth-приложение</h4>',
    ),

    'api_client_id' => array(
        'value'        => '00e02a11a94943e58a1e191266335567',
        'title'        => 'ID приложения',
        'description'  => <<<HTML
Используйте уже созданное <a href="https://oauth.yandex.ru/client/00e02a11a94943e58a1e191266335567" target="_blank">приложение<i class="icon16 new-window"></i></a>
или <a href="https://oauth.yandex.ru/client/new" target="_blank">создайте<i class="icon16 new-window"></i></a> свое собственное для большего контроля.
HTML
        ,
        'control_type' => waHtmlControl::INPUT,
    ),

    'api_oauth_token' => array(
        'value'       => '',
        'placeholder' => '',
        'title'       => 'Авторизационный токен',
        'description' => <<<HTML
Укажите <i>авторизационный токен</i> приложения, имеющего доступ к «Яндекс.Маркету».<br/>
Используется для отображения статистики и обновления статусов заказов.<br/>
<p>Для получения токена перейдите по
        <a data-href="https://oauth.yandex.ru/authorize?response_type=token&client_id=%api_client_id%" href="https://oauth.yandex.ru/authorize?response_type=token&client_id=%api_client_id%" target="_blank">ссылке</a>, подтвердите права и введите токен в это поле.</p>
<script type="text/javascript">
(function () {
    "use strict";
    var input = $('input[name$="\[api_client_id\]"');
    if(input.length){
        input.bind('change',function(){
            var href = $('input[name$="\[api_oauth_token\]"').parent().find('a:first');
            if(this.value!=''){
                href.attr('href', href.data('href').replace(/%api_client_id%/, this.value));
                href.parents('p').show();
            } else {
                href.parents('p').hide();
            }
        }).trigger('change');
    }
})();
</script>
HTML
        ,

        'control_type' => waHtmlControl::INPUT,
        'autocomplete' => 'off',
    ),
);
