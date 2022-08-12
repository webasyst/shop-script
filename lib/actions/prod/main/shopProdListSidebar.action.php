<?php
/**
 * Sidebar for products list section.
 */
class shopProdListSidebarAction extends waViewAction
{
    public function execute()
    {
        shopProdAction::setNewDesign();
        $contact_settings_model = new waContactSettingsModel();
        $sidebar_menu_state = (int)$contact_settings_model->getOne(wa()->getUser()->getId(), 'shop', 'sidebar_menu_state');
        $this->view->assign('sidebar_menu_state', $sidebar_menu_state);
        $this->setTemplate('templates/actions/prod/main/ListSidebar.html');
    }
}
