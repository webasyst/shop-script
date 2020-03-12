<?php
/**
 * Delete given product type. Return validation error if impossible.
 */
class shopSettingsTypefeatTypeDeleteController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id');

        // Not allowed to delete type that has products belong to it
        $product_model = new shopProductModel();
        if ($product_model->getByField('type_id', $id)) {
            $this->errors[] = _w("There are products that belong to this product type. To delete this product type, you must either delete those products or assign them to another product type first.");
            return;
        }

        // Not allowed to delete last type
        $type_model = new shopTypeModel();
        if ($type_model->countAll() <= 1) {
            $this->errors[] = _w("Cannot delete product type. At least one product type must remain.");
            return;
        }

        $type_model->deleteById($id);
        $this->response = 'ok';
    }
}
