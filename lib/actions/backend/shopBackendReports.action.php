<?php
class shopBackendReportsAction extends waViewAction
{
    public function execute()
    {
        $this->getResponse()->setTitle(_w('Reports'));
    }
}
