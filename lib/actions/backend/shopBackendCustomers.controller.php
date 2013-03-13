<?php
class shopBackendCustomersController extends waViewController
{
    public function execute()
    {
        $this->setLayout(new shopBackendLayout());
        $this->executeAction(new shopCustomersAction());
    }
}
