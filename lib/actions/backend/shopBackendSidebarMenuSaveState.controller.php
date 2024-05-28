<?php

class shopBackendSidebarMenuSaveStateController extends waJsonController
{
    public function execute()
    {
        $contact_settings_model = new waContactSettingsModel();

        $sidebar_menu_state = waRequest::post('sidebar_menu_state', null, waRequest::TYPE_INT);
        if ($sidebar_menu_state !== null) {
            $contact_settings_model->set(wa()->getUser()->getId(), 'shop', 'sidebar_menu_state', $sidebar_menu_state);
        }

        $hide_mobile_ad = waRequest::post('hide_mobile_ad', null);
        if ($hide_mobile_ad !== null) {
            $contact_settings_model->set(wa()->getUser()->getId(), 'shop', 'hide_mobile_ad_till', date('Y-m-d', strtotime('+30 days')));
        }

        $hide_premium_ad = waRequest::post('hide_premium_ad', null);
        if ($hide_premium_ad !== null) {
            $contact_settings_model->set(wa()->getUser()->getId(), 'shop', 'hide_premium_ad_till', date('Y-m-d', strtotime('+90 days')));
        }
    }
}
