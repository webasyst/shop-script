<?php
/**
 * Add sku file. Used on Sku tab.
 */
class shopProdAddSkuFileController extends shopUploadController
{
    protected function save(waRequestFile $file)
    {
        $fields = array(
            'id'         => waRequest::post('sku_id', null, waRequest::TYPE_INT),
            'product_id' => waRequest::post('product_id', null, waRequest::TYPE_INT),
        );

        $path = "sku_file/{$fields['id']}." . pathinfo($file->name, PATHINFO_EXTENSION);
        $file_path = shopProduct::getPath($fields['product_id'], $path);
        if ((file_exists($file_path) && !is_writable($file_path)) || (!file_exists($file_path) && !waFiles::create($file_path))) {
            $this->errors = array(
                'id' => 'folder_permissions',
                'text' => sprintf(
                    "The insufficient file write permissions for the %s folder.",
                    substr($file_path, strlen($this->getConfig()->getRootPath()))
                )
            );
            return;
        } else {
            $file->moveTo($file_path);

            $product_skus_model = new shopProductSkusModel();
            $data = array(
                'file_size' => $file->size,
                'file_name' => $file->name,
            );
            $product_skus_model->updateByField($fields, $data);
        }

        if (empty($file->error)) {
            $response = array(
                'id' => $fields['id'],
                'url' => shopProdDownloadSkuFileController::getSkuFileUrl($fields['id'], $fields['product_id']),
                'name' => $file->name,
                'size' => waFiles::formatSize($file->size),
                'description' => '',
            );
            $this->response = $response;
        }
    }

    public function display()
    {
        $this->getResponse()->addHeader('Content-Type', 'application/json');
        $this->getResponse()->sendHeaders();
        if (!$this->errors) {
            echo waUtils::jsonEncode(array('status' => 'ok', 'data' => $this->response));
        } else {
            echo waUtils::jsonEncode(array('status' => 'fail', 'errors' => $this->errors));
        }
    }
}