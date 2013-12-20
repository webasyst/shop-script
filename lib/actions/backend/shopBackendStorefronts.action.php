<?php
class shopBackendStorefrontsAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'design') && !$this->getUser()->getRights('shop', 'pages')) {
            throw new waException(_w("Access denied"));
        }
        $this->setLayout(new shopBackendLayout());
        $this->layout->assign('no_level2', true);
        $this->getResponse()->setTitle(_w('Storefronts'));
    }
}
