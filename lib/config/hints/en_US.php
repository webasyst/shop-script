<?php

return array(
    'product_main' => array(
        'text' => 'Product name, URL, prices, stock quantities, categories, visibility status.'
    ),

    'product_name' => array(
        'text' => 'Main product name visible in the storefront.'
    ),

    'product_type' => array(
        'text' => 'Each product type; e.g., “TVs”, “Phones”, or “Clocks”, can have its own set of features. You can decide which product types to make visible in a storefront. Separate access rights to product types can be set up for backend users and user groups.',
        'link' => 'https://www.shop-script.com/help/11307/product-types/'
    ),

    'product_tax' => array(
        'text' => 'You can select a tax rule for every product. Tax rules’ settings may take various countries and their regions into account.',
        'link' => 'https://www.shop-script.com/help/11311/taxes/'
    ),

    'product_categories' => array(
        'text' => 'Group of products with its own page in the storefront. A product can be included in one or several categories.',
        'link' => 'https://www.shop-script.com/help/53/product-categories/'
    ),

    'product_sets' => array(
        'text' => 'A group of products to be displayed on existing storefront pages; e.g., on the home page or in a slider. A product can be included in one or more sets.',
        'link' => 'https://www.shop-script.com/help/42/product-sets/'
    ),

    'product_skus' => array(
        'text' => 'Product prices and stock quantities. Add extra product variants, or SKUs, with their own prices and stock quantities.',
        'link' => 'https://www.shop-script.com/help/11312/fixed-vs-virtual-skus/'
    ),

    'product_skus_plain' => array(
        'text' => 'Product variants, or SKUs. A shopper can see a list of SKUs defined in this section and can select one to add to cart.'
    ),

    'product_skus_features' => array(
        'text' => 'Product variants, or SKUs, each with a unique combination of various features such as color, size etc. A shopper can see feature values on a product page instead of an SKU list. When the desired feature values are selected, the corresponding SKU is added to the cart. For setup of selectable SKU parameters the values of “Multiple values (checkbox)” type features are used.'
    ),

    'product_descriptions' => array(
        'text' => 'SEO fields — descriptions and tags for search engines, social media, and messengers.'
    ),

    'product_images' => array(
        'text' => 'Upload product images and add a link to a product video. Add descriptions and badges to product images.'
    ),

    'product_features' => array(
        'text' => 'Configurable extra product properties; e.g., weight, color, size, etc. The available list of features can be set up differently by product type.'
    ),

    'product_services' => array(
        'text' => 'Option for a shopper to add extra value or to select an individual property, or service, when ordering a product. A service may be available for payment or free of charge.',
        'link' => 'https://www.shop-script.com/help/26893/product-services/'
    ),

    'product_related' => array(
        'text' => 'Offers for shoppers to order complementary or similar products. The available list of offers is configured manually in product-editing page or by automatic rules.'
    ),

    'product_pages' => array(
        'text' => 'Web pages with additional product-related information. A shopper can open them via links on the main product-viewing page to better learn its values before ordering.'
    ),

    'customers_search_show_contacts_customers' => array(
        'text' => 'Displayed will be only customers who have at least one order. Their orders do not necessarily need to be paid.',
    ),

    'customers_search_show_contacts_unpaid' => array(
        'text' => 'Displayed will be customers who have at least one order, and none of their orders may be paid.',
    ),

    'customers_search_show_contacts_all' => array(
        'text' => 'Displayed will be all contacts. Search fields related to orders will not be taken into account.',
    ),

    'customers_search_consider_orders_paid' => array(
        'text' => 'Displayed will be only customers who have orders labeled as “Paid”. Other search fields, if completed, will be taken into account only for paid orders while non-paid ones will be ignored.',
    ),

    'customers_search_consider_orders_unpaid' => array(
        'text' => 'Displayed will be only customers who have orders not labeled as “Paid”. Other search fields, if completed, will be taken into account only for non-paid orders while paid ones will be ignored.',
    ),
    'marketing_promo_stock_price_products' => array(
        'text' => 'A special product price specified for a promo becomes a product’s base price during the promo period and is not considered as a discounted price. Discounts are calculated relative to this base promo price.',
    ),
);
