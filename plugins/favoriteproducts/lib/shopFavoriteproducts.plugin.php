<?php

/**
 * Plugin allows backend users to mark certain products as favorite,
 * and list all favorite products in a separate list.
 */
class shopFavoriteproductsPlugin extends shopPlugin
{
    /** Handler for backend_products event: HTML for order list sidebar. */
    public function backendProducts($param)
    {
        $fm = new shopFavoriteproductsPluginModel();
        $count = $fm->countByField('contact_id', wa()->getUser()->getId());
        return array(
            'sidebar_top_li' => '<li id="s-sb-favoriteproducts"><span class="count">'.$count.'</span><a href="#/products/hash=favorites"><i class="icon16 star"></i>'._wp('Favorites').'</a></li>',
        );
    }

    /* Handler for products_collection event: modify collection  */
    public function productsCollection($params)
    {
        $collection = $params['collection'];

        $hash = $collection->getHash();
        if ($hash[0] !== 'favorites' || !wa()->getUser()->getId()) {
            return null;
        }

        $collection->addJoin(array(
            'table' => 'shop_favoriteproducts',
            'on'    => 'shop_favoriteproducts.product_id = p.id',
        ))->addWhere('shop_favoriteproducts.contact_id='.wa()->getUser()->getId());

        if ($params['auto_title']) {
            $collection->addTitle(_wp('Favorites'));
        }

        return null;
    }

    /** Handler for backend_product event: HTML for single order page. */
    public function backendProduct($product)
    {
        if ($product instanceof shopProduct && !$product->checkRights()) {
            return '';
        }

        $el_id = uniqid('fav');
        $js = <<<EOJS
<script>$(function() { "use strict";
    var a = $('#{$el_id}').click(function() {
        var i = a.children('i').toggleClass('star').toggleClass('star-empty');
        var fav = i.hasClass('star') ? 1 : 0;
        $.post('?plugin=favoriteproducts&action=fav', { fav: fav, id: '{$product['id']}' }, function(r) {
            $('#s-sb-favoriteproducts .count').text(r.data);
        }, 'json');
    });
});</script>
EOJS;

        $fm = new shopFavoriteproductsPluginModel();
        $favorite = !!$fm->getByField(array('contact_id' => wa()->getUser()->getId(), 'product_id' => $product['id']));
        return array(
            'title_suffix' => '<a id="'.$el_id.'" href="javascript:void(0)" title="'._wp('Add to favorites').'"><i class="icon16 star'.($favorite ? '' : '-empty').'"></i></a>'.$js,
        );
    }

    /** Handler for product_delete event: clean up our data when products are removed. */
    public function productDelete($params)
    {
        $fm = new shopFavoriteproductsPluginModel();
        $fm->deleteByField('product_id', $params['ids']); // !!! no index for this field, query may be slow
    }
}

