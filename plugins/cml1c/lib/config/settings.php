<?php
return array(
    'hint'                      => array(
        'description'  => '</span><span><strong style="position: relative; top: -8px;">Обмен данными с 1С производится в разделе «<a href="?action=importexport#/cml1c/">Импорт/экспорт &rarr; 1С</a>»</strong><br><span class="hint">При обмене будут применены настройки, определенные ниже.</span><br><br>',
        'title'        => 'Обмен данными',
        'control_type' => waHtmlControl::HIDDEN,
    ),
    'price_type'                => array(
        'value'        => 'Розничная',
        'title'        => 'Тип цены в 1С',
        'description'  => 'Название типа цены в 1С, по которому будет осуществляться поиск',
        'control_type' => waHtmlControl::INPUT,
    ),
    'price_type_uuid'           => array(
        'value'        => 'cbcf493b-55bc-11d9-848a-00112f43529a',
        'title'        => 'Идентификатор розничного типа цен в 1С',
        'description'  => 'Будет обновляться автоматически при импорте из 1С',
        'control_type' => waHtmlControl::INPUT,
    ),
    'purchase_price_type'       => array(
        'value'        => 'Закупочная',
        'title'        => 'Тип закупочной цены в 1С',
        'description'  => 'Название типа цены в 1С, по которому будет осуществляться поиск',
        'control_type' => waHtmlControl::INPUT,
    ),
    'purchase_price_type_uuid'  => array(
        'value'        => 'bd72d8fc-55bc-11d9-848a-00112f43529a',
        'title'        => 'Идентификатор закупочного типа цен в 1С',
        'description'  => 'Будет обновляться автоматически при импорте из 1С',
        'control_type' => waHtmlControl::INPUT,
    ),
    'export_datetime'           => array(
        'control_type' => false,
    ),
    'order_state'               => array(
        'value'            => array(),
        'title'            => 'Статусы заказов',
        'description'      => 'Только заказы в выбранных статусах будут экспортироваться из Shop-Script в 1С.<br>Если не выбран ни один статус, то будут экспортироваться заказы во всех статусах.',
        'control_type'     => waHtmlControl::GROUPBOX,
        'options_callback' => array('shopCml1cPlugin', 'controlStatus'),
    ),
    'export_orders'             => array(
        'value'        => 'all',
        'title'        => 'Выгрузка заказов',
        'description'  => 'Выберите, какие заказы нужно экспортировать при автоматическом обмене.<br>Если выбран вариант «Новые и измененные», то экспортироваться будут только заказы, созданные или измененные не более чем за 1 час до завершения последнего обмена данными с 1С либо позднее.',
        'control_type' => waHtmlControl::RADIOGROUP,
        'options'      => array(
            'all'     => 'Все',
            'changed' => 'Новые и измененные',
        ),
    ),
    'update_product_fields'     => array(
        'value'        => array(),
        'title'        => 'Обновлять свойства товаров',
        'description'  => 'При синхронизации значений выбранных свойств товаров в Shop-Script будут полностью перезаписаны значениями из 1С',
        'control_type' => waHtmlControl::GROUPBOX,
        'options'      => array(
            'description' => _w('Description'),
            'summary'     => _w('Summary'),
            'name'        => _w('Product name'),
            'features'    => _w('Features'),
            'sku'         => 'Артикул',
            'sku_name'    => 'Наименование артикула',
            'type_id'     => 'Тип товара',
        ),

    ),
    'product_type'              => array(
        'value'            => 1,
        'title'            => 'Тип товаров',
        'description'      => 'Тип товаров в Shop-Script, к которому по умолчанию будут отнесены товары из 1С',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlType'),
    ),
    'update_product_categories' => array(
        'value'        => 'update',
        'title'        => 'Категории товаров',
        'description'  => 'Обновление данных о принадлежности к категориям при импорт товаров из 1С в Shop-Script',
        'control_type' => waHtmlControl::SELECT,
        'options'      => array(
            'none'   => 'Только для новых товаров',
            'update' => 'Только добавлять товар в новые категории',
            'sync'   => 'Добавлять в новые и удалять из устаревших',
        )
    ),
    'base_unit'                 => array(
        'value'        => '',
        'title'        => 'Единица измерения',
        'description'  => 'Значение единицы хранения будет импортировано в качестве характеристики товара с указанным кодом',
        'control_type' => waHtmlControl::INPUT,
    ),
    'description_is_html'       => array(
        'value'        => true,
        'title'        => 'Описание товара в HTML',
        'description'  => 'Включить поддержку HTML-тегов в описаниях товаров',
        'control_type' => waHtmlControl::CHECKBOX,
    ),
    'update_options'            => array(),
    'update_category_fields'    => array(
        'value'        => array('description' => true, 'name' => 'true'),
        'title'        => 'Обновлять свойства категорий',
        'description'  => 'При синхронизации значения выбранных свойств категорий в Shop-Script будут полностью перезаписаны информацией из 1С',
        'control_type' => waHtmlControl::GROUPBOX,
        'options'      => array(
            'description' => 'Описание',
            'name'        => 'Название',
            'parent_id'   => 'Родительскую категорию',
        ),
    ),
    'contact_phone'             => array(
        'value'            => 'phone',
        'title'            => 'Телефон',
        'description'      => 'Укажите поле контакта, экспортируемого из Shop-Script в 1С как номер телефона клиента',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlCustomerFields'),
    ),
    'contact_email'             => array(
        'value'            => 'email',
        'title'            => 'Email',
        'description'      => 'Выберите свойство контакта, соответствующее email-адресу клиента в Shop-Script',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlCustomerFields'),
    ),
    'currency'                  => array(
        'value'            => 'RUB',
        'title'            => 'Валюта',
        'description'      => 'Выберите основную (национальную) валюту расчета в 1С.<br/>
        <span class="bold">ВАЖНО: В случае различия валют в 1С и Shop-Script производится пересчет по курсу , установленному в настройках Shop-Script на момент выгрузки.</span>',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlCurrencies'),
    ),
    'currency_map'              => array(
        'value'        => 'руб',
        'title'        => 'Код валюты',
        'description'  => 'Укажите «Наименование» национальной валюты расчета, указанное в настройках 1С.<br>Если необходимо сопоставлять валюты по их ISO-кодам, оставьте это поле пустым.',
        'control_type' => waHtmlControl::INPUT,
    ),
    'stock'                     => array(
        'value'            => 0,
        'title'            => 'Склад',
        'description'      => 'Выберите склад Shop-Script для синхронизации остатков с 1С',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlStock'),
    ),
);