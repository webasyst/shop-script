<?php

class shopProductPageEditAction extends waViewAction
{
    private $model;

    public function __construct($params = null) {
        $this->model = new shopProductPagesModel();
        parent::__construct($params);
    }

    public function execute()
    {
        $product_id = waRequest::get('product_id', null, waRequest::TYPE_INT);
        $product_model = new shopProductModel();
        $product = $product_model->getById($product_id);
        if (!$product) {
            throw new waException(_w("Unknown product"), 404);
        }
        $id = waRequest::get('id', null, waRequest::TYPE_INT);
        $page = $this->getPage($id);

        $url = rtrim(
            wa()->getRouteUrl('/frontend/productPage', array(
                'product_url' => $product['url'],
                'page_url' => ''
            ), true
        ), '/');

        $this->view->assign(array(
            'url' => waIdna::dec($url),
            'preview_hash' => $this->getPreviewHash(),
            'page' => $page,
            'lang' => substr(wa()->getLocale(), 0, 2),
            'product_id' => $product_id
        ));
    }

    private function getPage($id)
    {
        $page = null;
        if ($id) {
            $page = $this->model->get($id);
        }
        return $page ? $page : array();
    }

    protected function getPreviewHash()
    {
        return $this->model->getPreviewHash();
    }
}