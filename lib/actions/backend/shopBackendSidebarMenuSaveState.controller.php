<?php

class shopBackendSidebarMenuSaveStateController extends waJsonController
{
    public function execute()
    {
        $sidebar_menu_state = waRequest::post('sidebar_menu_state', 0, waRequest::TYPE_INT);
        $contact_settings_model = new waContactSettingsModel();
        $contact_settings_model->set(wa()->getUser()->getId(), 'shop', 'sidebar_menu_state', $sidebar_menu_state);
    }
}