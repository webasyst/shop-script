<?php
/**
 * Exclude a product from the dataset
 */
class shopProdPresentationExcludeProductController extends waJsonController
{
    public function execute()
    {
        $product_id = waRequest::post('product_id', null, waRequest::TYPE_INT);
        $dataset_id = waRequest::post('dataset_id', null, waRequest::TYPE_STRING_TRIM);
        $type = waRequest::post('type', null, waRequest::TYPE_STRING_TRIM);

        $this->validateData($product_id, $dataset_id, $type);
        if (!$this->errors) {
            if ($type == 'set') {
                $model = new shopSetProductsModel();
            } elseif ($type == 'tag') {
                $model = new shopProductTagsModel();
            } else {
                $model = new shopCategoryProductsModel();
            }

            $type .= '_id';
            $model->deleteByField([
                'product_id' => $product_id,
                $type => $dataset_id
            ]);
        }
    }

    /**
     * @param int $product_id
     * @param int $dataset_id
     * @param string $type
     * @return void
     */
    protected function validateData($product_id, $dataset_id, $type)
    {
        if ($product_id <= 0) {
            $this->errors = [
                'id' => 'product_id',
                'text' => _w('Incorrect product ID.'),
            ];
        }
        if ((is_numeric($dataset_id) && $dataset_id <= 0) || (is_string($dataset_id) && strlen($dataset_id) === 0)) {
            $this->errors = [
                'id' => 'dataset_id',
                'text' => _w('Incorrect dataset ID.'),
            ];
        }
        if (!in_array($type, ['set', 'tag', 'category'])) {
            $this->errors = [
                'id' => 'type',
                'text' => _w('Incorrect type.'),
            ];
        }
    }
}
