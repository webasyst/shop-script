<?php

class shopBackendController extends waViewController
{
    public function execute()
    {
        if (waRequest::isMobile()) {
            $this->executeAction(new shopOrdersMobileAction());
        } else {
            $this->executeAction(new shopBackendOrdersAction());
        }
    }
}