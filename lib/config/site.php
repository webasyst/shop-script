<?php

wa('shop');
$type_model = new shopTypeModel();
$types = $type_model->select('id,name')->fetchAll('id', true);

$currencies = wa('shop')->getConfig()->getCurrencies();
foreach ($currencies as &$c) {
    $c = $c['title'];
}

$payment_items = $shipping_items = array();
foreach (shopHelper::getPaymentMethods() as $p) {
    $payment_items[$p['id']] = $p['name'];
}
foreach (shopHelper::getShippingMethods() as $s) {
    $shipping_items[$s['id']] = $s['name'];
}

$stock_model = new shopStockModel();
$stocks = $stock_model->select('id,name')->order('sort')->fetchAll('id', true);

return array(
    'params' => array(
        _w('Homepage'),
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
        'og_title' => array(
            'name' => _w('Social sharing Title (og:title)'),
            'type' => 'input',
            'description' => _w('For detailed information on Open Graph parameters and examples please refer to <a href="http://ogp.me" target="_blank">ogp.me</a>')
        ),
        'og_image' => array(
            'name' => _w('Social sharing Image URL (og:image)'),
            'type' => 'input'
        ),
        'og_video' => array(
            'name' => _w('Social sharing Video URL (og:video)'),
            'type' => 'input'
        ),
        'og_description' => array(
            'name' => _w('Social sharing Description (og:description)'),
            'type' => 'textarea'
        ),
        'og_type' => array(
            'name' => _w('Social sharing Type (og:type)'),
            'type' => 'input',
            'description' => _w('E.g. <b>website</b>.').' '._w('For detailed information on Open Graph parameters and examples please refer to <a href="http://ogp.me" target="_blank">ogp.me</a>')
        ),
        _w('Products'),
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
        ),
        'drop_out_of_stock' => array(
            'name' => _w('Force drop out-of-stock products to the bottom of all lists'),
            'description' => _w('When enabled, out-of-stock products will be automatically dropped to the bottom of every product list on this storefront, e.g. in product search results, category product filtering, and more.'),
            'type' => 'checkbox',
        ),
        _w('Checkout'),
        'payment_id' => array(
            'name' => _w('Payment options'),
            'type' => 'radio_checkbox',
            'items' => array(
                0 => array(
                    'name' => _w('All available payment options'),
                    'description' => '',
                ),
                array (
                    'name' => _w('Selected only'),
                    'description' => '',
                    'items' => $payment_items
                )
            )
        ),
        'shipping_id' => array(
            'name' => _w('Shipping options'),
            'type' => 'radio_checkbox',
            'items' => array(
                0 => array(
                    'name' => _w('All available shipping options'),
                    'description' => '',
                ),
                array (
                    'name' => _w('Selected only'),
                    'description' => '',
                    'items' => $shipping_items
                )
            )
        ),
        'ssl' => array(
            'name' => _w('Use HTTPS for checkout and personal accounts'),
            'description' => _w('Automatically redirect to secure https:// mode for checkout (/checkout/) and personal account (/my/) pages of your online storefront. Make sure you have valid SSL certificate installed for this domain name before enabling this option.'),
            'type' => 'checkbox'
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
            '$product.name' => _w('Product name'),
            '$product.summary' => _w('Product summary (brief description)'),
            '$product.description' => _w('Product description'),
            '$product.rating' => _w('Product average rating (float, 0 to 5)'),
            '$product.skus' => _w('Array of product SKUs'),
            '$product.images' => _w('Array of product images'),
            '$product.categories' => _w('Array of product categories'),
            '$product.tags' => _w('Array of product tags'),
            '$product.pages' => _w('Array of product static info pages'),
            '$product.features' => _w('Array of product features and values'),

            '$reviews' => _w('Array of product reviews'),
            '$services' => _w('Array of services available for this product'),

/*
            '$category' => _w('Conditional! Available only if current context of photo is album. Below are describe keys of this param'),
            '$category.id' => '',
            '$category.name' => '',
            '$category.parent_id' => '',
            '$category.description' => '',
*/

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
            '$wa->shop->categories(<em>$id = 0, $depth = null, $tree = false, $params = false, $route = null</em>)' => _w('Returns array of subcategories of the specified category. Omit parent category for the entire array of categories'),
            '$wa->shop->category(<em>$category_id</em>)' => _w('Returns category object by <em>$category_id</em>'),
            '<em>$category</em>.params()' => _w('Array of custom category parameters'),
            '$wa->shop->compare()' => _w('Returns array of products currently added into a comparison list'),
            '$wa->shop->crossSelling(<em>$product_id</em>, <em>$limit = 5</em>, <em>$available_only = false</em>)' => _w('Returns array of cross-sell products.<em>$product_id</em> can be either a number (ID of the specified base product) or an array of products IDs').'. '._w('Setting <em>$available_only = true</em> will automatically exclude all out-of-stock products from the return'),
            '$wa->shop->currency()' => _w('Returns current currency object'),
            '$wa->shop->product(<em>$product_id</em>)' => _w('Returns product object by <em>$product_id</em>').'<br><br> '.
                '$product-><strong>productUrl()</strong>: '._w('Returns valid product page URL').'<br>'.
                '$product-><strong>upSelling</strong>(<em>$limit = 5</em>, <em>$available_only = false</em>):'._w('Returns array of upsell products for the specified product').'. '._w('Setting <em>$available_only = true</em> will automatically exclude all out-of-stock products from the return').'<br>'.
                '$product-><strong>crossSelling</strong>(<em>$limit = 5</em>, <em>$available_only = false</em>):'._w('Returns array of upsell products for the specified product').'. '._w('Setting <em>$available_only = true</em> will automatically exclude all out-of-stock products from the return').'<br><br>'.
                '$product.<strong>id</strong>: '._w('Product id. Other elements of <em>$product</em> available in this template are listed below').'<br>'.
                '$product.<strong>name</strong>: '._w('Product name').'<br>'.
                '$product.<strong>description</strong>: '._w('Product summary (brief description)').'<br>'.
                '$product.<strong>rating</strong>: '._w('Product average rating (float, 0 to 5)').'<br>'.
                '$product.<strong>skus</strong>: '._w('Array of product SKUs').'<br>'.
                '$product.<strong>images</strong>: '._w('Array of product images').'<br>'.
                '$product.<strong>categories</strong>: '._w('Array of product categories').'<br>'.
                '$product.<strong>tags</strong>: '._w('Array of product tags').'<br>'.
                '$product.<strong>pages</strong>: '._w('Array of product static info pages').'<br>'.
                '$product.<strong>features</strong>: '._w('Array of product features and values').'<br>',
                '$product.<strong>reviews</strong>: '._w('Array of product reviews').'<br>',
            
            '$wa->shop->productImgHtml($product, $size, $attributes = array())' => _w('Displays specified $product object’s default image'),
            '$wa->shop->productImgUrl($product, $size)' => _w('Returns specified $product default image URL'),
            '$wa->shop->products(<em>search_conditions</em>[,<em>offset</em>[, <em>limit</em>[, <em>options</em>]]])' => _w('Returns array of products by search criteria, e.g. <em>"tag/new"</em>, <em>"category/12"</em>, <em>"id/1,5,7"</em>, <em>"set/1"</em>, or <em>"*"</em> for all products list.').' '._w('Optional <em>options</em> parameter indicates additional product options, e.g. <em>["params" => 1]</em> to include product custom parameter values into the output.'),
            '$wa->shop->productsCount(<em>search_conditions</em>)' => _w('Returns number of products matching specified search conditions, e.g. <em>"tag/new"</em>, <em>"category/12"</em>, <em>"id/1,5,7"</em>, <em>"set/1"</em>, or <em>"*"</em> for all products list.'),
            '$wa->shop->productSet(<em>set_id</em>)' => _w('Returns array of products from the specified set.').' '._w('Optional <em>options</em> parameter indicates additional product options, e.g. <em>["params" => 1]</em> to include product custom parameter values into the output.'),
            '$wa->shop->ratingHtml(<em>$rating, $size = 10, $show_when_zero = false</em>)' => _w('Displays 1—5 stars rating. $size indicates icon size and can be either 10 or 16'),
            '$wa->shop->features(<em>product_ids</em>)' => _w('Returns array of feature values for the specified list of  products'),
            '$wa->shop->reviews([<em>$limit = 10</em>])' => _w('Returns array of latest product reviews'),
            '$wa->shop->stocks()' => _w('Returns array of stocks'),
            '$wa->shop->settings("<em>option_id</em>")' => _w('Returns store’s general setting option by <em>option_id</em>, e.g. "name", "email", "country"'),
            '$wa->shop->themePath("<em>theme_id</em>")' => _ws('Returns path to theme folder by <em>theme_id</em>'),
        ),
    ),
    'blocks' => array(

    ),
);
