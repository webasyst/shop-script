<?php

class shopProdGetProductsHashAction extends waViewAction
{
    public function execute()
    {
        $this->view->assign([
            'products_hash' => shopProdAssociatePromoDialogAction::getProductsHash(),
        ]);
    }
}