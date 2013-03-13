<?php
class shopBackendOrdersController extends waViewController
{
    public function execute()
    {
        $this->setLayout(new shopBackendLayout());
        $this->executeAction(new shopBackendOrdersAction());
    }
}
