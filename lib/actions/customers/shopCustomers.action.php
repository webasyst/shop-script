<?php

/** Loaded with layout. Shows sidebar and initializes JS, then loads other actions depending on URL hash. */
class shopCustomersAction extends waViewAction
{
    public function execute()
    {
        $this->getResponse()->setTitle(_w('Customers'));


        /*
         * @event backend_customers
         * @return array[string]array $return[%plugin_id%] array of html output
         * @return array[string][string]string $return[%plugin_id%]['sidebar_top_li'] html output
         * @return array[string][string]string $return[%plugin_id%]['sidebar_section'] html output
         */
        $this->view->assign('backend_customers', wa()->event('backend_customers'));
    }
}

