<?php

return array(
    /*
     'field_id'              => array(
     'type'        => 'fixed|adjustable|custom',
     'category'    => array('book', 'tours', 'tickets', 'media', ),
     'name'        => '',
     'description' => '',
     ),
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
        'name'        => 'URL - адрес страницы товара',
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
        'description' => 'Для корректного отображения цены в национальной валюте, необходимо использовать идентификатор с соответствующим значением цены',
        'values'      => array(
            'RUB', 'USD', 'UAH', 'KZT', 'BYR', 'EUR',
        ),
        'source'      => 'field:currency',
    ),

    'categoryId'            => array(
        'type'        => 'fixed',
        'name'        => 'Идентификатор категории товара ',
        'description' => '(целое число не более 18 знаков). Товарное предложение может принадлежать только одной категории',
        'source'      => 'field:category_id',
    ),

    'picture'               => array(
        'type'   => 'fixed',
        'name'   => 'Ссылка на картинку соответствующего товарного предложения. ',
        'source' => 'field:images',
    ),

    'store'                 => array(
        'type'        => 'adjustable',
        'name'        => 'Покупка в офлайне',
        'description' => 'Возможность приобрести товар в точке продаж без предварительного заказа по интернету',
        'values'      => array(
            false => 'false',
            true => 'true',
        ),
        'sort'        => 35,
    ),

    'pickup'                => array(
        'type'        => 'adjustable',
        'name'        => 'Самовывоз',
        'description' => 'Возможность предварительно заказать товар и забрать его в точке продаж',
        'values'      => array(
            false => 'false',
            true => 'true',
        ),
        'sort'        => 36,
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
             * http:partner.market.yandex.ru на странице "редактирование"
             **/
            true => 'true',
        ),

        'sort'        => 37,
    ),

    'downloadable'          => array(
        'type'        => 'fixed',
        'name'        => 'Цифровой товар',
        'description' => 'Обозначение товара, который можно скачать',
    ),
    /* BEGIN vendor.model*/
    'typePrefix'            => array(
        'type'     => 'adjustable',
        'name'     => 'Группа товаров/категория',
        'source'   => 'field:type_id',
        'category' => array('vendor.model', )
    ),
    'name'                  => array(
        'type'        => 'adjustable',
        'name'        => 'Название',
        'description' => 'Наименование товарного предложения',
        'source'      => 'field:name',
        'sort'        => 1,
    ),

    'vendor'                => array(
        'type'        => 'adjustable',
        'name'        => 'Производитель',
        'description' => '',
        'category'    => array('vendor.model', 'simple', ),
        'sort'        => 5,
    ),

    'vendorCode'            => array(
        'type'        => 'adjustable',
        'name'        => 'Код',
        'description' => 'Код производителя',
        'category'    => array('vendor.model', 'simple', ),
        'sort'        => 6,
    ),

    'model'                 => array(
        'type'        => 'adjustable',
        'name'        => 'Модель',
        'description' => '',
        'category'    => array('vendor.model', )
    ),
    /* END vendor.model*/

    'description'           => array(
        'type'        => 'adjustable',
        'name'        => 'Описание',
        'description' => 'Описание товарного предложения',
        'source'      => 'field:summary',
        'sort'        => 2,
    ),

    'local_delivery_cost'   => array(
        'type'        => 'adjustable',
        'name'        => 'Стоимость доставки',
        'description' => 'Стоимость доставки данного товара в Своем регионе',
        'sort'        => 38,
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
            true => 'true',
        ),
        'sort'        => 3,
    ),

    'sales_notes'           => array(
        'type'        => 'adjustable',
        'name'        => 'Примечания',
        'description' => 'Информация о минимальной сумме заказа, минимальной партии товара или необходимости предоплаты, а так же описания акций, скидок и распродаж',
        'format'      => '%50s',
        'sort'        => 4,
    ),

    'manufacturer_warranty' => array(
        'type'        => 'adjustable',
        'name'        => 'Гарантия',
        'description' => 'Информация об официальной гарантии производителя',
        'sort'        => 21,
    ),
    'country_of_origin'     => array(
        'type'        => 'adjustable',
        'name'        => 'Страна производитель',
        'description' => '',
        'sort'        => 20,
    ),

    'adult'                 => array(
        'type'        => 'adjustable',
        'name'        => 'Товары для взрослых',
        'description' => 'Обязателен для обозначения товара, имеющего отношение к удовлетворению сексуальных потребностей, либо иным образом эксплуатирующего интерес к сексу',
        'values'      => array(
            false => 'false',
            true => 'true',
        ),
        'sort'        => 22,
    ),

    'age'                   => array(
        'type'        => 'adjustable',
        'name'        => 'Возрастная категория',
        'description' => '',
        'values'      => array(
            0, 6, 12, 16, 18,
        ),
        'category'    => array('book', 'audiobook', ),
    ),

    'barcode'               => array(
        'type'        => 'adjustable',
        'name'        => 'Штрихкод',
        'description' => 'Штрихкод товара, указанный производителем',
        'sort'        => 23,
    ),

    'author'                => array(
        'type'     => 'adjustable',
        'name'     => 'Автор произведения',
        'category' => array('book', 'audiobook', ),
    ),
    'name.book|audiobook'   => array(
        'type'     => 'adjustable',
        'name'     => 'Наименование произведения',
        'category' => array('book', 'audiobook', ),
    ),
    'publisher'             => array(
        'type'     => 'adjustable',
        'name'     => 'Издательство',
        'category' => array('book', 'audiobook', ),
    ),
    'series'                => array(
        'type'     => 'adjustable',
        'name'     => 'Серия',
        'category' => array('book', 'audiobook', ),
    ),
    'year'                  => array(
        'type'     => 'adjustable',
        'name'     => 'Год издания',
        'category' => array('book', 'audiobook', ),
    ),
    'ISBN'                  => array(
        'type'     => 'adjustable',
        'name'     => 'Код книги',
        'category' => array('book', 'audiobook', ),
    ),
    'description.*book'     => array(
        'type'     => 'adjustable',
        'name'     => 'Аннотация к книге',
        'category' => array('book', 'audiobook', ),
    ),
    'volume'                => array(
        'type'     => 'adjustable',
        'name'     => 'Количество томов',
        'category' => array('book', 'audiobook', ),
    ),
    'part'                  => array(
        'type'     => 'adjustable',
        'name'     => 'Номер тома',
        'category' => array('book', 'audiobook', ),
    ),
    'language'              => array(
        'type'     => 'adjustable',
        'name'     => 'Язык произведения',
        'category' => array('book', 'audiobook', ),
    ),
    'performed_by'          => array(
        'type'     => 'adjustable',
        /**
         *  Если их несколько, перечисляются через запятую
         **/
        'name'     => 'Исполнитель',
        'category' => array('audiobook', ),
    ),
    'performance_type'      => array(
        'type'     => 'adjustable',
        'name'     => 'Тип аудиокниги',
        'category' => array('audiobook', ),
    ),
    'format'                => array(
        'type'     => 'adjustable',
        'name'     => 'Формат аудиокниги',
        'category' => array('audiobook', ),
    ),
    'storage'               => array(
        'type'     => 'adjustable',
        'name'     => 'Носитель',
        'category' => array('audiobook', ),
    ),
    'recording_length'      => array(
        'type'     => 'adjustable',
        /**
         *  задается в формате mm.ss (минуты.секунды).
         */
        'name'     => 'Время звучания',
        'category' => array('audiobook', ),
    ),
    'binding'               => array(
        'type'     => 'adjustable',
        'name'     => 'Переплет',
        'category' => array('book', ),
    ),
    'page_extent'           => array(
        'type'     => 'adjustable',
        'name'     => 'Количествово страниц в книге',
        'category' => array('book', ),
    ),
    'table_of_contents'     => array(
        'type'        => 'adjustable',
        'name'        => 'Оглавление',
        'description' => 'Выводится информация о наименованиях произведений, если это сборник рассказов или стихов',
        'category'    => array('book', 'audiobook', ),
    ),
);
