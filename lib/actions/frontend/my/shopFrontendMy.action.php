<?php

/**
 * Welcome page in customer account.
 */
class shopFrontendMyAction extends shopFrontendAction
{
    public function execute()
    {
        /**
         *
         * @event frontend_my
         * @return array[string]string $return[%plugin_id%] html output
         */
        $this->view->assign('frontend_my', wa()->event('frontend_my'));
        // Set up layout and template from theme
        $this->setThemeTemplate('my.html');
        if (!waRequest::isXMLHttpRequest()) {
            $this->setLayout(new shopFrontendLayout());
            $this->getResponse()->setTitle(_w('My account'));
            $this->layout->assign('breadcrumbs', self::getBreadcrumbs());
            $this->layout->assign('nofollow', true);
        }
    }

    public static function getBreadcrumbs()
    {
        return array();
    }
}

