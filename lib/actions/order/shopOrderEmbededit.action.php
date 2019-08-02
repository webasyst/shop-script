<?php

class shopOrderEmbededitAction extends shopOrderEditAction
{
    public function execute()
    {
        parent::execute();
        $this->setTemplate('templates/actions/order/OrderEdit.html');
        $this->view->assign('embedded_version', 1);
        $this->setLayout(new shopBackendLayout());
        $this->layout->setEmbedded(1);
    }
}
