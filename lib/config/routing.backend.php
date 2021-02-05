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

    'products/?'                           => 'prod/list',
    'products/<id:\d+>/related/?'          => 'prod/related',
    'products/<id:\d+>/seo/?'              => 'prod/seo',
    'products/<id:\d+>/media/?'            => 'prod/media',
    'products/<id:\d+>/general/?'          => 'prod/general',
    'products/<id:\d+>/sku/?'              => 'prod/sku',
    'products/<id:\d+>/?'                  => 'prod/',

    // everything else uses old routing via ?module=&action=
    '' => 'backend',
);