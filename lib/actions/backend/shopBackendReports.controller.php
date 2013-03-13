<?php
class shopBackendReportsController extends waViewController
{
    public function execute()
    {
        $this->setLayout(new shopBackendLayout());
        $this->executeAction(new shopBackendReportsAction());

    }
}
