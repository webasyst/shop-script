<?php

class shopBackendLayout extends waLayout
{
    public function execute()
    {
        // Welcome-screen-ralated stuff: redirect on first login unless skipped
        $app_settings_model = new waAppSettingsModel();
        if (waRequest::get('skipwelcome')) {
            $app_settings_model->del('shop', 'welcome');
            $app_settings_model->set('shop', 'show_tutorial', 1);
        } else if ($app_settings_model->get('shop', 'welcome')) {
            $this->redirect(wa()->getConfig()->getBackendUrl(true).'shop/?action=welcome');
        }

        // Tutorial tab status
        $tutorial_progress = 0;
        $tutorial_visible = $app_settings_model->get('shop', 'show_tutorial') || waRequest::request('module') == 'tutorial';
        if ($tutorial_visible) {
            $tutorial_progress = $this->getTutorialProgress();
        }

        $order_model = new shopOrderModel();
        $this->view->assign(array(
            'page' => $this->getPage(),
            'frontend_url' => wa()->getRouteUrl('shop/frontend'),
            'backend_menu' => $this->backendMenuEvent(),
            'new_orders_count' => $order_model->getStateCounters('new'),
            'tutorial_progress' => $tutorial_progress,
            'tutorial_visible' => $tutorial_visible,
        ));
    }

    // Layout is slightly different for different modules.
    // $page is passed to template to control that.
    protected function getPage()
    {
        // Default page
        if (wa()->getUser()->getRights('shop', 'orders')) {
            $default_page = 'orders';
        } elseif (wa()->getUser()->getRights('shop', 'type.%')) {
            $default_page = 'products';
        } elseif (wa()->getUser()->getRights('shop', 'design') || wa()->getUser()->getRights('shop', 'pages')) {
            $default_page = 'storefronts';
        } elseif (wa()->getUser()->getRights('shop', 'reports')) {
            $default_page = 'reports';
        } elseif (wa()->getUser()->getRights('shop', 'settings')) {
            $default_page = 'settings';
        } else {
            $default_page = 'products';
        }

        $module = waRequest::get('module', 'backend');
        $page = waRequest::get('action', ($module == 'backend') ? $default_page : 'default');
        if ($module != 'backend') {
            $page = $module.':'.$page;
        }
        $plugin = waRequest::get('plugin');
        if ($plugin) {
            if ($module == 'backend') {
                $page = ':'.$page;
            }
            $page = $plugin.':'.$page;
        }

        return $page;
    }

    protected function backendMenuEvent()
    {
        /**
         * Extend backend main menu
         * Add extra main menu items (tab items, submenu items)
         * @event backend_menu
         * @return array[string]array $return[%plugin_id%]
         * @return array[string][string]string $return[%plugin_id%]['aux_li'] Single menu items
         * @return array[string][string]string $return[%plugin_id%]['core_li'] Single menu items
         */
        return wa()->event('backend_menu');
    }

    protected function getTutorialProgress()
    {
        $total = 0;
        $complete = 0;
        foreach(shopTutorialActions::getActions(shopTutorialActions::backendTutorialEvent()) as $a) {
            $total++;
            if (!empty($a['complete'])) {
                $complete++;
            }
        }

        // When there's at least one unfinished item,
        // treat the last pseudo-item 'Profit!' as a real one
        // and take it into account when calculating percentage.
        if ($total != $complete) {
            $total++;
        }

        if ($total) {
            return round($complete*100 / $total);
        } else {
            return 100;
        }
    }
}

