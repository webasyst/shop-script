<?php

$currencies = wa('shop')->getConfig()->getCurrencies();
foreach ($currencies as &$c) {
    $c = $c['title'];
}
unset($c);

$type_model = new shopTypeModel();
$types = $type_model->select('id,name')->fetchAll('id', true);

$payment_items = $shipping_items = array();
foreach (shopHelper::getPaymentMethods() as $p) {
    $payment_items[$p['id']] = $p['name'];
}
foreach (shopHelper::getShippingMethods() as $s) {
    $shipping_items[$s['id']] = $s['name'];
}

$stock_model = new shopStockModel();
$public_stocks = $stocks = array();
foreach (shopHelper::getStocks() as $stock_id => $s) {
    $stocks[$stock_id] = $s['name'];
    if ($s['public']) {
        $public_stocks[$stock_id] = $s['name'];
    }
}
if (count($stocks) === 0) {
    $stocks_form = array(
        'name'        => _w('Default stock'),
        'description' => _w('There are no stocks available for selection. Set up stocks in section “Settings → Stocks”.'),
        'type'        => 'help',
    );
} else {
    $stocks_form = array(
        'name'        => _w('Default stock'),
        'description' => _w('Select primary stock to which this storefront is associated with. When you process orders from placed via this storefront, selected stock will be automatically offered for product stock update.'),
        'type'        => 'select',
        'items'       => $stocks
    );
}


$view = wa()->getView();
$template = wa()->getAppPath('templates/includes/checkoutVersionRouteMoveSetting.html', 'shop');
$checkout_version_move_setting = $view->fetch($template);

// $route is var outside current file, that is include file
$new_route_rule = empty($route);

$checkout_version_params = array(
    'name'  => _w('Checkout mode'),
    'type'  => 'radio_select',
    'items' => array(
        2 => array(
            'name'        => sprintf('<span class="checkout-2-background">%s</span>', _w('In-cart checkout')),
            'description' => '<br>'.sprintf(_w('If your design theme does not support “in-cart checkout”, standard checkout design of “Default” theme can be used. <a href="%s" target="_blank">Set up</a> <i class="icon16 new-window"></i>in-cart checkout.'), wa()->getAppUrl('shop/?action=settings#/checkout')) . '<br><strong>'. _w('Read <a href="https://www.shop-script.com/help/29297/in-cart-checkout/" target="_blank">user manual</a> before enabling this checkout option.') . '</strong><br><br>',
        ),
        1 => array(
            'name'        => _w('Multi-step checkout'),
            'description' => '<br>'.sprintf(_w('<a href="%s" target="_blank">Set up</a> <i class="icon16 new-window"></i>multi-step checkout'), wa()->getAppUrl('shop/?action=settings#/checkout&r=1')) . $checkout_version_move_setting,
        ),
    ),
    'original_name' => true,
);

// for new route rule choose version 2 as default,
if ($new_route_rule) {
    $checkout_version_params['default'] = 2;
}

