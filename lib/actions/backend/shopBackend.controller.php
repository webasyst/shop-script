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
            } elseif ($this->getUser()->isAdmin('shop') || wa()->getUser()->getRights('shop', 'type.%')) {
                $this->executeAction(new shopBackendProductsAction());
            } elseif ($this->getRights('pages') || $this->getRights('design')) {
                $this->executeAction(new shopBackendStorefrontsAction());
            } elseif ($this->getRights('reports')) {
                $this->executeAction(new shopBackendReportsAction());
            } elseif (wa()->getUser()->getRights('shop', 'settings')) {
                $this->executeAction(new shopBackendSettingsAction());
            } else {
                $this->setLayout(new shopBackendLayout());
            }
        }
    }
}