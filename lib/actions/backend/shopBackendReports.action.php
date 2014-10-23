<?php
class shopBackendReportsAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'reports')) {
            throw new waRightsException(_w("Access denied"));
        }
        
        $this->getResponse()->setTitle(_w('Reports'));

        $this->setLayout(new shopBackendLayout());
        
        /*
         * @event backend_reports
         * @return array[string]array $return[%plugin_id%] array of html output
         * @return array[string][string]string $return[%plugin_id%]['menu_li'] html output
         */
        $this->getLayout()->assign('backend_reports', wa()->event('backend_reports'));
        $this->view->assign('lang', substr(wa()->getLocale(), 0, 2));
    }
}
