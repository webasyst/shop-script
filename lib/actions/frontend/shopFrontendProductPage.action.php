<?php

class shopFrontendProductPageAction extends shopFrontendProductAction
{
    public function execute()
    {
        $product_model = new shopProductModel();
        try {
            $product = $product_model->getByField('url', waRequest::param('product_url'));
        } catch (waException $e) {
            $this->pageNotFound();
        }
        if (!$product) {
            $this->pageNotFound();
        }

        if ($types = waRequest::param('type_id')) {
            if (!in_array($product['type_id'], (array)$types)) {
                $this->pageNotFound();
            }
        }

        $product = new shopProduct($product, true);
        $this->view->assign('product', $product);

        $this->getBreadcrumbs($product, true);

        $page_model = new shopProductPagesModel();
        try {
            $page = $page_model->getByField(array('product_id' => $product['id'], 'url' => waRequest::param('page_url')));
        } catch (waException $e) {
            $this->pageNotFound();
        }
        if (!$page['status']) {
            $hash = $this->appSettings('preview_hash');
            if (!$hash || md5($hash) != waRequest::get('preview')) {
                $this->pageNotFound();
            }
        }

        if (!$page) {
            $this->pageNotFound();
        }
        if (!$page['title']) {
            $page['title'] = $page['name'];
        }

        $this->ensurePageCanonicalUrl($product, waRequest::param('page_url'));

        // interpret smarty code
        $page['content'] = $this->view->fetch('string:'.$page['content']);

        $this->view->assign('page', $page);
        $this->view->assign('reviews_total_count', $this->getReviewsTotalCount($product['id']));

        $this->getResponse()->setTitle($product['name'].' - '.$page['title']);
        $this->getResponse()->setMeta(array(
            'keywords' => isset($page['keywords']) ? $page['keywords'] : '',
            'description' => isset($page['description']) ? $page['description'] : ''
        ));

        /**
         * @event frontend_product
         * @param shopProduct $product
         * @return array[string][string]string $return[%plugin_id%]['menu'] html output
         * @return array[string][string]string $return[%plugin_id%]['cart'] html output
         * @return array[string][string]string $return[%plugin_id%]['block_aux'] html output
         * @return array[string][string]string $return[%plugin_id%]['block'] html output
         */
        $this->view->assign('frontend_product', wa()->event('frontend_product', $product, array('menu','cart','block_aux','block')));

        $this->setThemeTemplate('product.page.html');
    }

    protected function pageNotFound()
    {
        throw new waException('Page not found', 404);
    }

    /** @param shopProduct $product */
    protected function ensurePageCanonicalUrl($product, $page_url)
    {
        $root_url = ltrim(wa()->getRootUrl(false, true), '/');

        $canonical_url = $product->getProductUrl(true, true, false);
        // not very good approach to build url
        $canonical_url = rtrim($canonical_url, '/') . '/' . $page_url . '/';
        $canonical_url = ltrim(substr($canonical_url, strlen($root_url)), '/');

        $actual_url = explode('?', wa()->getConfig()->getRequestUrl(), 2);
        $actual_url = ltrim(urldecode($actual_url[0]), '/');

        if ($canonical_url != $actual_url) {
            $q = waRequest::server('QUERY_STRING');
            $this->redirect('/'.$canonical_url.($q ? '?'.$q : ''), 301);
        }
    }
}
