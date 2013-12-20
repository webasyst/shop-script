<?php
class shopBackendSettingsAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w("Access denied"));
        }
        
        $this->setLayout(new shopBackendLayout());
        
        //TODO get dynamic sections lists
        /**
         * @event backend_settings
         * @return array[string]array $return[%plugin_id%] array of html output
         * @return array[string][string]string $return[%plugin_id%]['sidebar_top_li'] html output
         * @return array[string][string]string $return[%plugin_id%]['sidebar_middle_li'] html output
         * @return array[string][string]string $return[%plugin_id%]['sidebar_bottom_li'] html output
         */
        $this->view->assign('backend_settings', wa()->event('backend_settings'));
    }
}
