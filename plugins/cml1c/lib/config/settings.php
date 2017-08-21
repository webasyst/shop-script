<?php
return array(
    'hint'                     => array(
        'description'  => '</span><span>
<strong style="position: relative; top: -8px;">Обмен данными с 1С выполняется в разделе «<a href="?action=importexport#/cml1c/">Импорт/экспорт &rarr; 1С</a>».</strong><br/>
<span class="hint">При обмене будут использоваться определенные здесь настройки.<br><br>
<strong>Для настройки синхронизации данных по складам, характеристикам, свойствам и реквизитам товаров необходимо сначала выполнить анализ файла CommerceML на странице
<a href="?action=importexport#/cml1c/tab/manual/">ручного обмена</a>.</strong><br><br>',
        'title'        => 'Обмен данными',
        'control_type' => waHtmlControl::HIDDEN,
    ),
    'price_type'               => array(
        'value'        => 'Розничная',
        'placeholder'  => 'Розничная',
        'title'        => 'Тип цены в «1С»',
        'description'  => 'Название типа цены в «1С», по которому будет осуществляться поиск.',
        'control_type' => waHtmlControl::INPUT,
    ),
    'price_type_uuid'          => array(
        'value'        => 'cbcf493b-55bc-11d9-848a-00112f43529a',
        'placeholder'  => shopCml1cPlugin::makeUuid(),
        'title'        => 'Идентификатор розничного типа цен в «1С»',
        'description'  => 'Будет обновляться автоматически при импорте из «1С».<br/>
Идентификатор используется только для экспорта цен товаров из Shop-Script. Для правильного импорта цен укажите значение в поле «Тип цены из 1С».',
        'control_type' => waHtmlControl::INPUT,
    ),
    'purchase_price_type'      => array(
        'value'        => 'Закупочная',
        'placeholder'  => 'Закупочная',
        'title'        => 'Тип закупочной цены в «1С»',
        'description'  => 'Название типа цены в «1С», по которому будет осуществляться поиск.',
        'control_type' => waHtmlControl::INPUT,
    ),
    'purchase_price_type_uuid' => array(
        'value'        => 'bd72d8fc-55bc-11d9-848a-00112f43529a',
        'placeholder'  => shopCml1cPlugin::makeUuid(),
        'title'        => 'Идентификатор закупочного типа цен в «1С»',
        'description'  => 'Идентификатор используется только для экспорта цен товаров из Shop-Script. Для правильного импорта цен укажите значение в поле «Тип закупочной цены из 1С».<br/><br/><br/>',
        'control_type' => waHtmlControl::INPUT,
    ),
    'compare_price_type'       => array(
        'value'        => 'Зачеркнутая',
        'placeholder'  => 'Зачеркнутая',
        'title'        => 'Тип зачеркнутой цены в «1С»',
        'description'  => 'Название типа цены в «1С», по которому будет осуществляться поиск.',
        'control_type' => waHtmlControl::INPUT,
    ),
    'compare_price_type_uuid'  => array(
        'value'        => 'bd72d8fc-55bc-11d9-848a-00112f43529a',
        'placeholder'  => shopCml1cPlugin::makeUuid(),
        'title'        => 'Идентификатор зачеркнутой цены в «1С»',
        'description'  => 'Идентификатор используется только для экспорта цен товаров из Shop-Script. Для правильного импорта цен укажите значение в поле «Тип зачеркнутой цены из 1С».<br/><br/><br/>',
        'control_type' => waHtmlControl::INPUT,
    ),
    'export_product_name'      => array(
        'value'        => 'name',
        'title'        => 'Формат экспорта наименований артикулов (модификаций)',
        'description'  => 'Выбранный формат используется для экспорта каталога товаров и товарных единиц в составе заказов.',
        'control_type' => waHtmlControl::SELECT,
        'options'      => array(
            'name'     => 'Только наименование товара',
            'brackets' => 'Формат «наименование товара (наименование артикула)»'
        ),
    ),
    'export_product_features' => array(
        'value'        => 0,
        'title'        => 'Экспорт характеристик товаров в составе заказов',
        'description'  => 'Включите, чтобы экспортировать подробную информацию о заказанных товарах, например, размер, цвет и т. п.',
        'control_type' => waHtmlControl::RADIOGROUP,
        'options' => array(
            0                 => 'Не экспортировать',
            'characteristics' => 'Экспортировать в элементе <ХарактеристикиТовара> (для МойСклад)',
            'properties'      => 'Экспортировать в элементе <ЗначенияРеквизитов> (для Бизнес.ру)',
        ),
    ),

    'export_datetime'              => array(
        'control_type' => false,
    ),
    'order_state'                  => array(
        'value'            => array(),
        'title'            => 'Статусы заказов',
        'description'      => 'Только заказы в выбранных статусах будут экспортироваться из Shop-Script в «1С».<br/>
Если не выбран ни один статус, то будут экспортироваться заказы во всех статусах.',
        'control_type'     => waHtmlControl::GROUPBOX,
        'options_callback' => array('shopCml1cPlugin', 'controlStatus'),
    ),
    'export_orders'                => array(
        'value'        => 'all',
        'title'        => 'Выгрузка заказов',
        'description'  => 'Выберите, какие заказы нужно экспортировать при автоматическом обмене.<br/>
Если выбран вариант «Новые и измененные», то экспортироваться будут только заказы, созданные или измененные не более чем за 1 час до завершения последнего обмена данными с «1С»
либо позднее. Стандартное значение <em>1 час</em> (3600 сек) можно изменить в поле «Период для выборки новых и измененных заказов» ниже.',
        'control_type' => waHtmlControl::RADIOGROUP,
        'options'      => array(
            'all'     => 'Все',
            'changed' => 'Новые и измененные',
        ),
    ),
    'time_gap'                     => array(
        'value'        => 3600,
        'title'        => 'Период для выборки новых и измененных заказов',
        'description'  => 'Временной интервал в секундах для выборки новых и измененных заказов при экспорте; должен быть больше времени экспорта и обработки пакета данных на стороне «1С»',
        'control_type' => waHtmlControl::INPUT,
    ),
    'export_delivery'              => array(
        'value'        => true,
        'title'        => 'Выгрузка доставки',
        'description'  => 'Выгружать стоимость доставки в виде отдельной позиции заказа',
        'control_type' => waHtmlControl::CHECKBOX,
    ),
    'contact_phone'                => array(
        'value'            => 'phone',
        'title'            => 'Телефон клиента',
        'description'      => 'Выберите поле контакта в Shop-Script, соответствующее телефону клиента в CommerceML (тип контакта «<tt>ТелефонРабочий</tt>»).',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlCustomerFields'),
    ),
    'contact_email'                => array(
        'value'            => 'email',
        'title'            => 'Email клиента',
        'description'      => 'Выберите поле контакта в Shop-Script, соответствующее email-адресу клиента в CommerceML (тип контакта «<tt>Почта</tt>»).',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlCustomerFields'),
    ),
    'contact_company'              => array(
        'value'            => '',
        'title'            => 'Наименование компании клиента',
        'description'      => 'Выберите поле контакта в Shop-Script, соответствующее элементу CommerceML <tt>&lt;Наименование&gt;</tt> и <tt>&lt;ОфициальноеНаименование&gt;</tt>
(наименование юридического лица).<br/>
<strong>По заполненности этого поля будет выбран формат экспортируемых данных, соответствующий физическому или юридическому лицу</strong>
(для физического лица при оформлении заказа это поле не должно быть заполнено).',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlCustomerFields'),
    ),
    'contact_inn'                  => array(
        'value'            => '',
        'title'            => 'ИНН клиента',
        'description'      => 'Выберите поле контакта в Shop-Script, соответствующее элементу CommerceML <tt>&lt;ИНН&gt;</tt>.',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlCustomerFields'),
    ),
    'contact_kpp'                  => array(
        'value'            => '',
        'title'            => 'КПП клиента',
        'description'      => 'Выберите поле контакта в Shop-Script, соответствующее элементу CommerceML <tt>&lt;КПП&gt;</tt>.',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlCustomerFields'),
    ),
    'contact_okpo'                 => array(
        'value'            => '',
        'title'            => 'ОКПО клиента',
        'description'      => 'Выберите поле контакта в Shop-Script, соответствующее элементу CommerceML <tt>&lt;ОКПО&gt;</tt>.',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlCustomerFields'),
    ),
    'contact_bank_bik'             => array(
        'value'            => '',
        'title'            => 'БИК клиента',
        'description'      => 'Выберите поле контакта в Shop-Script, соответствующее элементу CommerceML <tt>&lt;БИК&gt;</tt>.',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlCustomerFields'),
    ),
    'contact_bank_name'            => array(
        'value'            => '',
        'title'            => 'Наименование банка клиента',
        'description'      => 'Выберите поле контакта в Shop-Script, соответствующее элементу наименование банка клиента в CommerceML (элемент <tt>&lt;Наименование&gt;</tt> блока
<tt>&lt;Банк&gt;</tt>).',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlCustomerFields'),
    ),
    'contact_bank_account'         => array(
        'value'            => '',
        'title'            => 'Расчетный счет клиента',
        'description'      => 'Выберите поле контакта в Shop-Script, соответствующее элементу CommerceML <tt>&lt;НомерСчета&gt;</tt>.',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlCustomerFields'),
    ),
    'contact_bank_cor_account'     => array(
        'value'            => '',
        'title'            => 'Корреспондентский счет клиента',
        'description'      => 'Выберите поле контакта в Shop-Script, соответствующее элементу CommerceML <tt>&lt;СчетКорреспондентский&gt;</tt>.',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlCustomerFields'),
    ),
    'contact_fields'               => array(
        'value'            => array(),
        'title'            => 'Дополнительные параметры заказа',
        'description'      => 'Настройки экспорта дополнительных параметров заказа в блоке CommerceML <tt>&lt;ЗначенияРеквизитов&gt;</tt>.<br/><br/><br/>',
        'control_type'     => 'ContactFieldsControl',
        'options_callback' => array('shopCml1cPlugin', 'controlCustomerFields'),
    ),
    'update_product_fields'        => array(
        'value'            => array(),
        'title'            => 'Обновлять при импорте свойства товаров',
        'description'      => 'При синхронизации значений выбранных свойств товаров в Shop-Script будут полностью перезаписаны значениями из «1С» для уже <b>существующих</b>
товаров, а для новых товаров будут импортированы <b>все</b> данные из «1С».<br/>
<b>Характеристики артикулов (модификаций) будут импортированы, только если они заданы в Shop-Script в виде характеристики типа <tt>checkbox</tt>.</b>',
        'control_type'     => waHtmlControl::GROUPBOX,
        'options_callback' => array('shopCml1cPlugin', 'controlProductFields'),
        'group'            => 'Товары',
    ),
    'import_sku_name_rule'         => array(
        'value'        => '',
        'title'        => 'Импорт наименования артикула',
        'description'  => '',
        'control_type' => waHtmlControl::RADIOGROUP,
        'options'      => array(
            array(
                'value'       => '',
                'description' => 'Наименование артикула будет сформировано на основе наименования товарного предложения',
                'title'       => 'Наименование предложения',
            ),
            array(
                'value'       => 'features',
                'description' => 'В сервисе «МойСклад» нет возможности указать наименования модификаций товаров — они формируются автоматически из значений характеристик, но не передаются через файлы CommerceML. Выберите этот вариант, чтобы импортировать наименования артикулов в формате, максимально близком используемому в «МойСклад», например: «<em>Белый, 64 Мб</em>».',
                'title'       => 'Значения характеристик (для «МойСклад»)',
            ),
        ),
    ),
    'create_unique_product_url' => array(
        'value'        => false,
        'title'        => 'Формировать уникальные URL для импортируемых товаров',
        'description'  => 'Если URL импортированного товара, сформированный путем транслитерации его названия, совпадет с URL другого товара, то к полученному адресу будет добавлено число для обеспечения его уникальности.',
        'control_type' => waHtmlControl::CHECKBOX,
    ),
    'update_product_categories'    => array(
        'value'        => 'update',
        'title'        => 'Категории товаров при импорте',
        'description'  => 'Обновление данных о принадлежности к категориям при импорте товаров из «1С» в Shop-Script.',
        'control_type' => waHtmlControl::RADIOGROUP,
        'options'      => array(
            'skip'   => 'Импорт категорий и информации о принадлежности к ним будет пропущен',
            'none'   => 'Только для новых товаров',
            'update' => 'Только добавлять товар в новые категории',
            'sync'   => 'Добавлять в новые категории и удалять из устаревших',
        ),
        'group'        => 'Товары',
    ),
    'update_options'               => array(),
    'update_category_fields'       => array(
        'value'        => array(
            'description' => true,
            'name'        => true,
        ),
        'title'        => 'Обновлять свойства категорий при импорте',
        'description'  => 'Значения выбранных свойств категорий в Shop-Script при синхронизации будут полностью перезаписаны информацией из «1С» для <b>существующих</b> категорий.',
        'control_type' => waHtmlControl::GROUPBOX,
        'options'      => array(
            'description' => 'Описание категории',
            'name'        => 'Название категории',
            'parent_id'   => 'Родительскую категорию',
        ),
    ),
    'update_product_types'         => array(
        'value'        => 'skip',
        'title'        => 'Тип товаров при импорте',
        'description'  => 'Обновление данных о принадлежности к типам при импорте товаров из «1С» в Shop-Script.<br/>
Добавляемые характеристики будут отнесены к тем типам товаров, для которых они были заданы.',
        'control_type' => waHtmlControl::RADIOGROUP,
        'options'      => array(
            'skip'   => 'Типы товаров и информация о принадлежности товаров к ним не будет импортироваться. Все новые товары будут отнесены к типу, выбранному в настройке
«Тип товаров по умолчанию».',
            'none'   => 'Только для новых товаров, если указанный тип существует. В противном случае для них будет использоваться тип товаров по умолчанию.',
            'update' => 'Только для новых товаров. Если указанный тип товаров не существует, он будет создан.',
            'sync'   => 'Обновлять для всех товаров.',
        ),
        'group'        => 'Товары',
    ),
    'product_type'                 => array(
        'value'            => 1,
        'title'            => 'Тип товаров по умолчанию',
        'description'      => 'Тип товаров в Shop-Script, к которому по умолчанию будут отнесены новые товары из «1С» (в настройках витрины магазина можно скрыть этот тип
товаров).<br/>
Соответствие типа товара определяется на основе параметров «<em>вид номенклатуры</em>» или «<em>вид товара</em>».',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlType'),
        'group'            => 'Товары',
    ),
    'product_hide'                 => array(
        'value'        => false,
        'title'        => 'Скрывать новые товары при импорте',
        'description'  => 'Всем новым товарам, импортированных из «1С», будет присвоен статус «Скрыт с сайта».<br/>
 Товарам, помеченных на удаление в «1С», будет присвоен статус «Скрыт с сайта» вне зависимости от значения этой настройки.<br/><br/><br/>',
        'control_type' => waHtmlControl::CHECKBOX,
    ),
    'description_is_html'          => array(
        'value'        => true,
        'title'        => 'Обработка специальных символов в описаниях товаров',
        'description'  => 'Включите, если в описаниях товаров нужно отображать специальные символы (например, угловые скобки &lt; и &gt;) и в них не используются HTML-теги.',
        'control_type' => waHtmlControl::CHECKBOX,
        'group'        => 'Товары',
    ),
    'base_unit'                    => array(
        'value'        => '',
        'placeholder'  => 'Введите код характеристики',
        'title'        => 'Единица измерения',
        'class'        => 'js-autocomplete-feature',
        'description'  => <<<HTML
            Значение единицы хранения будет импортировано в качестве характеристики товара с указанным кодом. Если оставить это поле пустым, то единицы измерения будут игнорироваться
при импорте.

<script type="text/javascript">
if((typeof($.ui.autocomplete) != 'undefined') && (typeof($.ui.autocomplete) != 'undefined')){
    $(':input[name*="base_unit"].js-autocomplete-feature').autocomplete({
        source: '?action=autocomplete&type=feature&options[single]=1',
        minLength: 2,
        delay: 300
    });
}
</script>

HTML
        ,
        'control_type' => waHtmlControl::INPUT,
        'group'        => 'Товары',
    ),
    'weight_unit'                  => array(
        'value'            => 'kg',
        'title'            => 'Единица измерения веса',
        'description'      => 'Укажите размерность хранения веса в «1С»',
        'control_type'     => waHtmlControl::SELECT,
        'group'            => 'Товары',
        'options_callback' => array('shopCml1cPlugin', 'controlWeightUnits'),
    ),
    'currency'                     => array(
        'value'            => 'RUB',
        'title'            => 'Валюта',
        'description'      => 'Выберите основную (национальную) валюту расчета в «1С».<br/>
        <span class="bold">В случае различия валют в «1С» и Shop-Script выполняется пересчет по курсу, установленному в настройках Shop-Script на момент выгрузки данных.</span>',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlCurrencies'),
    ),
    'currency_map'                 => array(
        'value'        => 'руб',
        'placeholder'  => 'руб',
        'title'        => 'Код валюты',
        'description'  => 'Укажите «Наименование» национальной валюты расчета, указанное в настройках «1С».<br/>
Если необходимо сопоставлять валюты по их ISO-кодам, оставьте это поле пустым.',
        'control_type' => waHtmlControl::INPUT,
    ),
    'stock'                        => array(
        'value'            => 0,
        'title'            => 'Общие остатки в CommerceML',
        'description'      => 'Выберите один склад Shop-Script для переноса в него общих остатков из CommerceML (элемент <code>&lt;Количество&gt;</code>) или оставьте выбранным
импорт в общие остатки товаров Shop-Script.<br>
Одновременный импорт остатков по нескольким складам (элемент CommerceML <code>&lt;КоличествоНаСкладе&gt;</code>) возможен только после анализа файла CommerceML на странице
<a href="?action=importexport#/cml1c/tab/manual/">ручного обмена</a>.',
        'control_type'     => waHtmlControl::SELECT,
        'options_callback' => array('shopCml1cPlugin', 'controlStock'),
    ),
    'stock_setup'                  => array(
        'value'        => true,
        'title'        => 'Создавать новые артикулы с нулевыми остатками',
        'description'  => 'Если эта настройка выключена, то новые артикулы будут создаваться с бесконечным остатком на тех складах Shop-Script, которые не были связаны со складами
в файле CommerceML.<br>
Связь складов выполняется после анализа файла CommerceML на странице <a href="?action=importexport#/cml1c/tab/manual/">ручного обмена</a>.',
        'control_type' => waHtmlControl::CHECKBOX,
    ),
    'stock_complement'             => array(
        'value'        => false,
        'title'        => 'Обнулять остатки в несинхронизированных складах',
        'description'  => 'Будут обнулены остатки товаров на тех складах Shop-Script, которые не были связаны со складами в файле CommerceML.<br>
Если эта настройка выключена, то при импорте не будут изменены текущие остатки товаров на складах Shop-Script, которые не были связаны со складами в файле CommerceML.<br>
Связь складов выполняется после анализа файла CommerceML на странице <a href="?action=importexport#/cml1c/tab/manual/">ручного обмена</a>.',
        'control_type' => waHtmlControl::CHECKBOX,
    ),
);
