<?php
/**
 * Delete sku file. Used on Sku tab.
 */
class shopProdDeleteSkuFileController extends waJsonController
{
    public function execute()
    {
        $fields = array(
            'id'         => waRequest::post('sku_id', null, waRequest::TYPE_INT),
            'product_id' => waRequest::post('product_id', null, waRequest::TYPE_INT),
        );
        $product = new shopProduct($fields['product_id']);
        if (!$product->getId()) {
            $this->errors[] = [
                'id' => 'not_found',
                'text' => _w('Product not found.'),
            ];
            return;
        }
        $product_skus_model = new shopProductSkusModel();
        $file_name = $product_skus_model->getByField($fields)['file_name'];
        $path = "sku_file/{$fields['id']}";
        $dot_position = strripos($file_name, '.');
        if ($dot_position !== false) {
            $path .= substr($file_name, $dot_position);
        } else {
            $path .= '.';
        }
        $file_path = shopProduct::getPath($fields['product_id'], $path);

        try {
            waFiles::delete($file_path);
            $empty_fields = array(
                'file_name' => null,
                'file_size' => null,
                'file_description' => null,
            );
            $product_skus_model->updateByField($fields, $empty_fields);
        } catch (waException $e) {
            $this->errors = array(
                'id' => 'file_delete',
                'text' => _w('Cannot delete the file.')
            );
        }
    }
}
