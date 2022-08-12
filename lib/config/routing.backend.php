<?php

return array(

    //
    // Marketing
    //

    # Promos
    'marketing/?'                          => 'marketingPromos',
    'marketing/promo/<promo_id>/?'         => 'marketingPromo',

    # Coupons
    'marketing/coupons/?'                  => 'marketingCoupons',
    'marketing/coupons/<coupon_id>/?'      => 'marketingCoupons',

    # Discounts
    'marketing/discounts/coupons/?'        => 'marketingDiscountsCoupons',
    'marketing/discounts/category/?'       => 'marketingDiscountsCategory',
    'marketing/discounts/order_total/?'    => 'marketingDiscountsOrderTotal',
    'marketing/discounts/customer_total/?' => 'marketingDiscountsCustomerTotal',
    'marketing/discounts/<custom_type>/'   => 'marketingDiscountsCustomType',
    'marketing/discounts/?'                => 'marketingDiscountsCoupons',

    # Abtesting
    'marketing/abtesting/<id>/?'           => 'marketingAbtesting',
    'marketing/abtesting/?'                => 'marketingAbtesting',

    # Followups
    'marketing/followups/<id>/?'           => 'marketingFollowups',
    'marketing/followups/?'                => 'marketingFollowups',

    # Affiliate
    'marketing/affiliate/?'                => 'marketingAffiliate',

    # Recommendations
    'marketing/recommendations/?'          => 'marketingRecommendations',

    # Costs
    'marketing/costs/?'                    => 'marketingCosts',

    # Plugins
    'marketing/plugins/?'                  => 'marketingPlugins',

    //
    // Products (wa2)
    //

    'products/?'                               => 'prod/list',
    'products/categories/?'                    => 'prod/categories',
    'products/sets/?'                          => 'prod/sets',
    'products/tags/?'                          => 'prod/tags',
    'products/reviews/?'                       => 'prod/reviewsList',
    'products/stocks/?'                        => 'prod/stocks',
    'products/<id:\d+|new>/services/?'         => 'prod/services',
    'products/<id:\d+|new>/related/?'          => 'prod/related',
    'products/<id:\d+|new>/reviews/?'          => 'prod/reviews',
    'products/<id:\d+|new>/seo/?'              => 'prod/seo',
    'products/<id:\d+|new>/media/?'            => 'prod/media',
    'products/<id:\d+|new>/general/?'          => 'prod/general',
    'products/<id:\d+|new>/sku/?'              => 'prod/sku',
    'products/<id:\d+|new>/pages/?'            => 'prod/pages',
    'products/<id:\d+|new>/prices/?'           => 'prod/prices',
    'products/<id:\d+|new>/?'                  => 'prod/',

    // everything else uses old routing via ?module=&action=
    '' => 'backend',
);