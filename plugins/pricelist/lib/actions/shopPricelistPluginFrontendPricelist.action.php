<?php
/**
 * Shop price list plugin
 */
class shopPricelistPluginFrontendPricelistAction extends shopFrontendAction
{
    private function getPricelist()
    {
        $m = new shopCategoryModel();
        $categories = $m->getFullTree( 'id, depth, full_url, url, name', true );
        $url = wa()->getRouteUrl( 'shop/frontend/category', array('category_url' => '%CATEGORY_URL%') );
        foreach ( $categories as $category )
        {
            $categories[$category['id']]['frontend_url'] = str_replace( '%CATEGORY_URL%', waRequest::param('url_type') == 1 ? $category['url'] : $category['full_url'], $url );
            $categories[$category['id']]['name'] = htmlspecialchars( $category['name'] );
            $m = new shopProductsCollection( 'category/' . $category['id'] );
            $m->filters( array('status' => 1) );
            $categories[$category['id']]['products'] = $m->getProducts( 'id, name, frontend_url, price, compare_price, currency, count', 0, 1000, true );
        }
        return $categories;
    }
    
    public function execute()
    {
        // use cache for pricelist array
        $app = wa()->getApp();
        $m = new waAppSettingsModel();
        if ( $cache_time = $m->get(array($app, 'pricelist'), 'cache_time') ) 
        {   
            $cache = new waSerializeCache( 'pricelist', $cache_time, $app );
            if ( $cache->isCached() )
            {
                $categories = $cache->get();
            }
            if ( empty($categories) )
            {
                $categories = $this->getPricelist();
                $cache->set($categories);
            }
        }
        else
        {
            $m->set(array('shop', 'pricelist'), 'cache_time', 600);
            $categories = $this->getPricelist();
        }
        // Set categories with products & template
        $this->view->assign( 'pricelist', $categories );
        if ( file_exists( $this->getTheme()->path . '/pricelist.html' ) )
        {
            $this->setThemeTemplate( 'pricelist.html' );
        }
    }
}
