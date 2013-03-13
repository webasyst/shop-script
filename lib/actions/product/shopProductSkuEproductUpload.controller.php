<?php

class shopProductSkuEproductUploadController extends shopUploadController
{
    /**
     * @var shopProductSkusModel
     */
    private $model;

    protected function save(waRequestFile $file)
    {
        if (!$this->model) {
            $this->model = new shopProductSkusModel();
        }
        $field = array(
            'id'         => waRequest::post('sku_id', null, waRequest::TYPE_INT),
            'product_id' => waRequest::post('product_id', null, waRequest::TYPE_INT),
        );

        $data = array(
            'file_size' => $file->size,
            'file_name' => $file->name,
        );

        $this->model->updateByField($field, $data);

        $file_path = shopProduct::getPath($field['product_id'], "sku_file/{$field['id']}.".pathinfo($file->name, PATHINFO_EXTENSION));
        if ((file_exists($file_path) && !is_writable($file_path)) || (!file_exists($file_path) && !waFiles::create($file_path))) {
            $data = array(
                'file_size' => 0,
                'file_name' => '',
            );
            $this->model->updateByField($field, $data);
            throw new waException(sprintf("The insufficient file write permissions for the %s folder.", substr($file_path, strlen($this->getConfig()->getRootPath()))));
        }

        $file->moveTo($file_path);

        return array(
            'name' => $file->name,
            'size' => waFiles::formatSize($file->size),
        );
    }
}
