<?php
/**
 * HTML for dialog that confirms deletion of product code.
 */
class shopSettingsTypefeatCodeDeleteDialogAction extends waViewAction
{
    public function execute()
    {
        $code_id = waRequest::request('id', '', waRequest::TYPE_STRING);

        $product_code_model = new shopProductCodeModel();
        $code = $product_code_model->getById($code_id);
        if (!$code) {
            throw new waException('Feature not found', 404);
        }

        $order_item_codes_model = new shopOrderItemCodesModel();
        $order_usage_count = $order_item_codes_model->countOrdersByCode($code_id);

        $this->view->assign([
            'code' => $code,
            'order_usage_count' => $order_usage_count,
        ]);
    }
}
