<?php

class shopProdAssignTagsDialogAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);

        $this->view->assign([]);

        $this->setTemplate('templates/actions/prod/main/dialogs/products.assign_tags.html');
    }
}
