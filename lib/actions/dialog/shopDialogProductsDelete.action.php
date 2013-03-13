<?php

class shopDialogProductsDeleteAction extends waViewAction
{
    public function execute()
    {
        $this->view->assign('count', waRequest::get('count', 0, waRequest::TYPE_INT));
    }
}