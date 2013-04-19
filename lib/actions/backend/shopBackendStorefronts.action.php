<?php
class shopBackendStorefrontsAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'design')) {
            throw new waException(_w("Access denied"));
        }
        $this->getResponse()->setTitle(_w('Storefronts'));
    }
}
