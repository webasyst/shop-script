<?php

class shopMarketingSidebarAction extends waViewAction
{
    public function execute()
    {
        $promo_model = new shopPromoModel();
        $this->view->assign([
            'counts' => $promo_model->countByStatus(),
            'additional_items' => $this->getPluginItems(),
        ]);
    }

    protected function getPluginItems()
    {
        /**
         * Sidebar in backend marketing section
         * Hook allows to add items to leftmost sidebar in different places.
         *
         * @event backend_marketing_sidebar
         * @return array[string][string]string $return[%plugin_id%]['promos_li'] html output
         * @return array[string][string]string $return[%plugin_id%]['tools_li'] html output
         * @return array[string][string]string $return[%plugin_id%]['settings_li'] html output
         * @return array[string][string]string $return[%plugin_id%]['bottom_li'] html output
         * @return array[string][string]string $return[%plugin_id%]['custom_html'] html output
         */
        $event_result = wa()->event('backend_marketing_sidebar');

        $additional_items = [
            'promos_li'   => [],
            'tools_li'    => [],
            'settings_li' => [],
            'bottom_li'   => [],
            'custom_html' => [],
        ];
        foreach($event_result as $plugin_id => $res) {
            foreach($additional_items as $k => $_) {
                if (isset($res[$k])) {
                    if (!is_array($res[$k])) {
                        $res[$k] = [$res[$k]];
                    }
                    foreach($res[$k] as $html) {
                        $additional_items[$k][] = $html;
                    }
                }
            }
        }

        return $additional_items;
    }
}
