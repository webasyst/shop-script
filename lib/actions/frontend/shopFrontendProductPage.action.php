<?php

class shopFrontendProductPageAction extends shopFrontendProductAction
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
        $this->view->assign('product', $product);

        $this->getBreadcrumbs($product, true);

        $page_model = new shopProductPagesModel();
        $page = $page_model->getByField(array('product_id' => $product['id'], 'url' => waRequest::param('page_url')));
        if (!$page['status']) {
            $hash = $this->appSettings('preview_hash');
            if (!$hash || md5($hash) != waRequest::get('preview')) {
                throw new waException('Page not found', 404);
            }
        }

        if (!$page) {
            throw new waException('Page not found', 404);
        }
        if (!$page['title']) {
            $page['title'] = $page['name'];
        }

        // interpret smarty code
        $page['content'] = $this->view->fetch('string:'.$page['content']);

        $this->view->assign('page', $page);
        $this->view->assign('reviews_total_count', $this->getReviewsTotalCount($product['id']));

        $this->getResponse()->setTitle($product['name'].' - '.$page['title']);
        $this->getResponse()->setMeta(array(
            'keywords' => isset($page['keywords']) ? $page['keywords'] : '',
            'description' => isset($page['description']) ? $page['description'] : ''
        ));



        $this->setThemeTemplate('product.page.html');
    }
}