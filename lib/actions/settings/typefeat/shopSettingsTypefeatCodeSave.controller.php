<?php
/**
 * Accept POST from product code editor dialog to save new or existing code.
 */
class shopSettingsTypefeatCodeSaveController extends waJsonController
{
    public function execute()
    {
        $code_id = waRequest::post('id', 0, 'int');
        $code_data = waRequest::post('code', [], 'array');
        if (empty($code_data['all_types_is_checked'])) {
            // Selected product type ids
            $code_types_data = waRequest::post('types', [], 'array_int');
        } else if ($code_data['all_types_is_checked'] == 'all') {
            // All product type ids
            $type_model = new shopTypeModel();
            $code_types_data = [0 => 0];
            foreach(array_keys($type_model->getAll('id')) as $type_id) {
                $code_types_data[$type_id] = $type_id;
            }
        } else {
            // Single product type id
            $code_types_data = [$code_data['all_types_is_checked'] => $code_data['all_types_is_checked']];
        }

        $code_data['name'] = trim(ifset($code_data, 'name', ''));
        if (!strlen($code_data['name'])) {
            $this->errors[] = [
                'name' => 'code[name]',
                'text' => _w('This field is required.'),
            ];
        }
        $code_data['code'] = trim(ifset($code_data, 'code', ''));
        if (!strlen($code_data['code'])) {
            $this->errors[] = [
                'name' => 'code[code]',
                'text' => _w('This field is required.'),
            ];
            return;
        }
        if ($this->errors) {
            return;
        }
        $code_data = array_intersect_key($code_data, [
            'name' => '',
            'code' => '',
        ]);

        $product_code_model = new shopProductCodeModel();

        // Make sure code is unique and transliterated if changed; if not unique, append numbers at the end.
        $code_data['code'] = $product_code_model->getUniqueCode($code_data['code'], ifempty($code_id));

        if ($code_id) {
            $saved_code_id = $code_id;
            $product_code_model->updateById($code_id, $code_data);
        } else {
            $saved_code_id = $product_code_model->insert($code_data);
        }

        // Save types this code belongs to
        $type_codes_model = new shopTypeCodesModel();
        $type_codes_model->updateByCode($saved_code_id, $code_types_data);
    }
}
