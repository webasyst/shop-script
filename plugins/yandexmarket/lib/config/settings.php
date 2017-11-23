<?php
return array(
    'primary_currency' => array(
        'value'            => '',
        'title'            => 'Основная валюта',
        'description'      => 'Основная валюта товарных предложений.<br/>
 Цены в валюте, отличной от основной, могут быть сконвертированы в основную валюту в зависимости от значения настройки «Конвертация цен в основную валюту».<br/><br/>',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopYandexmarketPlugin', 'settingsPrimaryCurrencies'),
    ),

    'convert_currency' => array(
        'value'        => '',
        'title'        => 'Конвертация цен в основную валюту',
        'description'  => 'При конвертации цен будут использоваться правила округления, указанные для основной валюты в разделе
«<a href="?action=settings#/currencies/" target="_blank">Настройки → Валюты</a>»<i class="icon16 new-window"></i>.<br/>
<i class="icon16 exclamation"></i>К ценам, указанным в <em>основной валюте</em>, правила округления не применяются.',
        'control_type' => waHtmlControl::RADIOGROUP,
        'options'=>array(
            array(
                'value'=>'',
                'title'=>'Только цены в валюте, отличной от RUB, UAH, BYR, BYN, KZT, USD и EUR, будут сконвертированы в основную валюту.'
            ), array(
                'value'=>'1',
                'title'=>'Цены в любой валюте, отличной от основной, будут сконвертированы в основную валюту.'
            ),
        )
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
        'class'        => 'long',
    ),

    'api_oauth_token' => array(
        'value'        => '',
        'placeholder'  => '',
        'title'        => 'Авторизационный токен',
        'description'  => <<<HTML
Укажите <i>авторизационный токен</i> приложения, имеющего доступ к «Яндекс.Маркету».<br/>
Используется для отображения статистики и обновления статусов заказов.<br/>
<p>Для получения токена перейдите по
        <a data-href="https://oauth.yandex.ru/authorize?response_type=token&client_id=%api_client_id%"
        href="https://oauth.yandex.ru/authorize?response_type=token&client_id=%api_client_id%" target="_blank">
        ссылке</a>, подтвердите права и введите токен в это поле.</p>
<script type="text/javascript">
    (function () {
        "use strict";
        var input = $('input[name$="\[api_client_id\]"]');
        if (input.length) {
            input.bind('change', function () {
                var href = $('input[name$="\[api_oauth_token\]"]').parent().find('a:first');
                if (this.value !== '') {
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
        'class'        => 'long',
        'autocomplete' => 'off',
    ),

    'market_token' => array(
        'value'       => '',
        'placeholder' => 'B3000001C136C238',
        'title'       => 'Авторизационный токен',
        'description' => 'Укажите <i>авторизационный токен</i>, сгенерированный на странице «Настройки API заказа» в кабинете «Яндекс.Маркета».',
        // 'control_type' => waHtmlControl::HIDDEN,
        'class'       => 'long',
    ),
    'contact_id'   => array(
        'value'        => null,
        'placeholder'  => '1',
       /* 'title'        => 'ID контакта',
        'description'  => 'Укажите <i>ID</i> контакта, от имени которого будут проводиться действия программы «Заказ на Маркете».<br>
            Например, ID контакта вашего пользователя в Вебасисте.',*/
        'control_type' => waHtmlControl::HIDDEN,
    ),

    'order_action_ship' => array(
        'value'            => array('ship' => true,),
        'title'            => 'Заказ готов к доставке',
        'description'      => 'Выберите действия, подтверждаюшие готовность заказа к доставке',
        'control_type'     => waHtmlControl::GROUPBOX,
        'options_callback' => array('shopYandexmarketPlugin', 'getShipActions'),
    ),

    'order_action_pickup' => array(
        'value'            => array(),
        'title'            => 'Заказ доставлен в пункт выдачи',
        'description'      => 'Выберите действия, подтверждаюшие доставку заказа в пункт выдачи',
        'control_type'     => waHtmlControl::GROUPBOX,
        'options_callback' => array('shopYandexmarketPlugin', 'getActions'),
    ),

    'order_action_complete' => array(
        'value'            => array('complete' => true,),
        'title'            => 'Заказ выполнен',
        'description'      => 'Выберите действия, подтверждаюшие выдачу заказа покупателю',
        'control_type'     => waHtmlControl::GROUPBOX,
        'options_callback' => array('shopYandexmarketPlugin', 'getCompleteActions'),
    ),

    'order_action_delete' => array(
        'value'            => array('delete' => true,),
        'title'            => 'Заказ удален',
        'description'      => 'Выберите действия, подтверждаюшие отмену или удаление заказа',
        'control_type'     => waHtmlControl::GROUPBOX,
        'options_callback' => array('shopYandexmarketPlugin', 'getDeleteActions'),
    ),
);
