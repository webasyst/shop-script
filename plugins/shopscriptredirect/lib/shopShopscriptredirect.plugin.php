<?php

class shopShopscriptredirectPlugin extends shopPlugin
{

    /**
     * @param waException $params
     */
    public function frontendError($params)
    {
        if ($params->getCode() == 404 && waRequest::param('url_type') != 1) {
            $url = wa()->getRouting()->getCurrentUrl();
            if ($url == 'index.php') {
                if ($id = waRequest::get('productID')) {
                    $product_model = new shopProductModel();
                    $p = $product_model->getById($id);
                    if ($p) {
                        $params = array('product_url' => $p['url']);
                        if ($p['category_id']) {
                            $category_model = new shopCategoryModel();
                            $c = $category_model->getById($p['category_id']);
                            $params['category_url'] = $c['full_url'];
                        }
                        wa()->getResponse()->redirect(wa()->getRouteUrl('shop/frontend/product', $params));
                    }
                } elseif ($id = waRequest::get('categoryID')) {
                    $category_model = new shopCategoryModel();
                    $c = $category_model->getById($id);
                    if ($c) {
                        wa()->getResponse()->redirect(wa()->getRouteUrl('shop/frontend/category', array(
                        'category_url' => waRequest::param('url_type') == 1 ? $c['url'] : $c['full_url'])));
                    }
                }
            } elseif (substr($url, 0, 8) == 'product/') {
                $url = substr($url, 8);
                $url_parts = explode('/', $url);
                $product_model = new shopProductModel();
                if ($product_model->getByField('url', $url_parts[0])) {
                    wa()->getResponse()->redirect(wa()->getRootUrl().wa()->getRouting()->getRootUrl().$url);
                }
            } elseif (substr($url, 0, 9) == 'category/') {
                $url = substr($url, 9);
                $category_model = new shopCategoryModel();
                if ($category_model->getByField('full_url', rtrim($url, '/'))) {
                    wa()->getResponse()->redirect(wa()->getRootUrl().wa()->getRouting()->getRootUrl().$url);
                }
            }
        }
    }
}