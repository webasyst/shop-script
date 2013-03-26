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

        $product = new shopProduct($product);

        $this->getBreadcrumbs($product, true);

        $reviews_model = new shopProductReviewsModel();
        $reviews = $reviews_model->getFullTree(
            $product['id'], 0, null, 'datetime DESC', array('escape' => true)
        );

        $config = wa()->getConfig();

        $this->view->assign(array(
            'product' => $product,
            'reviews' => $reviews,
            'reviews_count' => $reviews_model->count($product['id'], false),
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

        $this->setThemeTemplate('reviews.html');
    }

}
