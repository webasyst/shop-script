<?php

class shopSettingsFeaturesTypeDeleteController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        $product_model = new shopProductModel();
        $id = waRequest::post('id');
        if ($product_model->getByField('type_id', $id)) {
            $this->errors[] = _w("There are products that belong to this product type. To delete this product type, you must either delete those products or assign them to another product type first.");
        } else {
            $model = new shopTypeModel();
            if ($model->countAll() > 1) {
                $model->deleteById($id);
            } else {
                $this->errors[] = _w("Can not delete product type. At least one product type must remain.");
            }
        }
    }
}
