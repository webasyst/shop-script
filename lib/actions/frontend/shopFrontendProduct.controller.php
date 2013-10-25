<?php

class shopFrontendProductController extends waViewController
{
    public function execute()
    {
        if (waRequest::param('url_type') == 2) {

            $product_model = new shopProductModel();

            if (waRequest::param('category_url')) {
                $category_model = new shopCategoryModel();
                $c = $category_model->getByField('full_url', waRequest::param('category_url'));
                if (!$c) {
                    throw new waException(_w('Product not found'), 404);
                }
                $product = $product_model->getByField(array('url' => waRequest::param('product_url'), 'category_id' => $c['id']));
            } else {
                $product = $product_model->getByField('url', waRequest::param('product_url'));
            }

            if (!$product) {
                // try find page
                $url = waRequest::param('category_url');
                $url_parts = explode('/', $url);
                waRequest::setParam('page_url', waRequest::param('product_url'));
                waRequest::setParam('product_url', end($url_parts));
                $this->executeAction(new shopFrontendProductPageAction());
            } else {
                $this->executeAction(new shopFrontendProductAction($product));
            }

        } else {
            $this->executeAction(new shopFrontendProductAction());
        }
    }
}