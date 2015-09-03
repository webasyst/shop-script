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
);
