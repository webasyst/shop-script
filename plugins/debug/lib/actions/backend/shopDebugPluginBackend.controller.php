<?php

class shopDebugPluginBackendController extends waViewController
{
    public function execute()
    {
        $this->setLayout(new shopBackendLayout());
        $this->layout->assign('no_level2', true);
        $this->getResponse()->setTitle(_wp('my plugin'));
        $this->executeAction(new shopDebugPluginBackendAction());
    }
}
