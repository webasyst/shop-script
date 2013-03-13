<?php

class shopFrontendProductController extends waViewController
{
    public function execute()
    {
        if (waRequest::param('url_type') == 2) {
            $product_model = new shopProductModel();
            $product = $product_model->getByField('url', waRequest::param('product_url'));
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