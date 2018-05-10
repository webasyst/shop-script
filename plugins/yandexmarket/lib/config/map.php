<?php
return array(
    'types'  => array(
        /**
         * fields are order sensitive and depends on DTD
         */
        'simple'       => array(
            'name'   => 'Упрощенное описание',
            'fields' => array(
                'available'             => true,
                'id'                    => true,
                'url'                   => true,
                'price'                 => true,
                'oldprice'              => true,
                'purchase_price'        => false,
                'currencyId'            => true,
                'vat'                   => false,
                'categoryId'            => true,
                'picture'               => false,
                'store'                 => false,
                'pickup'                => false,
                'delivery'              => false,
                'local_delivery_before' => false,
                'local_delivery_days'   => false,
                'local_delivery_cost'   => false,
                'name'                  => true,
                'vendor'                => false,
                'model'                 => false,
                'vendorCode'            => false,
                'description'           => false,
                'sales_notes'           => false,
                'min-quantity'          => false,
                'step-quantity'         => false,
                'manufacturer_warranty' => false,
                'country_of_origin'     => false,
                'adult'                 => false,
                'age'                   => false,
                'barcode'               => false,
                'cpa'                   => false,
                'weight'                => false,
                'fee'                   => false,
                'bid'                   => false,
                'cbid'                  => false,
                'rec'                   => false,
                'param.*'               => false,
            ),
        ),
        'vendor.model' => array(
            'name'   => 'Произвольный товар (vendor.model)',
            'fields' => array(
                'available'             => true,
                'id'                    => true,
                'group_id'              => false,
                'url'                   => true,
                'price'                 => true,
                'oldprice'              => true,
                'purchase_price'        => false,
                'currencyId'            => true,
                'vat'                   => false,
                'categoryId'            => true,
                'picture'               => true,
                /**
                 *
                 * Ссылка на картинку соответствующего товарного предложения. Недопустимо давать ссылку на «заглушку»,
                 * т.е. на страницу, где написано «картинка отсутствует», или на логотип магазина.
                 * Максимальная длина URL — 512 символов.
                 *
                 * Для товарных предложений, относящихся к категории «Одежда и обувь», является обязательным элементом.
                 * Для всех остальных категорий – необязательный элемент.
                 **/
                'store'                 => false,
                'pickup'                => false,
                'delivery'              => false,
                'local_delivery_before' => false,
                'local_delivery_days'   => false,
                'local_delivery_cost'   => false,
                'typePrefix'            => false,
                /**
                 *
                 * Группа товаров/категория.
                 *
                 * Необязательный элемент.
                 **/
                'vendor'                => true,
                'vendorCode'            => false,
                'model'                 => true,
                'description'           => false,
                'sales_notes'           => false,
                'min-quantity'          => false,
                'step-quantity'         => false,
                'manufacturer_warranty' => false,
                'seller_warranty'       => false,
                'country_of_origin'     => false,
                'downloadable'          => false,
                'adult'                 => false,
                'age'                   => false,
                'barcode'               => false,
                'cpa'                   => false,
                'fee'                   => false,
                'bid'                   => false,
                'cbid'                  => false,
                /**
                 *
                 * Элемент предназначен для управления участием товарных предложений в программе «Покупка на Маркете».
                 *
                 * Необязательный элемент.
                 **/
                'rec'                   => false,
                /**
                 *
                 * Элемент обозначает товары, рекомендуемые для покупки вместе с текущим.
                 *
                 * Необязательный элемент.
                 **/
                'expiry'                => false,
                'weight'                => false,
                'dimensions'            => false,
                'param.*'               => false,
                /**
                 *
                 * Элемент предназначен для указания характеристик товара. Для описания каждого параметра используется
                 * отдельный элемент <param>.
                 *
                 * Необязательный элемент. Элемент <offer> может содержать несколько элементов <param>.
                 */
            ),
        ),
        'book'         => array(
            'name'   => 'Книги (book)',
            'fields' => array(
                'available'             => true,
                'id'                    => true,
                'url'                   => true,
                'price'                 => true,
                'oldprice'              => true,
                'purchase_price'        => false,
                'currencyId'            => true,
                'vat'                   => false,
                'categoryId'            => true,
                'picture'               => false,
                'store'                 => false,
                'pickup'                => false,
                'delivery'              => false,
                'local_delivery_before' => false,
                'local_delivery_days'   => false,
                'local_delivery_cost'   => false,
                'author'                => false,
                'name'                  => true,
                'publisher'             => false,
                'series'                => false,
                'year'                  => false,
                'ISBN'                  => false,
                'volume'                => false,
                'part'                  => false,
                'language'              => false,
                'binding'               => false,
                'page_extent'           => false,
                'table_of_contents'     => false,
                'description'           => false,
                'sales_notes'           => false,
                'min-quantity'          => false,
                'step-quantity'         => false,
                'downloadable'          => false,
                'age'                   => false,
                'cpa'                   => false,
                'weight'                => false,
                'fee'                   => false,
                'bid'                   => false,
                'cbid'                  => false,
                'rec'                   => false,
            ),
        ),
        'medicine'     => array(
            'name'   => 'Лекарства (medicine)',
            'fields' => array(
                'available'             => true,
                'id'                    => true,
                'url'                   => true,
                'price'                 => true,
                'oldprice'              => true,
                'purchase_price'        => false,
                'currencyId'            => true,
                'vat'                   => false,
                'categoryId'            => true,
                'picture'               => false,
                'store'                 => false,
                'pickup'                => false,
                'delivery'              => false,
                'local_delivery_before' => false,
                'local_delivery_days'   => false,
                'local_delivery_cost'   => false,
                'name'                  => true,
                'vendor'                => false,
                'vendorCode'            => false,
                'description'           => false,
                'sales_notes'           => false,
                'min-quantity'          => false,
                'step-quantity'         => false,
                'country_of_origin'     => false,
                'adult'                 => false,
                'age'                   => false,
                'barcode'               => false,
                'cpa'                   => false,
                'weight'                => false,
                'fee'                   => false,
                'bid'                   => false,
                'cbid'                  => false,
                'rec'                   => false,
                'param.*'               => false,
            ),
        ),
        'audiobook'    => array(
            'name'   => 'Аудиокниги (audiobook)',
            'fields' => array(
                'available'         => true,
                'id'                => true,
                'url'               => true,
                'price'             => true,
                'oldprice'          => true,
                'purchase_price'    => false,
                'currencyId'        => true,
                'vat'               => false,
                'categoryId'        => true,
                'picture'           => false,
                'author'            => false,
                'name'              => false,
                'publisher'         => false,
                'series'            => false,
                'year'              => false,
                'ISBN'              => false,
                'volume'            => false,
                'part'              => false,
                'language'          => false,
                'table_of_contents' => false,
                'performed_by'      => false,
                'performance_type'  => false,
                'storage'           => false,
                'format'            => false,
                'recording_length'  => false,
                'description'       => false,
                'sales_notes'       => false,
                'downloadable'      => false,
                'age'               => false,
                'cpa'               => false,
                'weight'            => false,
                'fee'               => false,
                'bid'               => false,
                'cbid'              => false,
                'rec'               => false,
            ),
        ),
        'artist.title' => array(
            'name'   => 'Музыкальная и видео продукция (artist.title)',
            'fields' => array(
                'available'      => true,
                'id'             => true,
                'url'            => true,
                'price'          => true,
                'oldprice'       => true,
                'purchase_price' => false,
                'currencyId'     => true,
                'vat'            => false,
                'categoryId'     => true,
                'picture'        => false,
                'store'          => false,
                'pickup'         => false,
                'delivery'       => false,
                'artist'         => false,
                'title'          => true,
                'year'           => false,
                'media'          => false,
                'starring'       => false,
                /**
                 * Актеры.
                 **/
                'director'       => false,
                /**
                 * Режиссер.
                 **/
                'originalName'   => false,
                /**
                 * Оригинальное название.
                 **/
                'country'        => false,
                /**
                 * Страна.
                 */

                'description' => false,
                'sales_notes' => false,
                'adult'       => false,
                'age'         => false,
                'barcode'     => false,
                'cpa'         => false,
                'fee'         => false,
                'bid'         => false,
                'cbid'        => false,
                'rec'         => false,
            ),
        ),
        'tour'         => array(
            'name'   => 'Туры (tour)',
            'fields' => array(
                'available'   => true,
                'id'          => true,
                'url'         => true,
                'price'       => true,
                'currencyId'  => true,
                'vat'         => false,
                'categoryId'  => true,
                'picture'     => false,
                'store'       => false,
                'pickup'      => false,
                'delivery'    => false,
                'worldRegion' => false,
                /**
                 * Часть света.
                 **/
                'country'     => false,
                /**
                 * Страна.
                 **/
                'region'      => false,
                /**
                 * Курорт или город.
                 **/
                'days'        => true,
                'dataTour'    => false,
                'name'        => true,
                'hotel_stars' => false,
                'room'        => false,
                'meal'        => false,
                'included'    => true,
                'transport'   => true,
                'description' => false,
                'age'         => false,
            ),
        ),
        'event-ticket' => array(

            'name'   => 'Билеты на мероприятие (event-ticket)',
            'fields' => array(
                'available'   => true,
                'id'          => true,
                'url'         => true,
                'price'       => true,
                'currencyId'  => true,
                'vat'         => false,
                'categoryId'  => true,
                'picture'     => false,
                'store'       => false,
                'pickup'      => false,
                'delivery'    => false,
                'name'        => true,
                'place'       => true,
                'hall'        => false,
                /**
                 * Ссылка на изображение с планом зала.
                 **/
                'date'        => true,
                'is_premiere' => false,
                /**
                 * Признак премьерности мероприятия.
                 **/
                'is_kids'     => false,
                /**
                 * Признак детского мероприятия.
                 **/
                'age'         => false,
            ),
        ),
    ),
    'fields' => array(

        /**
         * Fields order is user friendly
         */
        /**
         * 'field_id'              => array(
         * 'type'        => 'fixed|adjustable|custom',
         * 'name'        => 'Field name for settings screen',
         * 'description' => 'Field description',
         * 'help'=>'Explained field description with possible html tags',
         * 'attribute'=>true, //(if it dom attribute only)
         * 'field'       => 'offer',(if it dom attribute only specify dom element name),
         * 'source'=>'',
         * 'format'=>'%d',//field value format for sprintf PHP method
         * 'callback'=>true,
         * 'sources'=>array(
         *      'feature',
         *      'custom',
         * ),
         * 'values'=>array(
         * ),
         * ),
         */
        'id'       => array(
            'type'        => 'fixed',
            'name'        => 'идентификатор товарного предложения',
            'description' => '',
            'attribute'   => true,
            'source'      => 'field:id',
            'field'       => 'offer',
        ),
        'group_id' => array(
            'type'        => 'fixed',
            'name'        => 'идентификатор группы товарного предложения',
            'description' => '',
            'attribute'   => true,
            'source'      => 'field:_group_id',
            'field'       => 'offer',
            'format'      => '%d',
            'callback'    => true,
        ),
        'url'      => array(
            'type'        => 'fixed',
            'name'        => 'URL — адрес страницы товара',
            'description' => '',
            'format'      => '%0.512s',
            'source'      => 'field:frontend_url',
        ),
        'price'    => array(
            'type'        => 'fixed',
            'name'        => 'Цена',
            'description' => 'Цена товарного предложения округляется и выводится в зависимости от настроек пользователя.',
            'format'      => '%0.2f',
            'source'      => 'field:price',
        ),

        'oldprice'       => array(
            'type'        => 'fixed',
            'name'        => 'Старая цена',
            'description' => 'Старая цена товарного предложения округляется и выводится в зависимости от настроек пользователя.',
            'format'      => '%0.2f',
            'source'      => 'field:compare_price',
            'callback'    => true,
        ),
        'purchase_price' => array(
            'type'        => 'fixed',
            'name'        => 'Закупочная цена',
            'description' => '',
            'format'      => '%d',
            'source'      => 'field:purchase_price',
            'callback'    => true,
        ),
        'currencyId'     => array(
            'type'        => 'fixed',
            'name'        => 'Идентификатор валюты товара',
            'description' => 'Для корректного отображения цены в национальной валюте необходимо использовать идентификатор с соответствующим значением цены.',
            'values'      => array(
                'RUB',
                'USD',
                'UAH',
                'KZT',
                'BYR',
                'BYN',
                'EUR',
            ),
            'source'      => 'field:currency',
        ),
        'vat'            => array(
            'type'        => 'adjustable',
            'name'        => 'Ставки НДС',
            'description' => 'Выберите «Налоговые ставки», чтобы передать для товаров ставки НДС в прайс-листе.
Налоговые ставки используются для предоплаты на «Маркете» и также передаются через API программы «Заказ на Маркете» для заказанных товаров.',
            'source'      => 'field:tax_id',
        ),
        'categoryId'     => array(
            'type'        => 'fixed',
            'name'        => 'Идентификатор категории товара ',
            'description' => '(целое число не более 18 знаков). Товарное предложение может принадлежать только к одной категории.',
            'source'      => 'field:category_id',
        ),
        'picture'        => array(
            'type'   => 'fixed',
            'name'   => 'Ссылка на изображение соответствующего товарного предложения',
            'source' => 'field:images',
        ),
        'downloadable'   => array(
            'type'        => 'adjustable',
            'name'        => 'Цифровой товар',
            'description' => 'Обозначение товара, который можно скачать',
            'source'      => 'field:file_name',
            'values'      => array(
                true => 'true',
            ),
            'params'      => true,
        ),
        /**
         * adjustable
         */


        'vendor'                => array(
            'type'        => 'adjustable',
            'name'        => 'Производитель',
            'description' => 'Отображается в названии предложения',
        ),
        'vendorCode'            => array(
            'type'        => 'adjustable',
            'name'        => 'Код',
            'description' => 'Код товара (указывается код производителя).',
        ),
        'model'                 => array(
            'type'        => 'adjustable',
            'name'        => 'Модель',
            'description' => '',
        ),
        'title'                 => array(
            'type'        => 'adjustable',
            'name'        => 'Название',
            'description' => 'Название фильма или альбома',
            'source'      => 'field:name',
        ),
        'name'                  => array(
            'type'        => 'adjustable',
            'name'        => 'Название',
            'description' => 'Наименование товарного предложения',
            'source'      => 'field:name',
        ),
        'artist'                => array(
            'type'   => 'adjustable',
            'name'   => 'Исполнитель',
            'source' => 'feature:artist',
        ),
        'author'                => array(
            'type'   => 'adjustable',
            'name'   => 'Автор произведения',
            'source' => 'feature:author',
        ),
        'days'                  => array(
            'type'   => 'adjustable',
            'name'   => 'Количество дней тура',
            'source' => '',
        ),
        'place'                 => array(
            'type'        => 'adjustable',
            'name'        => 'Место проведения',
            'description' => '',
            'format'      => '',
            'source'      => 'feature:place',
        ),
        'date'                  => array(
            'type'        => 'adjustable',
            'name'        => 'Дата и время сеанса',
            'description' => '',
            'format'      => '',
            'source'      => 'feature:date',
        ),
        'description'           => array(
            'type'              => 'adjustable',
            'name'              => 'Описание',
            'description'       => 'Описание товарного предложения',
            'source'            => 'field:summary',
            'available_options' => array(
                'html' => 'Выгружать описания товаров c <a href="https://yandex.ru/support/partnermarket/elements/description.html#requirements" target="_blank">HTML-тегами, которые поддерживает «Яндекс.Маркет»</a>, и игнорировать все остальные теги.<br>
        Отключите, чтобы игнорировать все HTML-теги в описаниях товаров.',
            ),
        ),
        'available'             => array(
            'type'        => 'adjustable',
            'name'        => 'Наличие',
            'description' => 'Статус доступности товара: в наличии или «на заказ».',
            'help'        => 'Положительное целочисленное значение означает наличие товара в указанном количестве, в том числе для программы «Заказ на Маркете».
Значения <tt>true</tt> и <tt>false</tt> означают соответственно доступность в наличии (без указания количества) либо «на заказ».
Другие значения означают доступность товара только «на заказ».',
            'source'      => 'field:count',
            'attribute'   => true,
            'callback'    => true,
            'field'       => 'offer',
            'values'      => array(
                /**
                 * товарное предложение на заказ. Магазин готов осуществить поставку товара на указанных условиях в течение месяца
                 * (срок может быть больше для товаров, которые всеми участниками рынка поставляются только на заказ).
                 * Те товарные предложения, на которые заказы не принимаются, не должны выгружаться в Яндекс.Маркет.
                 */
                false => 'false',
                /**
                 * товарное предложение в наличии. Магазин готов сразу договариваться с покупателем о доставке товара
                 * либо товар имеется в магазине или на складе, где осуществляется выдача товара покупателям
                 **/
                true  => 'true',
            ),
        ),
        'publisher'             => array(
            'type'   => 'adjustable',
            'name'   => 'Издательство',
            'source' => 'feature:publisher',
        ),
        'typePrefix'            => array(
            'type'   => 'adjustable',
            'name'   => 'Группа товаров/категория',
            'source' => 'field:type_id',
        ),
        'sales_notes'           => array(
            'type'        => 'adjustable',
            'name'        => 'Примечания',
            'description' => 'Информация о минимальной сумме заказа, минимальной партии товара или необходимости предоплаты, а также описания акций, скидок и распродаж',
            'available_options' => array(
                'format' => array(
                    'description' => 'Формат для вывода значения:',
                    'options'     => array(
                        '%name%: %value%'                         => '[Название поля]: [Значение]',
                        '%name%: %value% шт.'                     => '[Название поля]: [Значение] шт.',
                        '%name%: %value%%'                        => '[Название поля]: [Значение]%',
                        '%name%: %value% рублей'                  => '[Название поля]: [Значение] рублей',
                        'Минимальный заказ - %value%'             => 'Минимальный заказ - [Значение]',
                        'Необходима предоплата - %value%%'        => 'Необходима предоплата - [Значение]',
                        'Варианты оплаты - %value%'               => 'Варианты оплаты - [Значение]',
                        'Минимальная сумма заказа %value% рублей' => 'Минимальная сумма заказа [Значение] рублей',
                    ),
                ),
            ),
            'format'      => '%50s',
            'function'    => array(
                'prepaid' => 'Заказ товара по предоплате (для товаров, которых нет в наличии)',
            ),
        ),
        'min-quantity'          => array(
            'type'        => 'adjustable',
            'name'        => 'Минимальное количество',
            'description' => 'Минимальное количество товара в корзине «Яндекс.Маркета».',
            'params'      => true,
            'help'        => 'Добавить дополнительный параметр <tt>yandexmarket.min-quantity</tt> возможно при редактировании товара, вкладка «Описание и SEO», поле «Дополнительные параметры».'
                .' В текстовое поле добавьте строку вида <b>yandexmarket.min-quantity=5</b>.',
            'values'      => array(
                1  => 1,
                2  => 2,
                3  => 3,
                4  => 4,
                5  => 5,
                10 => 10,
            ),
        ),
        'step-quantity'         => array(
            'type'        => 'adjustable',
            'name'        => 'Количество товара, добавляемое к минимальному.',
            'description' => 'Количество товара, добавляемое к минимальному в корзине «Яндекс.Маркета».',
            'params'      => true,
            'help'        => 'Добавить дополнительный параметр <tt>yandexmarket.step-quantity</tt> возможно при редактировании товара, вкладка «Описание и SEO», поле «Дополнительные параметры».'
                .' В текстовое поле добавьте строку вида <b>yandexmarket.step-quantity=2</b>.',
            'values'      => array(
                1  => 1,
                2  => 2,
                3  => 3,
                4  => 4,
                5  => 5,
                10 => 10,
            ),
        ),
        'manufacturer_warranty' => array(
            'type'        => 'adjustable',
            'name'        => 'Гарантия производителя',
            'description' => 'Информация об официальной гарантии производителя',
            'help'        => 'Возможные пользовательские значения:
1) <b>false</b> — товар не имеет официальной гарантии;
2) <b>true</b> — товар имеет официальную гарантию;
3) указание срока гарантии в формате'
                .' <a href="https://ru.wikipedia.org/wiki/ISO_8601" class="inline-link" target="_blank">ISO 8601<i class="icon16 new-window"></i></a>, например: <i>P1Y2M10DT2H30M</i>