return array(
    'params' => array(
        _w('Homepage'),
        'title'             => array(
            'name' => _w('Homepage title <title>'),
            'type' => 'input',
        ),
        'meta_keywords'     => array(
            'name' => _w('Homepage META Keywords'),
            'type' => 'input'
        ),
        'meta_description'  => array(
            'name' => _w('Homepage META Description'),
            'type' => 'textarea'
        ),
        'og_title'          => array(
            'name'        => _w('Social sharing Title (og:title)'),
            'type'        => 'input',
            'description' => _w('For detailed information on Open Graph parameters and examples please refer to <a href="http://ogp.me" target="_blank">ogp.me</a>')
        ),
        'og_image'          => array(
            'name' => _w('Social sharing Image URL (og:image)'),
            'type' => 'input'
        ),
        'og_video'          => array(
            'name' => _w('Social sharing Video URL (og:video)'),
            'type' => 'input'
        ),
        'og_description'    => array(
            'name' => _w('Social sharing Description (og:description)'),
            'type' => 'textarea'
        ),
        'og_type'           => array(
            'name'        => _w('Social sharing Type (og:type)'),
            'type'        => 'input',
            'description' => _w('E.g. <b>website</b>.').' '._w('For detailed information on Open Graph parameters and examples please refer to <a href="http://ogp.me" target="_blank">ogp.me</a>')
        ),
        'og_url'            => array(
            'name'        => _w('Social sharing URL (og:url)'),
            'type'        => 'input',
            'description' => _w('If at least one og: value above is not empty, then you may keep this field empty for og:url meta tag to contain this storefront‘s URL by default. Or type a custom og:url value manually, if necessary.'),
        ),
        _w('Products'),
        'url_type'          => array(
            'name'  => _w('URLs'),
            'type'  => 'radio_select',
            'items' => array(
                2 => array(
                    'name'        => _w('Natural'),
                    'description' => _w('<br>Product URLs: /<strong>category-name/subcategory-name/product-name/</strong><br>Category URLs: /<strong>category-name/subcategory-name/</strong>'),
                ),
                0 => array(
                    'name'        => _w('Mixed'),
                    'description' => _w('<br>Product URLs: /<strong>product-name/</strong><br>Category URLs: /category/<strong>category-name/subcategory-name/subcategory-name/</strong>'),
                ),
                1 => array(
                    'name'        => _w('Plain'),
                    'description' => _w('<br>Product URLs: /product/<strong>product-name/</strong><br>Category URLs: /category/<strong>category-name/</strong>'),
                ),

            )
        ),
        'products_per_page' => array(
            'name'        => _w('Number of products per page'),
            'type'        => 'input',
            'description' => _w('If this value is empty, then the default value from the Shop-Script configuration is used.'),
        ),
        'type_id'           => array(
            'name'  => _w('Published products'),
            'type'  => 'radio_checkbox',
            'items' => array(
                0 => array(
                    'name'        => _w('All product types'),
                    'description' => '',
                ),
                array(
                    'name'        => _w('Selected only'),
                    'description' => '',
                    'items'       => $types
                )
            )
        ),
        'currency'          => array(
            'name'  => _w('Default currency'),
            'type'  => 'select',
            'items' => $currencies
        ),
        'stock_id'          => $stocks_form,
        'public_stocks'     => array(
            'name'  => _w('Visible stocks'),
            'type'  => 'radio_checkbox',
            'items' => array(
                0 => array(
                    'name'        => _w('All public stocks'),
                    'description' => '',
                ),
                array(
                    'name'        => _w('Selected only'),
                    'description' => '',
                    'items'       => $public_stocks,
                )
            )
        ),
        'drop_out_of_stock' => array(
            'name'  => _w('Out-of-stock products'),
            'type'  => 'radio_select',
            'items' => array(

                1 => array(
                    'name'        => _w('Force drop out-of-stock products to the bottom of all lists'),
                    'description' => _w('When enabled, out-of-stock products will be automatically dropped to the bottom of every product list on this storefront, e.g. in product search results, category product filtering, and more. Product quantities in all stocks are taken into account.'),
                ),
                2 => array(
                    'name'        => _w('Hide out-of-stock products'),
                    'description' => _w('Out-of-stock products will remain published, but will be automatically hidden from all product lists on this storefront, e.g. product search results, category product filtering, and others. Product quantities in all stocks are taken into account.')
                ),
                0 => array(
                    'name'        => _w('Display as is'),
                    'description' => _w('All product lists will contain both in-stock and out-of-stock products.'),
                )
            )
        ),
        _w('Checkout'),
        'payment_id'        => array(
            'name'  => _w('Payment options'),
            'type'  => 'radio_checkbox',
            'items' => array(
                0 => array(
                    'name'        => _w('All available payment options'),
                    'description' => '',
                ),
                array(
                    'name'        => _w('Selected only'),
                    'description' => '',
                    'items'       => $payment_items
                )
            )
        ),
        'shipping_id'       => array(
            'name'  => _w('Shipping options'),
            'type'  => 'radio_checkbox',
            'items' => array(
                0 => array(
                    'name'        => _w('All available shipping options'),
                    'description' => '',
                ),
                array(
                    'name'        => _w('Selected only'),
                    'description' => '',
                    'items'       => $shipping_items
                )
            )
        ),
        'ssl'               => array(
            'name'        => _w('Use HTTPS for checkout and personal accounts'),
            'description' => _w('Automatically redirect to secure https:// mode for checkout (/checkout/) and personal account (/my/) pages of your online storefront. Make sure you have valid SSL certificate installed for this domain name before enabling this option.'),
            'type'        => 'checkbox',
        ),
        'checkout_storefront_id' => array(
            'type' => 'hidden',
        ),

        'checkout_version' => $checkout_version_params,
    ),

    'vars' => array(
        'category.html'    => array(
            '$category.id'          => '',
            '$category.name'        => '',
            '$category.parent_id'   => '',
            '$category.description' => '',
        ),
        'index.html'       => array(
            '$content' => _w('Core content loaded according to the requested resource: product, category, search results, static page, etc.'),
        ),
        'product.html'     => array(

            '$product.id'          => _w('Product ID. Other elements of <em>$product</em> object available in this template are listed below.'),
            '$product.name'        => _w('Product name'),
            '$product.summary'     => _w('Product summary (brief description)'),
            '$product.description' => _w('Product description'),
            '$product.rating'      => _w('Product average rating (float, 0 to 5)'),
            '$product.skus'        => _w('Array of product SKUs'),
            '$product.images'      => _w('Array of product images'),
            '$product.categories'  => _w('Array of product categories'),
            '$product.tags'        => _w('Array of product tags'),
            '$product.pages'       => _w('Array of product subpages'),
            '$product.features'    => _w('Array of product features and values'),

            '$reviews'  => _w('Array of product reviews'),
            '$services' => _w('Array of services available for this product'),

            /*
                        '$category' => _w('Conditional! Available only if current context of photo is album. Below are describe keys of this param'),
                        '$category.id' => '',
                        '$category.name' => '',
                        '$category.parent_id' => '',
                        '$category.description' => '',
            */

        ),
        'search.html'      => array(
            '$title' => ''
        ),
        'list-table.html'  => array(
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
        'order.html'       => array(
            '$wa->shop->checkout()->cart(<em>$opts</em>)'            => _w('Returns HTML of in-cart checkout cart block'),
            '$wa->shop->checkout()->cartVars(<em>$clear_cache</em>)' => _w('Returns variables that $wa->shop->checkout()->cart() assigns to its template'),
            '$wa->shop->checkout()->form(<em>$opts</em>)'            => _w('Returns HTML of in-cart checkout form'),
            '$wa->shop->checkout()->formVars(<em>$clear_cache</em>)' => _w('Returns variables that $wa->shop->checkout()->form() assigns to its template'),
        ),
        'my.order.html'    => array(
            '$order'                                                   => _w('An array containing information about the order'),
            '$order.id'                                                => _w('Order ID'),
            '$order.currency'                                          => _w('Order currency (ISO 3 code)'),
            '$order.items'                                             => _w('An array containing information about ordered items'),
            '$order.items[].price'                                     => _w('Item price'),
            '$order.items[].quantity'                                  => _w('Ordered item quantity'),
            '$order.items[].download_link'                             => _w('Item download link (SKU attachment)'),
            '$order.discount'                                          => _w('Order discount amount (in order currency)'),
            '$order.tax'                                               => _w('Order tax amount (in order currency)'),
            '$order.shipping'                                          => _w('Order shipping cost (amount in order currency)'),
            '$order.total'                                             => _w('Order total (amount in order currency)'),
            '$order.comment'                                           => _w('Customer’s comment to the order'),
            '$order.state'                                             => _w('Current order status'),
            /** ORDER_URL **/
            '$order_url'                                               => _w('An URL to access order page in customer personal account in storefront'),
            /** ORDER.PARAMS **/
            '$order.params'                                            => _w('An array of other (custom) order parameter values'),
            '$order.params.shipping_name'                              => _w('Order selected shipping option name'),
            '$order.params.shipping_description'                       => _w('Order selected shipping option description'),
            '$order.params.payment_name'                               => _w('Order selected payment option name'),
            '$order.params.payment_description'                        => _w('Order selected payment option description'),
            '$order.params.auth_pin'                                   => _w('A 4-digit PIN to access order info page (for customers without permanent account only)'),
            '$order.params.storefront'                                 => _w('Domain and path to Shop-Script storefront where the order was placed'),
            '$order.params.storefront_decoded'                         => _w('URL of Shop-Script storefront where the order was placed, decoded from Punycode if applicable'),
            '$order.params.ip'                                         => _w('Customer’s IP address for the moment of order creation'),
            '$order.params.user_agent'                                 => _w('Customer’s User-Agent value at the moment of order creation'),
            '$order.params.<br>shipping_est_delivery'                  => _w('A string indicating approximate timeframe of the order delivery (if defined)'),
            '$order.params.tracking_number'                            => _w('Order shipment tracking number (if defined)'),
            '$order.params. …'                                         => _w('The list of order params may vary depending on your workflow and plugin setup'),
            /** CUSTOMER **/
            '$customer'                                                => _w('Customer-related data array'),
            '$customer.birth_day'                                      => _w('Day number of customer’s date of birth'),
            '$customer.birth_month'                                    => _w('Month number of customer’s date of birth'),
            '$customer.birth_year'                                     => _w('Year number of customer’s date of birth'),
            '$customer.name|escape'                                    => _w('Customer’s name'),
            '$customer.company|escape'                                 => _w('Customer’s company name'),
            '$customer.jobtitle|escape'                                => _w('Customer’s job title'),
            '$customer->get(\'phone\', \'default|top\')'               => _w('Customer’s phone number'),
            '$customer->get(\'email\', \'default\')|escape'            => _w('Customer’s email address'),
            '$customer->get(\'address:street\', \'default\')|escape '  => _w('Customer’s street address'),
            '$customer->get(\'address:city\', \'default\')|escape '    => _w('Customer’s city name'),
            '$customer->get(\'address:zip\', \'default\')|escape '     => _w('Customer’s ZIP code'),
            '$customer->get(\'address:region\', \'value|default\')|escape '  => _w('Customer’s region name'),
            '$customer->get(\'address:country\', \'value|default\')|escape ' => _w('Customer’s country name'),
            '$customer.affiliate_bonus'                                => _w('Amount of customer‘s bonuses accumulated with the “Loyalty program” enabled in store settings'),
            '$customer.total_spent'                                    => _w('Total amount of money spent by a customer calculated in default currency. To add formatted amount expressed in the order currency, use <code>{shop_currency($customer.total_spent, null, $order.currency)}</code>.'),
            '$customer.number_of_orders'                               => _w('Number of orders placed by a customer.'),
            '$customer.last_order_id'                                  => _w('ID of last order placed by a customer. To add formatted order ID, use <code>{shopHelper::encodeOrderId($customer.last_order_id)}</code>.'),
            '$customer. …'                                             => _w('The list of available contact fields is defined in your store backend: Settings &rarr; Checkout &rarr; Contact info checkout step'),
            /** ADDRESSES **/
            '$shipping_address'                                        => _w('Shipping address as string'),
            '$billing_address'                                         => _w('Billing address as string'),
            /** COURIER **/
            '$courier'                                                 => _w('Array of courier data'),
            '$courier.name'                                            => _w('Courier name'),
            '$courier.note'                                            => _w('Note'),
            '$courier.contact'                                         => _w('Array of data of a courier contact stored in Contacts app, if courier is linked to an existing contact'),
            '$courier.contact->get(\'email\', \'default\')'            => _w('Courier contact’s default email address'),
            '$courier.contact->get(\'phone\', \'default\')'            => _w("Courier contact’s default phone number"),
            /** OTHER **/
            '$signup_url'                                              => _w('Signup page URL. Will not be provided if either the order was placed by a registered customer, or <a href="?action=settings#/checkout/">Guest checkout setting</a> is not enabled.'),

        ),
        '$wa'              => array(
            '$wa->shop->checkout()->url(<em>$absolute</em>)'                                                          => _w('Returns checkout page URL'),
            '$wa->shop->checkout()->cartUrl(<em>$absolute</em>)'                                                      => _w('Returns shopping cart page URL'),
            '$wa->shop->schedule()'                                                                                   => _w('Returns working schedule data array for current storefront'),
            '$wa->shop->badgeHtml(<em>$product.code</em>)'                                                            => _w('Displays badge of the specified product (<em>$product</em> object)'),
            '$wa->shop->cart()'                                                                                       => _w('Returns current cart object'),
            '$wa->shop->categories(<em>$id, $depth, $tree, $params, $route</em>)'                                     => _w('Returns array of visible subcategories of specified parent category.<br><strong>$id</strong> (default: <em>0</em>): ID of parent category whose subcategories must be returned. By default, categories starting from top level are returned.<br><strong>$depth</strong> (default: <em>null</em>): depth of subcategory tree. By default, entire category tree is returned.<br><strong>$tree</strong> (default: <em>false</em>): flag requiring to return categories as a tree (<em>true</em>) or a flat array (<em>false</em>).<br><strong>$params</strong> (default: <em>false</em>): flag requiring to return categories with their extra parameters. By default, categories are returned without extra parameters.<br><strong>$route</strong> (default: <em>null</em>): array of route parameters of the storefront for which visible categories must be returned. By default, returned categories are not necessarily limited to certain storefronts.'),
            '$wa->shop->category(<em>$category_id</em>)'                                                              => _w('Returns category object by <em>$category_id</em>'),
            '$wa->shop->categoryUrl(<em>$category</em>)'                                                              => _w('Returns category URL for specified <em>$category</em> array'),
            '<em>$category</em>.params()'                                                                             => _w('Array of custom category parameters'),
            '$wa->shop->compare()'                                                                                    => _w('Returns array of products currently added into a comparison list'),
            '$wa->shop->crossSelling(<em>$product_id</em>, <em>$limit</em>, <em>$available_only</em>)'                => _w('Returns array of specified product’s cross-selling items.<br><strong>$id</strong> of product whose cross-selling items must be returned.<br><strong>$limit</strong> (default: <em>5</em>) limits the number of returned items.<br><strong>$available_only</strong> (default: <em>false</em>) excludes out-of-stock products from the result. By default, out-of-stock products are not skipped.'),
            '$wa->shop->currencies()'                                                                                 => _w('Returns array of available currencies.'),
            '$wa->shop->currency()'                                                                                   => _w('Returns current currency’s properties object.'),
            '$wa->shop->imgHtml(<em>$image</em>, <em>$size</em>, <em>$attributes = array()</em>)'                     => _w('Returns HTML for a product image thumbnail with specified size and attributes. <em>$image</em> array must contain <em>\'id\'</em>, <em>\'product_id\'</em>, and <em>\'ext\'</em> elements.'),
            '$wa->shop->imgUrl(<em>$image</em>, <em>$size</em>, <em>$absolute = false</em>)'                          => _w('Returns relative or absolute URL of a product image thumbnail with specified size. <em>$image</em> array must contain <em>\'id\'</em>, <em>\'product_id\'</em>, and <em>\'ext\'</em> elements.'),
            '$product'                                                                                                => array(
                '$product = $wa->shop->product(<em>$id</em>)'                       => _w('To get product-related information, first create a product object by passing its numeric ID; e.g., <em>\'product_id\'</em> property of an item in <em>$order.items</em> array.'),
                '$product->getProductUrl(<em>$storefront_url</em>)'                 => _w('Returns product URL for specified storefront address. E.g., use <em>$order.params.storefront</em> containing the storefront on which a product was purchased by a customer.'),
                '$product->upSelling(<em>$limit</em>, <em>$available_only</em>)'    => _w('Returns array of product’s upselling items.<br><strong>$limit</strong> (default: <em>5</em>) limits the number of returned items.<br><strong>$available_only</strong> (default: <em>false</em>) excludes out-of-stock products from the result. By default, out-of-stock products are not skipped.'),
                '$product->crossSelling(<em>$limit</em>, <em>$available_only</em>)' => _w('Returns array of product’s cross-selling items.<br><strong>$limit</strong> (default: <em>5</em>) limits the number of returned items.<br><strong>$available_only</strong> (default: <em>false</em>) excludes out-of-stock products from the result. By default, out-of-stock products are not skipped.'),
                '$product.id'                                                       => _w('Product ID. Other elements of <em>$product</em> object available in this template are listed below.'),
                '$product.name'                                                     => _w('Product name'),
                '$product.description'                                              => _w('Product summary (brief description)'),
                '$product.rating'                                                   => _w('Product average rating (float, 0 to 5)'),
                '$product.skus'                                                     => _w('Array of product’s SKUs'),
                '$product.images'                                                   => _w('Array of product’s images'),
                '$product.categories'                                               => _w('Array of product’s categories'),
                '$product.tags'                                                     => _w('Array of product’s tags'),
                '$product.pages'                                                    => _w('Array of product’s subpages'),
                '$product.features'                                                 => _w('Array of product’s features and their values'),
                '$product.reviews'                                                  => _w('Array of product’s reviews').'<br>',
            ),
            '$wa->shop->productImgHtml($product, $size, $attributes = array())'                                       => _w('Displays specified $product object’s default image'),
            '$wa->shop->productImgUrl($product, $size)'                                                               => _w('Returns specified $product default image URL'),
            '$wa->shop->products(<em>search_conditions</em>[,<em>offset</em>[, <em>limit</em>[, <em>options</em>]]])' => _w('Returns array of products by search criteria, e.g. <em>"tag/new"</em>, <em>"category/12"</em>, <em>"id/1,5,7"</em>, <em>"set/1"</em>, or <em>"*"</em> for all products list.').' '._w('Optional <em>options</em> parameter indicates additional product options, e.g. <em>["params" => 1]</em> to include product custom parameter values into the output.'),
            '$wa->shop->productsCount(<em>search_conditions</em>)'                                                    => _w('Returns number of products matching specified search conditions, e.g. <em>"tag/new"</em>, <em>"category/12"</em>, <em>"id/1,5,7"</em>, <em>"set/1"</em>, or <em>"*"</em> for all products list.'),
            '$wa->shop->productSet(<em>set_id</em>)'                                                                  => _w('Returns array of products from the specified set.').' '._w('Optional <em>options</em> parameter indicates additional product options, e.g. <em>["params" => 1]</em> to include product custom parameter values into the output.'),
            '$wa->shop->ratingHtml(<em>$rating, $size = 10, $show_when_zero = false</em>)'                            => _w('Displays 1—5 stars rating. $size indicates icon size and can be either 10 or 16'),
            '$wa->shop->features(<em>product_ids</em>)'                                                               => _w('Returns array of feature values for the specified list of  products'),
            '$wa->shop->reviews([<em>$limit = 10</em>])'                                                              => _w('Returns array of latest product reviews'),
            '$wa->shop->tags([<em>$limit = 50</em>])'                                                                 => _w('Returns array of tags, by default limited to 50 items'),
            '$wa->shop->stocks()'                                                                                     => _w('Returns array of stocks'),
            '$wa->shop->settings("<em>option_id</em>")'                                                               => _w('Returns store’s general setting option by <em>option_id</em>, e.g. "name", "email", "country"'),
            '$wa->shop->orderId("<em>id</em>")'                                                                       => _w('Returns formatted order ID by provided numerical ID'),
            '$wa->shop->payment()'                                                                                    => _w('Returns array of enabled payment methods'),
            '$wa->shop->shipping()'                                                                                   => _w('Returns array of enabled shipping methods'),
            '$wa->shop->themePath("<em>theme_id</em>")'                                                               => _ws('Returns path to theme folder by <em>theme_id</em>'),
        ),

        'notifications' => array(
            '$order'                                                      => _w('An array containing information about the order'),
            '$order.id'                                                   => _w('Order ID'),
            '$order.currency'                                             => _w('Order currency (ISO 3 code)'),
            '$order.items'                                                => _w('An array containing information about ordered items'),
            '$order.items[].price'                                        => _w('Item price'),
            '$order.items[].quantity'                                     => _w('Ordered item quantity'),
            '$order.items[].download_link'                                => _w('Item download link (SKU attachment)'),
            '$wa->shop->productImgUrl($item, $size)'                      => _w('Relative URL of ordered product‘s main image with specified size.').'<br>'.
                _w('<code>$item</code> must be an item of <code>$order.items</code> array.').'<br>'.
                _w('<code>$size</code> must contain one of size values described in the <a href="https://www.shop-script.com/help/43/image-thumbnails-in-shop-script-5-storefront/" target="_blank">documentation</a>; e.g., "200" or "200x0", etc. Default image size, if not specified, is "750x0".').'<br>'.
                _w('To obtain an absolute image URL, use <code>{$wa-&gt;domainUrl()}</code>.').'<br>'.
                _w('Example:').'<br>'.
                '<code>{$base_url = $wa-&gt;domainUrl()}<br>{foreach $order.items as $item}<br>&nbsp;&nbsp;&nbsp;&nbsp;{$img_url = $wa-&gt;shop-&gt;productImgUrl($item, "200x0")}<br>&nbsp;&nbsp;&nbsp;&nbsp;{if $img_url}<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&lt;img src="{$base_url}{$img_url}"&gt;<br>&nbsp;&nbsp;&nbsp;&nbsp;{/if}<br>{/foreach}</code>',
            '$order.discount'                                             => _w('Order discount amount (in order currency)'),
            '$order.tax'                                                  => _w('Order tax amount (in order currency)'),
            '$order.shipping'                                             => _w('Order shipping cost (amount in order currency)'),
            '$order.total'                                                => _w('Order total (amount in order currency)'),
            '$order.comment'                                              => _w('Customer’s comment to the order'),
            /** STATUS **/
            '$status'                                                     => _w('Current order status name'),
            /** ORDER_URL **/
            '$order_url'                                                  => _w('An URL to access order page in customer personal account in storefront'),
            /** ORDER.PARAMS **/
            '$order.params'                                               => _w('An array of other (custom) order parameter values'),
            '$order.params.shipping_name'                                 => _w('Order selected shipping option name'),
            '$order.params.shipping_description'                          => _w('Order selected shipping option description'),
            '$order.params.payment_name'                                  => _w('Order selected payment option name'),
            '$order.params.payment_description'                           => _w('Order selected payment option description'),
            '$order.params.auth_pin'                                      => _w('A 4-digit PIN to access order info page (for customers without permanent account only)'),
            '$order.params.storefront'                                    => _w('Domain and path to Shop-Script storefront where the order was placed'),
            '$order.params.storefront_decoded'                            => _w('URL of Shop-Script storefront where the order was placed, decoded from Punycode if applicable'),
            '$order.params.ip'                                            => _w('Customer’s IP address for the moment of order creation'),
            '$order.params.user_agent'                                    => _w('Customer’s User-Agent value at the moment of order creation'),
            '$order.params.<br>shipping_est_delivery'                     => _w('A string indicating approximate timeframe of the order delivery (if defined)'),
            '$order.params.tracking_number'                               => _w('Order shipment tracking number (if defined)'),
            '$order.params<br>shipping_start_datetime'                   => _w('Nearest delivery date and time formatted as <em>yyyy-mm-dd hh:mm:ss</em>; e.g., <em>2001-12-31 12:30:59</em>.'),
            '$order.params<br>shipping_end_datetime'                     => _w('Deadline for delivery formatted as <em>yyyy-mm-dd hh:mm:ss</em>; e.g., <em>2001-12-31 13:30:59</em>.'),
            '$order[\'params\']<br>[\'shipping_params_desired_delivery.date\']'     => _w('Desired delivery date selected by customer, formatted as <em>yyyy-mm-dd</em>; e.g., <em>2001-12-31</em>.'),
            '$order[\'params\']<br>[\'shipping_params_desired_delivery.date_str\']' => _w('Desired delivery date selected by customer, formatted as <em>dd.mm.yyyy</em>; e.g., <em>31.12.2001</em>.'),
            '$order[\'params\']<br>[\'shipping_params_desired_delivery.interval\']' => _w('Desired delivery interval selected by customer, formatted as <em>xx:xx-xx:xx</em>; e.g., <em>10:00-12:00</em>.'),
            '$order.params. …'                                         => _w('The list of order params may vary depending on your workflow and plugin setup'),
            /** CUSTOMER **/
            '$customer'                                                => _w('Customer-related data array'),
            '$customer.birth_day'                                      => _w('Day number of customer’s date of birth'),
            '$customer.birth_month'                                    => _w('Month number of customer’s date of birth'),
            '$customer.birth_year'                                     => _w('Year number of customer’s date of birth'),
            '$customer.name|escape'                                    => _w('Customer’s name'),
            '$customer.company|escape'                                 => _w('Customer’s company name'),
            '$customer.jobtitle|escape'                                => _w('Customer’s job title'),
            '$customer->get(\'phone\', \'default|top\')'               => _w('Customer’s phone number'),
            '$customer->get(\'email\', \'default\')|escape'            => _w('Customer’s email address'),
            '$customer->get(\'address:street\', \'default\')|escape '  => _w('Customer’s street address'),
            '$customer->get(\'address:city\', \'default\')|escape '    => _w('Customer’s city name'),
            '$customer->get(\'address:zip\', \'default\')|escape '     => _w('Customer’s ZIP code'),
            '$customer->get(\'address:region\', \'value|default\')|escape '  => _w('Customer’s region name'),
            '$customer->get(\'address:country\', \'value|default\')|escape ' => _w('Customer’s country name'),
            '$customer.affiliate_bonus'                                => _w('Amount of customer‘s bonuses accumulated with the “Loyalty program” enabled in store settings'),
            '$customer.total_spent'                                    => _w('Total amount of money spent by a customer calculated in default currency. To add formatted amount expressed in the order currency, use <code>{shop_currency($customer.total_spent, null, $order.currency)}</code>.'),
            '$customer.number_of_orders'                               => _w('Number of orders placed by a customer.'),
            '$customer.last_order_id'                                  => _w('ID of last order placed by a customer. To add formatted order ID, use <code>{shopHelper::encodeOrderId($customer.last_order_id)}</code>.'),
            '$customer. …'                                             => _w('The list of available contact fields is defined in your store backend: Settings &rarr; Checkout &rarr; Contact info checkout step'),
            /** ADDRESSES **/
            '$shipping_address'                                        => _w('Shipping address as string'),
            '$billing_address'                                         => _w('Billing address as string'),
            /** SHIPPING INTERVAL */
            '$shipping_date|wa_date:humandate'                         => _w('Shipping date, for example: February 16, 2017'),
            '$shipping_date|wa_date:shortdate'                         => _w('Shipping date, for example: February 16'),
            '$shipping_date|wa_date:date'                              => _w('Shipping date, for example: 02/16/2017'),
            '$shipping_time_start'                                     => _w('Start of shipping time interval'),
            '$shipping_time_end'                                       => _w('End of shipping time interval'),
            '$shipping_interval'                                       => _w('Shipping date and time, for example: February 16, 10:00 - 20:30'),
            /** COURIER **/
            '$courier'                                                 => _w('Array of courier data'),
            '$courier.name'                                            => _w('Courier name'),
            '$courier.note'                                            => _w('Note'),
            '$courier.contact'                                         => _w('Array of data of a courier contact stored in Contacts app, if courier is linked to an existing contact'),
            '$courier.contact->get(\'email\', \'default\')'            => _w('Courier contact’s default email address'),
            '$courier.contact->get(\'phone\', \'default\')'            => _w("Courier contact’s default phone number"),
            /** ACTION DATA **/
            '$action_data'                                             => _w('An array containing information about performed order action (action-dependent)'),
            '$action_data.text'                                        => _w('Performed action text comment (action-dependent)'),
            '$action_data.params'                                      => _w('An array of action-dependent parameters, e.g. $action_data.params.tracking_number for Ship’s action tracking number'),
            '$action_data.callback_transaction_data'                   => _w('Array of notification callback data received by a payment plugin from a payment gateway.'),
            /** OTHER **/
            '$signup_url'                                              => _w('Signup page URL. Will not be provided if either the order was placed by a registered customer, or <a href="?action=settings#/checkout/">Guest checkout setting</a> is not enabled.'),
            /** AFILIATE **/
            '$is_affiliate_enabled'                                    => _w('Indicates if <a href="?action=settings#/affiliate/">Loyalty program</a> is enabled in store settings.'),
            '$add_affiliate_bonus'                                     => _w('Amount of affiliate bonus that will be credited to customer account when order is paid.'),
        ),
        'followups'     => array(
            '$order'                                                      => _w('An array containing information about the order'),
            '$order.id'                                                   => _w('Order ID'),
            '$order.currency'                                             => _w('Order currency (ISO 3 code)'),
            '$order.items'                                                => _w('An array containing information about ordered items'),
            '$order.items[].price'                                        => _w('Item price'),
            '$order.items[].quantity'                                     => _w('Ordered item quantity'),
            '$order.items[].download_link'                                => _w('Item download link (SKU attachment)'),
            '$wa->shop->productImgUrl($item, $size)'                      => _w('Relative URL of ordered product‘s main image with specified size.').'<br>'.
                _w('<code>$item</code> must be an item of <code>$order.items</code> array.').'<br>'.
                _w('<code>$size</code> must contain one of size values described in the <a href="https://www.shop-script.com/help/43/image-thumbnails-in-shop-script-5-storefront/" target="_blank">documentation</a>; e.g., "200" or "200x0", etc. Default image size, if not specified, is "750x0".').'<br>'.
                _w('To obtain an absolute image URL, use <code>{$wa-&gt;domainUrl()}</code>.').'<br>'.
                _w('Example:').'<br>'.
                '<code>{$base_url = $wa-&gt;domainUrl()}<br>{foreach $order.items as $item}<br>&nbsp;&nbsp;&nbsp;&nbsp;{$img_url = $wa-&gt;shop-&gt;productImgUrl($item, "200x0")}<br>&nbsp;&nbsp;&nbsp;&nbsp;{if $img_url}<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&lt;img src="{$base_url}{$img_url}"&gt;<br>&nbsp;&nbsp;&nbsp;&nbsp;{/if}<br>{/foreach}</code>',
            '$order.discount'                                             => _w('Order discount amount (in order currency)'),
            '$order.tax'                                                  => _w('Order tax amount (in order currency)'),
            '$order.shipping'                                             => _w('Order shipping cost (amount in order currency)'),
            '$order.total'                                                => _w('Order total (amount in order currency)'),
            '$order.comment'                                              => _w('Customer’s comment to the order'),
            /** STATUS **/
            '$status'                                                     => _w('Current order status name'),
            /** ORDER_URL **/
            '$order_url'                                                  => _w('An URL to access order page in customer personal account in storefront'),
            /** ORDER.PARAMS **/
            '$order.params'                                               => _w('An array of other (custom) order parameter values'),
            '$order.params.shipping_name'                                 => _w('Order selected shipping option name'),
            '$order.params.shipping_description'                          => _w('Order selected shipping option description'),
            '$order.params.payment_name'                                  => _w('Order selected payment option name'),
            '$order.params.payment_description'                           => _w('Order selected payment option description'),
            '$order.params.auth_pin'                                      => _w('A 4-digit PIN to access order info page (for customers without permanent account only)'),
            '$order.params.storefront'                                    => _w('Domain and path to Shop-Script storefront where the order was placed'),
            '$order.params.storefront_decoded'                            => _w('URL of Shop-Script storefront where the order was placed, decoded from Punycode if applicable'),
            '$order.params.ip'                                            => _w('Customer’s IP address for the moment of order creation'),
            '$order.params.user_agent'                                    => _w('Customer’s User-Agent value at the moment of order creation'),
            '$order.params.<br>shipping_est_delivery'                     => _w('A string indicating approximate timeframe of the order delivery (if defined)'),
            '$order.params.tracking_number'                               => _w('Order shipment tracking number (if defined)'),
            '$order.params.<br>shipping_start_datetime'                   => _w('Nearest delivery date and time formatted as <em>yyyy-mm-dd</em>; e.g., <em>2001-12-31</em>.'),
            '$order.params.<br>shipping_end_datetime'                     => _w('Deadline for delivery formatted as <em>yyyy-mm-dd hh:mm:ss</em>; e.g., <em>2001-12-31 13:30:59</em>.'),
            '$order[\'params\']<br>[\'shipping_params_desired_delivery.date\']'     => _w('Desired delivery date selected by customer, formatted as <em>yyyy-mm-dd</em>; e.g., <em>2001-12-31</em>.'),
            '$order[\'params\']<br>[\'shipping_params_desired_delivery.date_str\']' => _w('Desired delivery date selected by customer, formatted as <em>dd.mm.yyyy</em>; e.g., <em>31.12.2001</em>.'),
            '$order[\'params\']<br>[\'shipping_params_desired_delivery.interval\']' => _w('Desired delivery interval selected by customer, formatted as <em>xx:xx-xx:xx</em>; e.g., <em>10:00-12:00</em>.'),
            '$order.params. …'                                            => _w('The list of order params may vary depending on your workflow and plugin setup'),
            /** CUSTOMER **/
            '$customer'                                                   => _w('Customer-related data array'),
            '$customer.birth_day'                                         => _w('Day number of customer’s date of birth'),
            '$customer.birth_month'                                       => _w('Month number of customer’s date of birth'),
            '$customer.birth_year'                                        => _w('Year number of customer’s date of birth'),
            '$customer.name|escape'                                       => _w('Customer’s name'),
            '$customer.company|escape'                                    => _w('Customer’s company name'),
            '$customer.jobtitle|escape'                                   => _w('Customer’s job title'),
            '$customer->get(\'phone\', \'default|top\')'                  => _w('Customer’s phone number'),
            '$customer->get(\'email\', \'default\')|escape'               => _w('Customer’s email address'),
            '$customer->get(\'address:street\', \'default\')|escape '     => _w('Customer’s street address'),
            '$customer->get(\'address:city\', \'default\')|escape '       => _w('Customer’s city name'),
            '$customer->get(\'address:zip\', \'default\')|escape '        => _w('Customer’s ZIP code'),
            '$customer->get(\'address:region\', \'value|default\')|escape '     => _w('Customer’s region name'),
            '$customer->get(\'address:country\', \'value|default\')|escape '    => _w('Customer’s country name'),
            '$customer.affiliate_bonus'                                   => _w('Amount of customer‘s bonuses accumulated with the “Loyalty program” enabled in store settings'),
            '$customer.total_spent'                                       => _w('Total amount of money spent by a customer calculated in default currency. To add formatted amount expressed in the order currency, use <code>{shop_currency($customer.total_spent, null, $order.currency)}</code>.'),
            '$customer.number_of_orders'                                  => _w('Number of orders placed by a customer.'),
            '$customer.last_order_id'                                     => _w('ID of last order placed by a customer. To add formatted order ID, use <code>{shopHelper::encodeOrderId($customer.last_order_id)}</code>.'),
            '$customer. …'                                                => _w('The list of available contact fields is defined in your store backend: Settings &rarr; Checkout &rarr; Contact info checkout step'),
            /** ADDRESSES **/
            '$shipping_address'                                           => _w('Shipping address as string'),
            '$billing_address'                                            => _w('Billing address as string'),
            /** COURIER **/
            '$courier'                                                    => _w('Array of courier data'),
            '$courier.name'                                               => _w('Courier name'),
            '$courier.note'                                               => _w('Note'),
            '$courier.contact'                                            => _w('Array of data of a courier contact stored in Contacts app, if courier is linked to an existing contact'),
            '$courier.contact->get(\'email\', \'default\')'               => _w('Courier contact’s default email address'),
            '$courier.contact->get(\'phone\', \'default\')'               => _w("Courier contact’s default phone number"),
            /** OTHER **/
            '$signup_url'                                                 => _w('Signup page URL. Will not be provided if either the order was placed by a registered customer, or <a href="?action=settings#/checkout/">Guest checkout setting</a> is not enabled.'),
        ),
    ),

    'blocks' => array(),

);


