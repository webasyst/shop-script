<?php
/**
 * Delete given product code.
 */
class shopSettingsTypefeatCodeDeleteController extends waJsonController
{
    public function execute()
    {
        $code_id = waRequest::post('id', null, 'int');

        if (!$code_id) {
            return;
        }

        $product_code_model = new shopProductCodeModel();
        $product_code_model->deleteById($code_id);

        $type_codes_model = new shopTypeCodesModel();
        $type_codes_model->deleteByField([
            'code_id' => $code_id,
        ]);
    }
}
