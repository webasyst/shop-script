<?php

class shopBackendController extends waViewController {
    public function execute() {
        if (waRequest::isMobile() && !waRequest::cookie('force_set_wa_backend_ui_version')) {
            $this->getResponse()->setCookie('force_set_wa_backend_ui_version', '2.0');
        }
        if (wa()->getUser()->getRights('shop', 'orders')) {
            $this->executeAction(new shopBackendOrdersAction());
        } else if ($this->getRights('pages') || $this->getRights('design')) {
            $this->executeAction(new shopBackendStorefrontsAction());
        } else if ($this->getRights('reports')) {
            $this->executeAction(new shopBackendReportsAction());
        } else if (wa()->getUser()->getRights('shop', 'settings')) {
            $this->executeAction(new shopBackendSettingsAction());
        } else {
            $this->executeAction(new shopBackendProductsAction());
        }
    }
}
