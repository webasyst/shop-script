<?php
/**
 * Dialog to upload or select existing product image for SKU.
 */
class shopProdSkuImageDialogAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::request('product_id', null, 'int');
        $sku_id = waRequest::request('sku_id', null, 'int');
        $selected_image_id = waRequest::request('image_id', null, 'int');

        $product = new shopProduct($product_id);
        if (!$product['id']) {
            throw new waException('Not found', 404);
        }

        $sku = ifset($product, 'skus', $sku_id, null);
        if (empty($selected_image_id)) {
            $selected_image_id = ifempty($sku, 'image_id', null);
        }

        $images = $product->getImages([
            'thumb' => wa('shop')->getConfig()->getImageSize('thumb'),
            'crop' => wa('shop')->getConfig()->getImageSize('crop'),
        ]);

        $this->view->assign([
            'sku' => $sku,
            'product' => $product,
            'selected_image_id' => (string)$selected_image_id,
            'images' => $images,
        ]);
    }
}
