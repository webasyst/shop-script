<?php
return array(
    'price_type'               => array(
        'value'        => 'Розничная',
        'title'        => 'Тип цены',
        'description'  => '',
        'control_type' => waHtmlControl::INPUT,
    ),
    'price_type_uuid'          => array(
        'value'        => 'cbcf493b-55bc-11d9-848a-00112f43529a',
        'title'        => 'Идентификатор розничного типа цен',
        'description'  => 'Будет обновлятся автоматически при импорте',
        'control_type' => waHtmlControl::INPUT,
    ),
    'purchase_price_type'      => array(
        'value'        => 'Закупочная',
        'title'        => 'Тип закупочной цены',
        'description'  => '',
        'control_type' => waHtmlControl::INPUT,
    ),
    'purchase_price_type_uuid' => array(
        'value'        => 'bd72d8fc-55bc-11d9-848a-00112f43529a',
        'title'        => 'Идентификатор закупочного типа цен',
        'description'  => 'Будет обновлятся автоматически при импорте',
        'control_type' => waHtmlControl::INPUT,
    ),
    'export_datetime'          => array(
        'value' => false,
    ),
);
