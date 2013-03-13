<?php
class shopBackendStorefrontsAction extends waViewAction
{
    public function execute()
    {
        $this->getResponse()->setTitle(_w('Storefronts'));
    }
}
