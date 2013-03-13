<?php

wa('shop');
$type_model = new shopTypeModel();
$types = $type_model->select('id,name')->fetchAll('id', true);

$currencies = wa('shop')->getConfig()->getCurrencies();
foreach ($currencies as &$c) {
    $c = $c['title'];
}

$stock_model = new shopStockModel();
$stocks = $stock_model->select('id,name')->order('sort')->fetchAll('id', true);

return array(
    'params' => array(
        'title' => array(
            'name' => _w('Homepage title <title>'),
            'type' => 'input',
        ),
        'meta_keywords' => array(
            'name' => _w('Homepage META Keywords'),
            'type' => 'input'
        ),
        'meta_description' => array(
            'name' => _w('Homepage META Description'),
            'type' => 'textarea'
        ),
        'url_type' => array(
            'name' => _w('URLs'),
            'type' => 'radio_select',
            'items' => array(
                2 => array(
                    'name' => _w('Natural'),
                    'description' => _w('<br>Product URLs: /<strong>category-name/subcategory-name/product-name/</strong><br>Category URLs: /<strong>category-name/subcategory-name/</strong>'),
                ),
                0 => array(
                    'name' => _w('Mixed'),
                    'description' => _w('<br>Product URLs: /<strong>product-name/</strong><br>Category URLs: /category/<strong>category-name/subcategory-name/subcategory-name/...</strong>'),
                ),
                1 => array(
                    'name' => _w('Plain').' (WebAsyst Shop-Script)',
                    'description' => _w('<br>Product URLs: /product/<strong>product-name/</strong><br>Category URLs: /category/<strong>category-name/</strong>'),
                ),

            )
        ),
        'type_id' => array(
            'name' => _w('Published products'),
            'type' => 'radio_checkbox',
            'items' => array(
                0 => array(
                    'name' => _w('All product types'),
                    'description' => '',
                ),
                array (
                    'name' => _w('Selected only'),
                    'description' => '',
                    'items' => $types
                )
            )
        ),
        'currency' => array(
            'name' => _w('Default currency'),
            'type' => 'select',
            'items' => $currencies
        ),
        'stock_id' => array(
            'name' => _w('Default stock'),
            'description' => _w('Select primary stock to which this storefront is associated with. When you process orders from placed via this storefront, selected stock will be automatically offered for product stock update.'),
            'type' => 'select',
            'items' => $stocks
        )

    ),

    'vars' => array(
        'category.html' => array(
            '$category.id' => '',
            '$category.name' => '',
            '$category.parent_id' => '',
            '$category.description' => '',
        ),
        'index.html' => array(
            '$content' => _w('Core content loaded according to the requested resource: product, category, search results, static page, etc.'),
        ),
        'product.html' => array(
            '$product.id' => _w('Product id. Other elements of <em>$product</em> available in this template are listed below'),
            '$product.name' => '',
            '$product.description' => '',
            '$product.rating' => '0 to 5',
            '$product.skus' => 'Array of product SKUs',
            '$product.images' => 'Array of product images',
            '$product.categories' => 'Array of product categories',
            '$product.tags' => 'Array of product tags',
            '$product.pages' => 'Array of product static info pages',
            '$product.features' => 'Array of product features and values',

            '$reviews' => 'Array of product reviews',
            '$services' => 'Array of services available for this product',

            '$category' => _w('Conditional! Available only if current context of photo is album. Below are describe keys of this param'),
            '$category.id' => '',
            '$category.name' => '',
            '$category.parent_id' => '',
            '$category.description' => '',

        ),
        'search.html' => array(
            '$title' => ''
        ),
        'list-table.html' => array(
            '$products' => array(
                '$id' => '',
                '...' => _w('Available vars are listed in the cheat sheet for product.html template')
            )
        ),
        'list-thumbs.html' => array(
            '$products' => array(
                '$id' => '',
                '...' => _w('Available vars are listed in the cheat sheet for product.html template')
            )
        ),
        '$wa' => array(
            '$wa->shop->badgeHtml(<em>$product.code</em>)' => _w('Displays badge of the specified product (<em>$product</em> object)'),
            '$wa->shop->cart()' => _w('Returns current cart object'),
            '$wa->shop->categories(<em>$parent_id = 0</em>)' => _w('Returns array of subcategories of the specified category. Omit parent category for the entire array of categories'),
            '$wa->shop->crossSelling(<em>$product_id</em>, <em>$limit = 5</em>)' => _w('Returns array of cross-sell products.<em>$product_id</em> can be either a number (ID of the specified base product) or an array of products IDs'),
            '$wa->shop->currency()' => _w('Returns current currency object'),
            '$wa->shop->product(<em>$product_id</em>)' => _w('Returns product object by <em>$product_id</em>'),
            '<em>$product</em>->productUrl()' => _w('Returns valid product page URL'),
            '<em>$product</em>->upSelling()' => _w('Returns array of upsell products for the specified product'),
            '$wa->shop->productImgHtml($product, $size, $attributes = array())' => _w('Displays specified $product object’s default image'),
            '$wa->shop->productImgUrl($product, $size)' => _w('Returns specified $product default image URL'),
            '$wa->shop->productSet(<em>set_id</em>)' => _w('Returns array of products from the specified set'),
            '$wa->shop->ratingHtml(<em>$rating, $size = 10, $show_when_zero = false</em>)' => _w('Displays 1—5 stars rating. $size indicates icon size and can be either 10 or 16'),
            '$wa->shop->settings("<em>option_id</em>")' => _w('Returns store’s general setting option by <em>option_id</em>, e.g. "name", "email", "country"'),
        ),
    ),
    'blocks' => array(

    ),
);
