<?php

class shopProductPagesAction extends waViewAction
{
    /**
     * @var shopProductPagesModel
     */
    protected $product_pages_model;

    public function __construct($params = null) {
        $this->product_pages_model = new shopProductPagesModel();
        parent::__construct($params);
    }

    public function execute()
    {
        $product_id = waRequest::get('id', 0, waRequest::TYPE_INT);
        if (!$product_id) {
            throw new waException("Unknown product");
        }
        $page_id = null;
        $param = waRequest::get('param', array(), waRequest::TYPE_ARRAY_INT);
        if (!empty($param[0])) {
            $page_id = $param[0];
        }

        $pages = $this->getPages($product_id);
        $this->view->assign(array(
            'lang' => substr(wa()->getLocale(), 0, 2),
            'pages' => $pages,
            'product_id' => $product_id,
            'page_id' => $page_id,
            'count' => count($pages)
        ));
    }

    public function getPages($product_id)
    {
        return $this->product_pages_model->getPages($product_id);
    }
}