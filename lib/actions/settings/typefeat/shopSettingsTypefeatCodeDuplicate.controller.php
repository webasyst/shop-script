<?php
/**
 * Duplicate existing code and attach it to the same product types.
 */
class shopSettingsTypefeatCodeDuplicateController extends waJsonController
{
    public function execute()
    {
        $old_code_id = waRequest::post('id', 0, 'int');
        $product_code_model = new shopProductCodeModel();
        $type_codes_model = new shopTypeCodesModel();
        $code_data = $product_code_model->getById($old_code_id);
        if (!$code_data) {
            throw new waException('Product code not found', 404);
        }

        // Modify name and code
        $old_name = $code_data['name'];
        if (preg_match('/^(.*\D)(\d+)$/', $old_name, $matches)) {
            $old_name = $matches[1];
            $number = $matches[2] + 1;
        } else {
            $old_name .= ' ';
            $number = 1;
        }
        $code_data['name'] = $old_name.$number;
        $code_data['code'] = rtrim($code_data['code'], '0123456789').$number;
        $code_data['code'] = $product_code_model->getUniqueCode($code_data['code']);
        foreach (array('id', 'icon', 'logo', 'protected', 'plugin_id') as $key) {
            unset($code_data[$key]);
        }

        // Duplicate code
        $new_code_id = $product_code_model->insert($code_data);

        // Save types this code belongs to
        $types_data = array_keys($type_codes_model->getTypesByCode($old_code_id));
        $type_codes_model->updateByCode($new_code_id, $types_data);
        $this->response = [
            'id' => $new_code_id,
        ] + $code_data;
    }
}
