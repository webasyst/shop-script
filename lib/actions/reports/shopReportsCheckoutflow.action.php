<?php

class shopReportsCheckoutflowAction extends waViewAction
{
    public function execute()
    {
        list($start_date, $end_date, $group_by) = shopReportsSalesAction::getTimeframeParams();
        
        $checkout_flow = new shopCheckoutFlowModel();
        $stat = $checkout_flow->getStat($start_date, $end_date);
        
        $app_settings_model = new waAppSettingsModel();

        $this->view->assign(array(
            'stat' => $stat,
            'checkout_flow_changed' => $app_settings_model->get('shop', 'checkout_flow_changed', 0),
            'storefronts' => $this->getStorefronts()
        ));
    }

    protected function getStorefronts()
    {
        $list = new shopStorefrontList();
        $list->addFilter(function($storefront) {
            $checkout_version = ifset($storefront, 'route', 'checkout_version', false);
            return $checkout_version < 2;
        });
        return $list->fetchAll();
    }
}
