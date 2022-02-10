<?php
/**
 * Контроллер возвращает данные типа продукта
 * Used on product/general/ page.
 */
class shopProdTypeSettingsController extends waJsonController
{
    public function execute() {
        $type_id = waRequest::request("type_id", null, "string");
        $type = null;
        $errors = [];

        if (!$type_id) {
            $errors[] = array("id" => "type_id", "text" => _w("Missing product type ID."));
        } else {
            $type_model = new shopTypeModel();
            $type = $type_model->getById($type_id);
            if (!$type) {
                $errors[] = array( "id" => "product_type", "text" => _w("Product type not found."));
            }
        }

        if (!empty($errors)) {
            $this->errors = $errors;
            return;
        }

        $this->response = shopProdSkuAction::getProductFractional($type);
    }
}