4) указание срока гарантии в числе дней;
Поддерживаются числовые данные — простое число определяет срок гарантии в днях, либо с учетом размерности характеристики типа «время».
Остальные типы данных приводятся к значениям <b>true</b>/<b>false</b>.',
            'values'      => array(
                false => 'false',
                true  => 'true',
            ),
        ),
        'seller_warranty'       => array(
            'type'        => 'adjustable',
            'name'        => 'Гарантия продавца',
            'description' => '',
            'help'        => 'Возможные пользовательские значения:
1) <b>false</b> — товар не имеет гарантию продавца;
2) <b>true</b> — товар имеет гарантию продавца;
3) указание срока гарантии в формате <a href="https://ru.wikipedia.org/wiki/ISO_8601" class="inline-link" target="_blank">ISO 8601<i class="icon16 new-window"></i></a>, например:'
                .' <i>P1Y2M10DT2H30M</i>
4) указание срока гарантии в числе дней;
Поддерживаются числовые данные — простое число определяет срок гарантии в днях, либо с учетом размерности характеристики типа «время».
Остальные типы данных приводятся к значениям <b>true</b>/<b>false</b>.',
            'values'      => array(
                false => 'false',
                true  => 'true',
            ),
        ),
        'expiry'                => array(
            'type'        => 'adjustable',
            'name'        => 'Срок годности/службы',
            'description' => '',
            'help'        => 'Возможные пользовательские значения:
1) указание срока гарантии в формате <a href="https://ru.wikipedia.org/wiki/ISO_8601" class="inline-link" target="_blank">ISO 8601<i class="icon16 new-window"></i></a>, например:'
                .'<i>P1Y2M10DT2H30M</i>
