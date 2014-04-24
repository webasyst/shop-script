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
                'currencyId'            => true,
                'categoryId'            => true,
                'market_category'       => false,
                'picture'               => false,
                'store'                 => false,
                'pickup'                => false,
                'delivery'              => false,
                'local_delivery_cost'   => false,
                'name'                  => true,
                'vendor'                => false,
                'vendorCode'            => false,
                'description'           => false,
                'sales_notes'           => false,
                'manufacturer_warranty' => false,
                'country_of_origin'     => false,
                'adult'                 => false,
                'age'                   => false,
                'barcode'               => false,
                'cpa'                   => false,
                'param'                 => false,
            ),
        ),

        'vendor.model' => array(
            'name'   => 'Произвольный товар (vendor.model)',
            'fields' => array(
                'available'             => true,
                'id'                    => true,

                'url'                   => true,
                'price'                 => true,
                'currencyId'            => true,
                'categoryId'            => true,
                'market_category'       => false,
                'picture'               => true,
                /**
                 *
                 * Ссылка на картинку соответствующего товарного предложения. Недопустимо давать ссылку на «заглушку», т.е. на страницу, где написано «картинка отсутствует», или на логотип магазина. Максимальная длина URL — 512 символов.
                 *
                 * Для товарных предложений, относящихся к категории «Одежда и обувь», является обязательным элементом. Для всех остальных категорий – необязательный элемент.
                 **/
                'store'                 => false,
                'pickup'                => false,
                'delivery'              => false,
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
                'manufacturer_warranty' => false,
                'seller_warranty'       => false,
                'country_of_origin'     => true,
                'downloadable'          => false,
                'adult'                 => false,
                'age'                   => false,
                'barcode'               => false,
                'cpa'                   => false,
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
                'dimensions'            => true,
                'param'                 => true,
                /**
                 *
                 * Элемент предназначен для указания характеристик товара. Для описания каждого параметра используется отдельный элемент <param>.
                 *
                 * Необязательный элемент. Элемент <offer> может содержать несколько элементов <param>.
                 */
            ),
        ),
        'book'         => array(
            'name'   => 'Книги (book)',
            'fields' => array(
                'available'           => true,
                'id'                  => true,

                'url'                 => true,
                'price'               => true,
                'currencyId'          => true,
                'categoryId'          => true,
                'market_category'     => false,
                'picture'             => false,
                'store'               => false,
                'pickup'              => false,
                'delivery'            => false,
                'local_delivery_cost' => false,
                'author'              => false,
                'name'                => true,
                'publisher'           => false,
                'series'              => false,
                'year'                => false,
                'ISBN'                => false,
                'volume'              => false,
                'part'                => false,
                'language'            => false,
                'binding'             => false,
                'page_extent'         => false,
                'table_of_contents'   => false,
                'description'         => false,
                'downloadable'        => false,
                'age'                 => false,
                'cpa'                 => false,
            ),
        ),
        'audiobook'    => array(
            'name'   => 'Аудиокниги (audiobook)',
            'fields' => array(
                'available'         => true,
                'id'                => true,

                'url'               => true,
                'price'             => true,
                'currencyId'        => true,
                'categoryId'        => true,
                'market_category'   => false,
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
                'downloadable'      => false,
                'age'               => false,
                'cpa'               => false,
            ),
        ),
        'artist.title' => array(
            'name'   => 'Музыкальная и видео продукция (artist.title)',
            'fields' => array(
                'available'       => true,
                'id'              => true,

                'url'             => true,
                'price'           => true,
                'currencyId'      => true,
                'categoryId'      => true,
                'market_category' => false,
                'picture'         => false,
                'store'           => false,
                'pickup'          => false,
                'delivery'        => false,
                'artist'          => false,
                'title'           => true,
                'year'            => false,
                'media'           => false,

                'starring'        => false,
                /**
                 * Актеры.
                 **/
                'director'        => false,
                /**
                 * Режиссер.
                 **/
                'originalName'    => false,
                /**
                 * Оригинальное название.
                 **/
                'country'         => false,
                /**
                 * Страна.
                 */

                'description'     => false,
                'adult'           => false,
                'age'             => false,
                'barcode'         => false,
                'cpa'             => false,
            ),
        ),
        'tour'         => array(
            'name'   => 'Туры (tour)',
            'fields' => array(
                'available'       => true,
                'id'              => true,

                'url'             => true,
                'price'           => true,
                'currencyId'      => true,
                'categoryId'      => true,
                'market_category' => false,
                'picture'         => false,
                'store'           => false,
                'pickup'          => false,
                'delivery'        => false,
                'worldRegion'     => false,
                /**
                 * Часть света.
                 **/
                'country'         => false,
                /**
                 * Страна.
                 **/
                'region'          => false,
                /**
                 * Курорт или город.
                 **/
                'days'            => true,
                'dataTour'        => false,
                'name'            => true,
                'hotel_stars'     => false,
                'room'            => false,
                'meal'            => false,
                'included'        => true,
                'transport'       => true,
                'description'     => false,
                'age'             => false,
            ),
        ),
        'event-ticket' => array(

            'name'   => 'Билеты на мероприятие (event-ticket)',
            'fields' => array(
                'available'       => true,
                'id'              => true,

                'url'             => true,
                'price'           => true,
                'currencyId'      => true,
                'categoryId'      => true,
                'market_category' => false,
                'picture'         => false,
                'store'           => false,
                'pickup'          => false,
                'delivery'        => false,
                'name'            => true,
                'place'           => true,
                'hall'            => false,
                /**
                 * Ссылка на изображение с планом зала.
                 **/
                'date'            => true,
                'is_premiere'     => false,
                /**
                 * Признак премьерности мероприятия.
                 **/
                'is_kids'         => false,
                /**
                 * Признак детского мероприятия.
                 **/
                'age'             => false,
            ),
        ),
    ),

    'fields' => array(

        /**
         * Fields order is user friendly
         */
        /*
       * 'field_id'              => array(
       * 'type'        => 'fixed|adjustable|custom',
       * 'name'        => '',
       * 'description' => '',
       * ),
       */
        'id'                    => array(
            'type'        => 'fixed',
            'name'        => 'идентификатор товарного предложения',
            'description' => '',
            'attribute'   => true,
            'source'      => 'field:id',
        ),

        'url'                   => array(
            'type'        => 'fixed',
            'name'        => 'URL — адрес страницы товара',
            'description' => '',
            'format'      => '%0.512s',
            'source'      => 'field:frontend_url',
        ),

        'price'                 => array(
            'type'        => 'fixed',
            'name'        => 'Цена',
            'description' => 'Цена товарного предложения округляеся и выводится в зависимости от настроек пользователя',
            'format'      => '%0.2f',
            'source'      => 'field:price',
        ),

        'currencyId'            => array(
            'type'        => 'fixed',
            'name'        => 'Идентификатор валюты товара',
            'description' => 'Для корректного отображения цены в национальной валюте необходимо использовать идентификатор с соответствующим значением цены',
            'values'      => array(
                'RUB', 'USD', 'UAH', 'KZT', 'BYR', 'EUR',
            ),
            'source'      => 'field:currency',
        ),

        'categoryId'            => array(
            'type'        => 'fixed',
            'name'        => 'Идентификатор категории товара ',
            'description' => '(целое число не более 18 знаков). Товарное предложение может принадлежать только к одной категории',
            'source'      => 'field:category_id',
        ),
        'market_category'       => array(
            'type'        => 'fixed',
            'name'        => 'Идентификатор категории товара ',
            'description' => '',
            'source'      => 'field:market_category',
        ),

        'picture'               => array(
            'type'   => 'fixed',
            'name'   => 'Ссылка на изображение соответствующего товарного предложения',
            'source' => 'field:images',
        ),

        'downloadable'          => array(
            'type'        => 'fixed',
            'name'        => 'Цифровой товар',
            'description' => 'Обозначение товара, который можно скачать',
        ),

        /**
         * adjustable
         */


        'vendor'                => array(
            'type'        => 'adjustable',
            'name'        => 'Производитель',
            'description' => 'Не отображается в названии предложения',
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
            'source' => 'feature:author'
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
            'type'        => 'adjustable',
            'name'        => 'Описание',
            'description' => 'Описание товарного предложения',
            'source'      => 'field:summary',
        ),

        'available'             => array(
            'type'        => 'adjustable',
            'name'        => 'Наличие',
            'description' => 'Статус доступности товара в наличии/на заказ',
            'source'      => 'field:count',
            'attribute'   => true,
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
            'format'      => '%50s',
        ),

        'manufacturer_warranty' => array(
            'type'        => 'adjustable',
            'name'        => 'Гарантия производителя',
            'description' => 'Информация об официальной гарантии производителя
Возможные пользовательские значения:
1) false — товар не имеет официальной гарантии;
2) true — товар имеет официальную гарантию;
3) указание срока гарантии в формате ISO 8601, например: P1Y2M10DT2H30M
4) указание срока гарантии в чисое дней;
Поддерживаются числовые данные — простое число определяет срок гарантии в днях, либо с учетом размерности характеристики типа «Время».
Остальные типы данных приводятся к значениям true/false.',
            'values'      => array(
                false => 'false',
                true  => 'true',
            ),
        ),


        'seller_warranty'       => array(
            'type'        => 'adjustable',
            'name'        => 'Гарантия продавца',
            'description' => 'Возможные пользовательские значения:
1) false — товар не имеет гарантию продавца;
2) true — товар имеет гарантию продавца;
3) указание срока гарантии в формате ISO 8601, например: P1Y2M10DT2H30M;
4) указание срока гарантии в чисое дней;
Поддерживаются числовые данные — простое число определяет срок гарантии в днях, либо с учетом размерности характеристики типа «Время».
Остальные типы данных приводятся к значениям true/false.',
            'values'      => array(
                false => 'false',
                true  => 'true',
            ),
        ),
        'expiry'                => array(
            'type'        => 'adjustable',
            'name'        => 'Срок годности/службы',
            'description' => '
Возможные пользовательские значения:
1) указание срока гарантии в формате ISO 8601, например: P1Y2M10DT2H30M
2) указание числа дней
Поддерживаются числовые данные — простое число определяет срок гарантии в днях, либо с учетом размерности характеристики типа «Время».',
            'source'      => 'feature:expiry',
        ),
        'country_of_origin'     => array(
            'type'        => 'adjustable',
            'name'        => 'Страна производитель',
            'description' => '',
        ),

        'adult'                 => array(
            'type'        => 'adjustable',
            'name'        => 'Товары для взрослых',
            'description' => 'Обязателен для обозначения товара, имеющего отношение к удовлетворению сексуальных потребностей либо иным образом эксплуатирующего интерес к сексу',
            'values'      => array(
                false => 'false',
                true  => 'true',
            ),
        ),

        'barcode'               => array(
            'type'        => 'adjustable',
            'name'        => 'Штрихкод',
            'description' => 'Штрихкод товара, указанный производителем',
            'source'      => 'feature:barcode'
        ),
        'cpa'                   => array(
            'type'   => 'fixed',
            'source' => 'skip:'
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
            'description' => 'Вес указывается в килограммах с учетом упаковки',
            'format'      => '%0.4f',
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
            'description' => 'Признак примьерности мероприятия',
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
                    0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12
                ),
                'year'  => array(
                    0, 6, 12, 16, 18,
                ),
            ),
        ),

        'store'                 => array(
            'type'        => 'adjustable',
            'name'        => 'Покупка в офлайне',
            'description' => 'Возможность приобрести товар в точке продаж без предварительного заказа через интернет',
            'values'      => array(
                false => 'false',
                true  => 'true',
            ),
        ),

        'pickup'                => array(
            'type'        => 'adjustable',
            'name'        => 'Самовывоз',
            'description' => 'Возможность предварительно заказать товар и забрать его в точке продаж',
            'values'      => array(
                false => 'false',
                true  => 'true',
            ),
        ),

        'delivery'              => array(
            'type'        => 'adjustable',
            'name'        => 'Доставка',
            'description' => 'Осуществляет ли ваш магазин доставку',
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

        ),

        'local_delivery_cost'   => array(
            'type'        => 'adjustable',
            'name'        => 'Стоимость доставки',
            'description' => 'Стоимость доставки данного товара в своем регионе',
        ),

    )
);
