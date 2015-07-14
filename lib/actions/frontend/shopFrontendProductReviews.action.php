<?php

class shopFrontendProductReviewsAction extends shopFrontendProductAction
{
    public function execute()
    {
        $this->setLayout(new shopFrontendLayout());

        $product_model = new shopProductModel();
        $product = $product_model->getByField('url', waRequest::param('product_url'));
        if (!$product) {
            throw new waException('Product not found', 404);
        }

        if ($types = waRequest::param('type_id')) {
            if (!in_array($product['type_id'], (array)$types)) {
                throw new waException(_w('Product not found'), 404);
            }
        }

        $product = new shopProduct($product, true);
        $this->prepareProduct($product);

        // get services
        list($services, $skus_services) = $this->getServiceVars($product);
        $this->view->assign('sku_services', $skus_services);
        $this->view->assign('services', $services);

        $stock_model = new shopStockModel();
        $this->view->assign('stocks', $stock_model->getAll('id'));

        $this->view->assign('currency_info', $this->getCurrencyInfo());

        $this->getBreadcrumbs($product, true);

        $reviews_model = new shopProductReviewsModel();
        $reviews = $reviews_model->getFullTree(
            $product['id'], 0, null, 'datetime DESC', array('escape' => true)
        );

        $config = wa()->getConfig();

        $this->view->assign(array(
            'product' => $product,
            'reviews' => $reviews,
            'reviews_count' => $reviews_model->count($product['id']),
            'reply_allowed' => true,
            'auth_adapters' => $adapters = wa()->getAuthAdapters(),
            'request_captcha' => $config->getGeneralSettings('require_captcha'),
            'require_authorization' => $config->getGeneralSettings('require_authorization')
        ));

        $storage = wa()->getStorage();
        $current_auth = $storage->read('auth_user_data');
        $current_auth_source = $current_auth ? $current_auth['source'] : shopProductReviewsModel::AUTH_GUEST;

        $this->view->assign('current_auth_source', $current_auth_source);
        $this->view->assign('current_auth', $current_auth, true);

        $meta_fields = $this->getMetafields($product);
        $title = $meta_fields['meta_title'] ? $meta_fields['meta_title'] : $product['name'];
        $title = sprintf_wp('%s reviews', $title);
        $meta_fields['meta_keywords'] && ($meta_fields['meta_keywords'] = $meta_fields['meta_keywords'].', '._w("Reviews"));
        wa()->getResponse()->setTitle($title);
        wa()->getResponse()->setMeta('keywords', $meta_fields['meta_keywords']);
        wa()->getResponse()->setMeta('description', $meta_fields['meta_description']);

        /**
         * @event frontend_product
         * @param shopProduct $product
         * @return array[string][string]string $return[%plugin_id%]['menu'] html output
         * @return array[string][string]string $return[%plugin_id%]['cart'] html output
         * @return array[string][string]string $return[%plugin_id%]['block_aux'] html output
         * @return array[string][string]string $return[%plugin_id%]['block'] html output
         */
        $this->view->assign('frontend_product', wa()->event('frontend_product', $product, array('menu','cart','block_aux','block')));

        $this->setThemeTemplate('reviews.html');
    }

}
