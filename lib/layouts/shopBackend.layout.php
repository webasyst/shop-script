<?php
class shopBackendLayout extends waLayout
{
    public function execute()
    {
        $user = wa()->getUser();
        $product_rights = $user->isAdmin('shop') || wa()->getUser()->getRights('shop', 'type.%');

        if (wa()->getUser()->getRights('shop', 'orders')) {
            $default_page = 'orders';
        } else if ($product_rights) {
            $default_page = 'products';
        } else {
            $default_page = null;
        }

        if (!$default_page) {
            throw new waRightsException(_w("Access denied"));
        }

        $this->assign('product_rights', $product_rights);

        $order_model = new shopOrderModel();
        $this->assign('new_orders_count', $order_model->getStateCounters('new'));

        $module = waRequest::get('module', 'backend');
        $this->assign('default_page', $default_page);
        $page = waRequest::get('action', ($module == 'backend') ? $default_page : 'default');
        if ($module != 'backend') {
            $page = $module.':'.$page;
        }
        $this->assign('page', $page);
        $submenu_class = 'shopBackend'.ucfirst($page).'SubmenuAction';
        if ($submenu_class && class_exists($submenu_class)) {
            $submenu_action = new $submenu_class();
            $this->executeAction('submenu', $submenu_action);
        } else {
            $this->assign('submenu', '<!-- there no default submenu -->');
        }

        $this->assign('frontend_url', wa()->getRouteUrl('shop/frontend'));

        /**
         * Extend backend main menu
         * Add extra main menu items (tab items, submenu items)
         * @event backend_menu
         * @return array[string]array $return[%plugin_id%]
         * @return array[string][string]string $return[%plugin_id%]['aux_li'] Single menu items
         * @return array[string][string]string $return[%plugin_id%]['core_li'] Single menu items
         * @return array[string][string]string $return[%plugin_id%]['reports_li'] Single menu items
         * @return array[string][string]string $return[%plugin_id%]['orders_viewmode_li'] Single menu items
         */
        $this->assign('backend_menu', wa()->event('backend_menu' /*,$page*/));
    }
}
