<?php
class shopBackendStorefrontsController extends waViewController
{
    public function execute()
    {
        $this->setLayout(new shopBackendLayout());
        
        $this->layout->assign('no_level2', true);
        
        $this->executeAction(new shopBackendStorefrontsAction());
    }
}
