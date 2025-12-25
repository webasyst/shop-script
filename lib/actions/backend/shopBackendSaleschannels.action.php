<?php

class shopBackendSaleschannelsAction extends waViewAction
{
    public function execute()
    {

        if (!$this->getUser()->isAdmin('shop') && !wa()->getUser()->getRights('shop', 'design')) {
            throw new waRightsException('Access denied');
        } elseif (wa()->whichUI() != '1.3') {
            $url = wa()->getConfig()->getRootUrl().wa()->getConfig()->getBackendUrl().'/shop/';
            $this->redirect($url);
        }
        $this->setLayout(new shopBackendLayout());
        $this->layout->assign('no_level2', true);
    }
}
