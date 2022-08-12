<?php
/**
 * Delete several product images. Used on Media tab.
 */
class shopProdDeleteImageController extends waJsonController
{
    public function execute()
    {
        $image_ids = waRequest::request('id', [], waRequest::TYPE_ARRAY_INT);

        $product_model = new shopProductModel();
        $product_images_model = new shopProductImagesModel();

        $images = $product_images_model->getById($image_ids);
        $this->response = [
            'images_not_found' => count($image_ids) - count($images),
            'access_denied' => 0,
            'deleted' => 0,
        ];

        $product_rights_ok = [];
        $products_not_found = [];
        foreach($images as $image) {
            if (!isset($products_not_found[$image['product_id']])) {
                $products_not_found[$image['product_id']] = $product_model->getById($image['product_id']);
            }
            if (empty($products_not_found[$image['product_id']])) {
                $this->response['products_not_found']++;
                continue;
            }
            if (!isset($product_rights_ok[$image['product_id']])) {
                $product_rights_ok[$image['product_id']] = $product_model->checkRights($image['product_id']);
            }
            if (empty($product_rights_ok[$image['product_id']])) {
                $this->response['access_denied']++;
                continue;
            }
            $product_images_model->delete($image['id']);
            $this->response['deleted']++;
        }
    }
}
