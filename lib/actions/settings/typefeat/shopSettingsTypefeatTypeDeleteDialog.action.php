<?php
/**
 * HTML for dialog that confirms deletion of product type.
 */
class shopSettingsTypefeatTypeDeleteDialogAction extends waViewAction
{
    public function execute()
    {
        $type_id = waRequest::request('type', '', waRequest::TYPE_STRING);

        $type_model = new shopTypeModel();
        if ($type_id) {
            $type = $type_model->getById($type_id);
        }
        if (empty($type)) {
            throw new waException('Not found', 404);
        }

        // Count products that belong to this type:
        // must not delete type that has products attached to it
        $product_model = new shopProductModel();
        $products_count = $product_model->countByField('type_id', $type_id);

        $this->view->assign([
            'products_count' => $products_count,
            'type' => $type,
        ]);
    }
}
