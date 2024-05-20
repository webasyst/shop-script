<?php
/**
 * Sidebar for products list section.
 */
class shopProdListSidebarAction extends waViewAction
{
    public function execute()
    {
        if (!wa()->getUser()->getRights('shop', 'products')) {
            throw new waRightsException(_w("Access denied"));
        }

        if (wa()->whichUI() == '1.3') {
            $url = wa()->getAppUrl() . shopHelper::getBackendEditorUrl();
            $this->redirect($url);
            exit;
        }
        $contact_settings_model = new waContactSettingsModel();
        $sidebar_menu_state = $contact_settings_model->getOne(wa()->getUser()->getId(), 'shop', 'sidebar_menu_state');

        $absolute_frontend_url = waIdna::dec(wa()->getRouteUrl('shop/frontend', true));
        $frontend_url_label = ifset(ref(explode('//', $absolute_frontend_url)), 1, $absolute_frontend_url);

        $this->view->assign([
            'sidebar_menu_state' => $sidebar_menu_state === null ? 1 : (int)$sidebar_menu_state,
            'frontend_url'       => wa()->getRouteUrl('shop/frontend'),
            'frontend_url_label' => $frontend_url_label,
        ]);
        $this->setTemplate('templates/actions/prod/main/ListSidebar.html');
    }
}
