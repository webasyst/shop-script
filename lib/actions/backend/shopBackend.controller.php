<?php

class shopBackendController extends waViewController
{
    public function execute()
    {
        if (waRequest::isMobile()) {
            $this->executeAction(new shopOrdersMobileAction());
        } else {
            if (wa()->getUser()->getRights('shop', 'orders')) {
                $this->executeAction(new shopBackendOrdersAction());
            } else {
                $this->executeAction(new shopBackendProductsAction());
            }
        }
    }
}