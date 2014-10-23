<?php

class shopMyNavAction extends waMyNavAction
{
    public function execute()
    {
        parent::execute();
        /**
         *
         * @event frontend_my_nav
         * @return array[string]string $return[%plugin_id%] html output
         */
        $this->view->assign('frontend_my_nav', wa()->event('frontend_my_nav'));
    }
}