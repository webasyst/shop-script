<?php

class shopProductPageSaveController extends waJsonController
{
    /**
     * @var shopProductPagesModel
     */
    private $pages_model;
    /**
     * @var shopProductModel
     */
    private $product_model;

    private $param_names = array('description', 'keywords');

    /**
     * @var array
     */
    private $product;

    public function __construct() {
        $this->pages_model = new shopProductPagesModel();
        $this->product_model = new shopProductModel();
    }

    public function execute()
    {
        $id = waRequest::get('id', null, waRequest::TYPE_INT);

        $data    = $this->getData($id);
        if (!isset($data['product_id']) && $id) {
            $data['product_id'] = $this->pages_model->select('product_id')->where('id='.(int)$id)->fetchField('product_id');
        }
        $product = $this->getProduct($data['product_id']);

        // check rights
        if (!$this->product_model->checkRights($product)) {
            throw new waException(_w("Access denied"));
        }

        if ($id) {
            if (!$this->pages_model->update($id, $data)) {
                $this->errors[] = _w('Error saving product page');
                return;
            }
        } else {
            $id = $this->pages_model->add($data);
            if (!$id) {
                $this->errors[] = _w('Error saving product page');
                return;
            }
        }

        $page = $this->pages_model->getById($id);
        $page['name'] = htmlspecialchars($data['name']);
        $page['frontend_url'] = rtrim(
            wa()->getRouteUrl('/frontend/productPage', array(
                'product_url' => $product['url'],
                'page_url' => ''
            ), true
        ), '/');
        $page['preview_hash'] = $this->pages_model->getPreviewHash();
        $page['url_escaped'] = htmlspecialchars($data['url']);
        $this->response = $page;
    }

    public function getData($id)
    {
        $data = waRequest::post('info');
        if (!$id) {
            $product_id = waRequest::get('product_id', null, waRequest::TYPE_INT);
            $product = $this->getProduct($product_id);
            $data['product_id'] = $product['id'];
        }
        $data['url'] = trim($data['url'], '/');
        if (!$id && !$data['url']) {
            $data['url'] = shopHelper::transliterate($data['name']);
        }
        if (empty($data['name'])) {
            $data['name'] = '('._ws('no-title').')';
        }
        $data['status'] = isset($data['status']) ? 1 : 0;
        return $data;
    }

    public function getProduct($product_id)
    {
        if ($this->product === null) {
            $this->product = $this->product_model->getById($product_id);
            if (!$this->product) {
                throw new waException(_w("Unknown product"), 404);
            }
        }
        return $this->product;
    }
}