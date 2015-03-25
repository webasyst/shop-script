<?php

class shopFrontendPageAction extends waPageAction
{
    public function execute()
    {
        $this->setLayout(new shopFrontendLayout());
        parent::execute();
    }

    public function display($clear_assign = true)
    {
        /**
         * @event frontend_nav
         * @return array[string]string $return[%plugin_id%] html output for navigation section
         */
        $this->view->assign('frontend_nav', wa()->event('frontend_nav'));

        /**
         * @event frontend_nav_aux
         * @return array[string]string $return[%plugin_id%] html output for navigation section
         */
        $this->view->assign('frontend_nav_aux', wa()->event('frontend_nav_aux'));

        try {
            return parent::display(false);
        } catch (waException $e) {
            if ($e->getCode() == 404) {
                $url = $this->getConfig()->getRequestUrl(false, true);
                if (substr($url, -1) !== '/' && substr($url, -9) !== 'index.php') {
                    $this->redirect($url.'/', 301);
                }
            }
            wa()->event('frontend_error', $e);
            $this->view->assign('error_message', $e->getMessage());
            $code = $e->getCode();
            $this->view->assign('error_code', $code);
            $this->getResponse()->setStatus($code ? $code : 500);
            $this->setThemeTemplate('error.html');
            return $this->view->fetch($this->getTemplate());
        }
    }
}