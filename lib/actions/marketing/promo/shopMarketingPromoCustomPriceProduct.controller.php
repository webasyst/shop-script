<?php

class shopMarketingPromoCustomPriceProductController extends waJsonController
{
    public function execute()
    {
        $product_id = waRequest::post('product_id', null, waRequest::TYPE_INT);
        if (!$product_id) {
            return;
        }

        $collection = new shopProductsCollection('id/'.$product_id);
        $product_data = $collection->getProducts('id,name,images,currency,skus', 0, 10000, false);
        if (empty($product_data)) {
            return;
        }

        $product_data = array_shift($product_data);

        $view = wa('shop')->getView();
        $view->assign([
            'options' => waRequest::post('options', [], waRequest::TYPE_ARRAY_TRIM),
            'product' => $product_data,
        ]);
        $template = wa()->getAppPath('templates/actions/marketing/rules/custom_price.product.html', 'shop');
        $html = $view->fetch($template);

        $this->response = [
            'product' => $product_data,
            'html'    => $html,
        ];
    }
}