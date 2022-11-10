<?php

class shopProdSetPublicationDialogAction extends waViewAction
{
    public function execute()
    {
//        if (!$this->getUser()->getRights('shop', 'setscategories')) {
//            throw new waRightsException(_w('Access denied'));
//        }

        $this->view->assign([]);

        $this->setTemplate('templates/actions/prod/main/dialogs/products.set_publication.html');
    }
}