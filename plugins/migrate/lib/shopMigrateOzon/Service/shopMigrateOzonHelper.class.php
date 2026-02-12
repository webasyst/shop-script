<?php

class shopMigrateOzonHelper
{
    public static function getTablesMeta()
    {
        return array(
            'shop_migrate_ozon_snapshots' => array(
                'title'       => _wp('Снапшоты'),
                'description' => _wp('История снимков и служебные данные.'),
            ),
            'shop_migrate_ozon_products' => array(
                'title'       => _wp('Товары'),
                'description' => _wp('Список товаров, полученных из Ozon.'),
            ),
            'shop_migrate_ozon_categories' => array(
                'title'       => _wp('Категории'),
                'description' => _wp('Плоское дерево категорий Ozon.'),
            ),
            'shop_migrate_ozon_attributes' => array(
                'title'       => _wp('Атрибуты'),
                'description' => _wp('Описания атрибутов категорий и типов.'),
            ),
            'shop_migrate_ozon_attribute_values' => array(
                'title'       => _wp('Значения атрибутов'),
                'description' => _wp('Значения атрибутов товаров Ozon.'),
            ),
            'shop_migrate_ozon_warehouses' => array(
                'title'       => _wp('Склады'),
                'description' => _wp('Склады, найденные в снапшоте.'),
            ),
            'shop_migrate_ozon_stocks' => array(
                'title'       => _wp('Остатки'),
                'description' => _wp('Остатки по складам и предложениям.'),
            ),
            'shop_migrate_ozon_type_map' => array(
                'title'       => _wp('Сопоставление типов'),
                'description' => _wp('Автоматические связи типов товаров.'),
            ),
            'shop_migrate_ozon_category_map' => array(
                'title'       => _wp('Сопоставление категорий'),
                'description' => _wp('Автоматические связи категорий.'),
            ),
            'shop_migrate_ozon_stock_map' => array(
                'title'       => _wp('Сопоставление складов'),
                'description' => _wp('Связи складов Ozon со складами Shop-Script.'),
            ),
            'shop_migrate_ozon_feature_map' => array(
                'title'       => _wp('Сопоставление характеристик'),
                'description' => _wp('Созданные характеристики и их связи.'),
            ),
            'shop_migrate_ozon_product_map' => array(
                'title'       => _wp('Сопоставление товаров'),
                'description' => _wp('Привязки offer_id к товарам Shop-Script.'),
            ),
        );
    }
}