2) указание числа дней
Поддерживаются числовые данные — простое число определяет срок гарантии в днях, либо с учетом размерности характеристики типа «время».',
            'source'      => 'feature:expiry',
        ),
        'country_of_origin'     => array(
            'type'        => 'adjustable',
            'name'        => 'Страна-производитель',
            'description' => '',
        ),
        'adult'                 => array(
            'type'        => 'adjustable',
            'name'        => 'Товары для взрослых',
            'description' => 'Обязателен для обозначения товара, имеющего отношение к удовлетворению сексуальных потребностей либо иным образом эксплуатирующего интерес к сексу.',
            'values'      => array(
                false => 'false',
                true  => 'true',
            ),
        ),
        'barcode'               => array(
            'type'        => 'adjustable',
            'name'        => 'Штрихкод',
            'description' => 'Штрихкод товара, указанный производителем',
            'source'      => 'feature:barcode',
        ),
        'cpa'                   => array(
            'type'        => 'adjustable',
            'name'        => 'CPA',
            'description' => 'Участие товара в программе «Заказ на Маркете»',
            'help'        => 'Элемент может принимать следующие значения:
1) <b>0</b> — товар не участвует в программе «Заказ на Маркете»;
2) <b>1</b> — товар участвует в программе «Заказ на Маркете».
Если элемент не указан, то значение автоматически принимается равным 1 (то есть предложение без элемента <tt>cpa</tt> участвует в программе).
Добавить дополнительный параметр <tt>yandexmarket.cpa</tt> возможно при редактировании товара, вкладка «Описание и SEO», поле «Дополнительные параметры».
В текстовое поле добавьте строку вида <b>yandexmarket.cpa=0</b> или <b>yandexmarket.cpa=1</b>',
            'source'      => 'skip:',
            'values'      => array(
                0 => 'Нет',
                1 => 'Да',
            ),
            'params'      => array(
                'cpa' => 'cpa',
            ),
        ),
        'fee'                   => array(
            'type'        => 'adjustable',
            'attribute'   => true,
            'field'       => 'offer',
            'name'        => 'Комиссия',
            'description' => 'Комиссия в процентах на товарное предложение для программы «Заказ на Маркете».',
            'help'        => 'Целое положительное значение без знака %.
Если указано недопустимое значение или значение меньше минимальной комиссии, то списывается минимальная комиссия.
Добавить дополнительный параметр <tt>yandexmarket.bid</tt> возможно при редактировании товара, вкладка «Описание и SEO», поле «Дополнительные параметры».
В текстовое поле добавьте строку вида <b>yandexmarket.fee=4.45</b> или <b>yandexmarket.fee=2</b>',
            'source'      => 'skip:',
            'params'      => array(
                'fee' => 'fee',
            ),
            'format'      => '%d',
        ),
        'bid'                   => array(
            'type'        => 'adjustable',
            'attribute'   => true,
            'field'       => 'offer',
            'name'        => 'Общая ставка на клик',
            'description' => 'Комиссия в условных долларах за клик без обозначения «у. е.» или символа $.',
            'help'        => 'Добавить дополнительный параметр <tt>yandexmarket.bid</tt> возможно при редактировании товара, вкладка «Описание и SEO», поле «Дополнительные параметры».
В текстовое поле добавьте строку вида <b>yandexmarket.bid=0.45</b> или <b>yandexmarket.bid=0.87</b>',
            'source'      => 'skip:',
            'params'      => array(
                'bid' => 'bid',
            ),
            'format'      => '%d',
        ),
        'cbid'                  => array(
            'type'        => 'adjustable',
            'attribute'   => true,
            'field'       => 'offer',
            'name'        => 'Ставка на клик на карточке модели',
            'description' => 'Комиссия в условных долларах за клик на карточке модели без обозначения «у. е.» или символа $.',
            'help'        => 'Добавить дополнительный параметр <tt>yandexmarket.cbid</tt> возможно при редактировании товара, вкладка «Описание и SEO», поле «Дополнительные параметры».
В текстовое поле добавьте строку вида <b>yandexmarket.cbid=0.45</b> или <b>yandexmarket.cbid=0.87</b>',
            'source'      => 'skip:',
            'params'      => array(
                'cbid' => 'cbid',
            ),
            'format'      => '%d',
        ),
        'series'                => array(
            'type' => 'adjustable',
            'name' => 'Серия',
        ),
        'year'                  => array(
            'type'   => 'adjustable',
            'name'   => 'Год издания',
            'format' => '%d',
            'source' => 'feature:imprint_date',
        ),
        'ISBN'                  => array(
            'type'   => 'adjustable',
            'name'   => 'Код книги',
            'source' => 'feature:isbn',
        ),
        'description.*book'     => array(
            'type' => 'adjustable',
            'name' => 'Аннотация к книге',
        ),
        'volume'                => array(
            'type' => 'adjustable',
            'name' => 'Номер тома',
        ),
        'part'                  => array(
            'type' => 'adjustable',
            'name' => 'Номер тома',
        ),
        'language'              => array(
            'type'   => 'adjustable',
            'name'   => 'Язык произведения',
            'source' => 'feature:language',
        ),
        'performed_by'          => array(
            'type' => 'adjustable',
            /**
             *  Если их несколько, перечисляются через запятую
             **/
            'name' => 'Исполнитель',
        ),
        'performance_type'      => array(
            'type' => 'adjustable',
            'name' => 'Тип аудиокниги',
        ),
        'format'                => array(
            'type' => 'adjustable',
            'name' => 'Формат аудиокниги',
        ),
        'storage'               => array(
            'type' => 'adjustable',
            'name' => 'Носитель',
        ),
        'recording_length'      => array(
            'type' => 'adjustable',
            /**
             *  задается в формате mm.ss (минуты.секунды).
             */
            'name' => 'Время звучания',
        ),
        'binding'               => array(
            'type' => 'adjustable',
            'name' => 'Переплет',
        ),
        'page_extent'           => array(
            'type' => 'adjustable',
            'name' => 'Количествово страниц в книге',
        ),
        'table_of_contents'     => array(
            'type'        => 'adjustable',
            'name'        => 'Оглавление',
            'description' => 'Выводится информация о наименованиях произведений, если это сборник рассказов или стихов',
        ),
        'weight'                => array(
            'type'        => 'adjustable',
            'name'        => 'Вес товара',
            'description' => 'Только число — в килограммах с учетом упаковки.
Нулевой вес не будет экспортирован.',
            'format'      => '%0.3f',
            'source'      => 'feature:weight',
        ),
        'dimensions'            => array(
            'type'        => 'adjustable',
            'name'        => 'Габариты товара ',
            'description' => 'габариты товара (длина, ширина, высота) в упаковке',
            'format'      => '%s',
            'source'      => '',
        ),
        'media'                 => array(
            'type'        => 'adjustable',
            'name'        => 'Носитель',
            'description' => '(CD, DVD, ...)',
        ),
        'starring'              => array(
            'type'   => 'adjustable',
            'name'   => 'Актеры',
            'source' => 'feature:starring',
        ),
        'director'              => array(
            'type'   => 'adjustable',
            'name'   => 'Режиссер',
            'source' => 'feature:director',
        ),
        'originalName'          => array(
            'type'   => 'adjustable',
            'name'   => 'Оригинальное название',
            'source' => '',
        ),
        'country'               => array(
            'type'   => 'adjustable',
            'name'   => 'Страна',
            'source' => '',
        ),
        'worldRegion'           => array(
            'type'   => 'adjustable',
            'name'   => 'Часть света',
            'source' => '',
        ),
        'region'                => array(
            'type'   => 'adjustable',
            'name'   => 'Курорт или город',
            'source' => '',
        ),
        'dataTour'              => array(
            'type'        => 'adjustable',
            'name'        => 'Даты заездов',
            'description' => '',
            'format'      => '',
            'source'      => '',
        ),
        'hotel_stars'           => array(
            'type'        => 'adjustable',
            'name'        => 'Звезды отеля',
            'description' => '',
            'format'      => '',
            'source'      => 'feature:hotel_stars',
        ),
        'room'                  => array(
            'type'        => 'adjustable',
            'name'        => 'Тип комнаты',
            'description' => '(SNG, DBL, ...)',
            'format'      => '',
            'source'      => 'feature:room',
        ),
        'meal'                  => array(
            'type'        => 'adjustable',
            'name'        => 'Тип питания',
            'description' => '(All, HB, ...)',
            'format'      => '',
            'source'      => 'feature:meal',
        ),
        'included'              => array(
            'type'        => 'adjustable',
            'name'        => 'Что включено в стоимость тура',
            'description' => '',
            'format'      => '',
            'source'      => 'feature:included',
        ),
        'transport'             => array(
            'type'        => 'adjustable',
            'name'        => 'Транспорт',
            'description' => '',
            'format'      => '',
            'source'      => 'feature:transport',
        ),
        'hall'                  => array(
            'type'        => 'adjustable',
            'name'        => 'Ссылка на изображение с планом зала',
            'description' => '',
            'format'      => '',
            'source'      => '',
        ),
        'is_premiere'           => array(
            'type'        => 'adjustable',
            'name'        => 'Премьера',
            'description' => 'Признак премьерности мероприятия',
            'format'      => '',
            'source'      => '',
        ),
        'is_kids'               => array(
            'type'        => 'adjustable',
            'name'        => 'Детское мероприятие',
            'description' => 'Признак детского мероприятия.',
            'format'      => '',
            'source'      => '',
        ),
        'age'                   => array(
            'type'        => 'adjustable',
            'name'        => 'Возрастная категория',
            'description' => '',
            'values'      => array(
                'month' => array(
                    0,
                    1,
                    2,
                    3,
                    4,
                    5,
                    6,
                    7,
                    8,
                    9,
                    10,
                    11,
                    12,
                ),
                'year'  => array(
                    0,
                    6,
                    12,
                    16,
                    18,
                ),
            ),
        ),
        'store'                 => array(
            'type'        => 'adjustable',
            'name'        => 'Покупка в офлайне',
            'description' => 'Возможность приобрести товар в точке продаж без предварительного заказа через интернет',
            'help'        => 'Добавить дополнительный параметр <tt>yandexmarket.store</tt> возможно при редактировании товара, вкладка «Описание и SEO», поле'
                .' «Дополнительные параметры». В текстовое поле добавьте строку вида <b>yandexmarket.store=1</b> или <b>yandexmarket.store=0</b>.',
            'values'      => array(
                false => 'false',
                true  => 'true',
            ),
            'params'      => true,
        ),
        'pickup'                => array(
            'type'        => 'adjustable',
            'name'        => 'Самовывоз',
            'description' => 'Возможность предварительно заказать товар и забрать его в точке продаж',
            'help'        => 'Добавить дополнительный параметр <tt>yandexmarket.pickup</tt> возможно при редактировании товара, вкладка «Описание и SEO», поле'
                .' «Дополнительные параметры». В текстовое поле добавьте строку вида <b>yandexmarket.pickup=1</b> или <b>yandexmarket.pickup=0</b>.',
            'values'      => array(
                false => 'false',
                true  => 'true',
            ),
            'params'      => true,
        ),
        'delivery'              => array(
            'type'        => 'adjustable',
            'name'        => 'Доставка',
            'description' => 'Осуществляет ли ваш магазин доставку',
            'help'        => 'Добавить дополнительный параметр <tt>yandexmarket.delivery</tt> возможно при редактировании товара, вкладка «Описание и SEO», поле'
                .' «Дополнительные параметры». В текстовое поле добавьте строку вида <b>yandexmarket.delivery=1</b> или <b>yandexmarket.delivery=0</b>.',
            'values'      => array(
                /**
                 * данный товар не может быть доставлен
                 */
                false => 'false',
                /**
                 * товар доставляется на условиях, которые указываются в партнерском интерфейсе
                 * http:partner.market.yandex.ru на странице «редактирование»
                 **/
                true  => 'true',
            ),
            'params'      => true,

        ),
        'local_delivery_before' => array(
            'type'        => 'adjustable',
            'name'        => 'Время приема заказа',
            'description' => 'Время оформления заказа (только часы), до наступления которого действуют указанные сроки и условия доставки. Например, дополнительный параметр товара yandexmarket.local_delivery_before.',
            'params'      => true,
            'help'        => 'Добавить дополнительный параметр <tt>yandexmarket.local_delivery_before</tt> возможно при редактировании товара, вкладка «Описание и SEO», поле «Дополнительные параметры».'
                .' В текстовое поле добавьте строку вида <b>yandexmarket.local_delivery_before=17</b>.',
            'path'        => 'delivery-options/option[order-before]',
            'virtual'     => true,
            'test'        => array(
                array(null, ''),
                array(null, false),
            ),
        ),
        'local_delivery_days'   => array(
            'type'        => 'adjustable',
            'name'        => 'Сроки доставки',
            'description' => 'Срок доставки данного товара в своем регионе (например, дополнительный параметр товара yandexmarket.local_delivery_days)',
            'help'        => 'Добавить дополнительный параметр <tt>yandexmarket.local_delivery_days</tt> возможно при редактировании товара, вкладка «Описание и SEO», поле'
                .' «Дополнительные параметры». В текстовое поле добавьте строку вида <b>yandexmarket.local_delivery_days=3</b>.',
            'params'      => true,
            'path'        => 'delivery-options/option[days]',
            'virtual'     => true,
        ),
        'local_delivery_cost'   => array(
            'type'        => 'adjustable',
            'name'        => 'Стоимость доставки',
            'plugins'     => array(
                //@future
                // 'shipping' => true,
            ),
            'description' => 'Стоимость доставки данного товара в своем регионе (например, дополнительный параметр товара yandexmarket.local_delivery_cost).'
                .' Указывается в валюте предложения, если не включена опция конвертирования цен, иначе указывается в основной валюте, настроенной в плагине.',
            'params'      => true,
            'help'        => 'Следует указывать максимальную цену доставки по городу (своему региону), чтобы не возникло ошибок по качеству.
Добавить дополнительный параметр <tt>yandexmarket.local_delivery_cost</tt> возможно при редактировании товара, вкладка «Описание и SEO», поле «Дополнительные параметры».'
                .' В текстовое поле добавьте строку вида <b>yandexmarket.local_delivery_cost=100</b>.',
            'path'        => 'delivery-options/option[cost]',
            'virtual'     => true,
            'callback'    => true,
            'values'      => array(
                'fixed' => 'Фиксированная стоимость доставки',
            ),
            'test'        => array(
                array(null, ''),
                array(null, false),
            ),
        ),
        'rec'                   => array(
            'type'     => 'adjustable',
            'name'     => 'Рекомендуемые товары',
            'help'     => 'Экспорт рекомендуемых товаров на основе автоматического выбора требует дополнительных ресурсов сервера.',
            'params'   => false,
            'function' => array(
                'cross_selling.static' => 'Перекрестные продажи (cross-selling) — выбранные вручную для товара',
                'cross_selling.all'    => 'Перекрестные продажи (cross-selling) — автоматический подбор',
                'upselling.static'     => 'Схожие и альтернативные товары (upselling) — выбранные вручную для товара',
                'upselling.all'        => 'Схожие и альтернативные товары (upselling) — автоматический подбор',
            ),
            'sources'  => array(
                'function',
            ),
        ),
        'param'                 => array(
            'type'        => 'adjustable',
            'name'        => '<param>',
            'description' => 'Дополнительные произвольные характеристики товара.',
            'help'        => 'Если у характеристики нет единицы измерения, но какую-то фиксированную единицу нужно передать в «Яндекс.Маркет», напишите эту единицу выше в поле для'
                .' атрибута <tt>unit</tt> или добавьте нужную единицу в скобках в названии характеристики в настройках магазина.<br>'
                .'Если в названии характеристики есть слово в скобках, но в «Яндекс.Маркет» не нужно экспортировать атрибут <tt>unit</tt>,'
                .' введите пробел в поле для этого атрибута.',
        ),
    ),
);
