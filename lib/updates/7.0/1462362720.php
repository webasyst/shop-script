<?php

$_m = new shopPromoRoutesModel();

$_delete = array();
$_insert = array();
foreach (wa()->getRouting()->getByApp('shop') as $_domain => $_domain_routes) {
    $_promo_routes = $_m->getByField('storefront', $_domain, true);
    if ($_promo_routes) {
        $_delete[] = $_domain;
        foreach ($_domain_routes as $_route) {
            $_url = rtrim($_domain . '/' . $_route['url'], '/*') . '/';
            foreach ($_promo_routes as $_promo_route_item) {
                $_promo_id = $_promo_route_item['promo_id'];
                $_insert[] = array(
                    'promo_id' => $_promo_route_item['promo_id'],
                    'storefront' => $_url,
                    'sort' => $_promo_route_item['sort']
                );
            }
        }
    }
}

$_m->exec("DELETE FROM `shop_promo_routes` WHERE storefront != '%all%'");

if ($_insert) {
    $_m->multipleInsert($_insert);
}