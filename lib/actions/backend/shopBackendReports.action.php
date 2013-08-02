<?php
class shopBackendReportsAction extends waViewAction
{
    public function execute()
    {
        $this->getResponse()->setTitle(_w('Reports'));

        /*
         * @event backend_reports
         * @return array[string]array $return[%plugin_id%] array of html output
         * @return array[string][string]string $return[%plugin_id%]['menu_li'] html output
         */
        $this->getLayout()->assign('backend_reports', wa()->event('backend_reports'));
    }
}
