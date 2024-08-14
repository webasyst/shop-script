<?php

class shopFrontendProductReviewsAction extends shopFrontendProductAction
{
    public function execute()
    {
        $this->setLayout(new shopFrontendLayout());

        $product_model = new shopProductModel();
        $product = $product_model->getByField('url', waRequest::param('product_url'));
        if (!$product) {
            throw new waException(_w('Product not found.'), 404);
        }

        if ($types = waRequest::param('type_id')) {
            if (!in_array($product['type_id'], (array)$types)) {
                throw new waException(_w('Product not found.'), 404);
            }
        }

        $product = new shopProduct($product, true);
        if ($product['status'] < 0) {
            // do the redirect when product is in "hidden and not available" status
            shopFrontendProductAction::handleHiddenAndNotAvailable($product);
        }
        $this->ensureCanonicalUrl($product);
        $this->prepareProduct($product);
        $this->assignFeaturesSelectable($product);

        // get services
        list($services, $skus_services) = $this->getServiceVars($product);
        $this->view->assign('sku_services', $skus_services);
        $this->view->assign('services', $services);

        $this->view->assign('stocks', shopHelper::getStocks(true));

        $this->view->assign('currency_info', $this->getCurrencyInfo());

        $this->getBreadcrumbs($product, true);

        $reviews_model = new shopProductReviewsModel();
        $reviews = $reviews_model->getFullTree(
            $product['id'], 0, null, 'datetime DESC', array('escape' => true)
        );

        /** @var shopConfig $config */
        $config = wa()->getConfig();

        $this->view->assign(array(
            'product' => $product,
            'reviews' => $reviews,
            'reviews_count' => $reviews_model->count($product['id']),
            'reply_allowed' => true,
            'auth_adapters' => $adapters = wa()->getAuthAdapters(),
            'request_captcha' => $config->getGeneralSettings('require_captcha'),
            'require_authorization' => $config->getGeneralSettings('require_authorization'),
            'review_service_agreement' => $config->getGeneralSettings('review_service_agreement'),
            'review_service_agreement_hint' => $config->getGeneralSettings('review_service_agreement_hint'),
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

    /** @param shopProduct $product */
    protected function ensureCanonicalUrl($product)
    {
        $root_url = ltrim(wa()->getRootUrl(false, true), '/');

        $canonical_url = $product->getProductUrl(true, true, false);
        $canonical_url = rtrim($canonical_url, '/') . '/reviews/';  // not very good approach to build url
        $canonical_url = ltrim(substr($canonical_url, strlen($root_url)), '/');
        $actual_url = explode('?', wa()->getConfig()->getRequestUrl(), 2);
        $actual_url = ltrim(urldecode($actual_url[0]), '/');

        if ($canonical_url != $actual_url) {
            $q = waRequest::server('QUERY_STRING');
            $this->redirect('/'.$canonical_url.($q ? '?'.$q : ''), 301);
        }
    }

}
