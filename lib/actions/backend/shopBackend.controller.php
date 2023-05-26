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
        } else if (wa()->getUser()->getRights('shop', 'products')) {
            $this->executeAction(new shopBackendProductsAction());
        } else if (wa()->getUser()->getRights('shop', 'marketing')) {
            $wa_app_url = wa('shop')->getAppUrl(null, true);
            $this->redirect("{$wa_app_url}marketing/");
        } else if (wa()->getUser()->getRights('shop', 'customers')) {
            $this->setLayout(new shopBackendLayout());
            $this->executeAction(new shopCustomersAction());
        } else if (wa()->getUser()->getRights('shop', 'importexport')) {
            $this->executeAction(new shopBackendImportexportAction());
        } else {
            $this->setLayout(new shopBackendLayout());
            $this->blocks['content'] = _w("Access denied");
        }
    }
}
