<?php
class shopFrontendLayout extends waLayout
{
    public function execute()
    {
        if (wa()->getEnv() == 'frontend') {
            $redirect_url = null;
            $currency = waRequest::get('currency', null, 'string');
            if ($currency) {
                if ($this->getConfig()->getCurrencies(array($currency))) {
                    wa()->getStorage()->set('shop/currency', $currency);
                    wa()->getStorage()->remove('shop/cart');
                }
                $redirect_url = $this->getConfig()->getCurrentUrl();
                $redirect_url = preg_replace('~&?currency='.$currency.'~i', '', $redirect_url);
            }
            $locale = waRequest::get('locale', null, 'string');
            if ($locale && waLocale::getInfo($locale)) {
                if (!$redirect_url) {
                    $redirect_url = $this->getConfig()->getCurrentUrl();
                }
                wa()->getStorage()->set('locale', $locale);
                $redirect_url = preg_replace('~&?locale='.$locale.'~i', '', $redirect_url);
            }

            if ($redirect_url) {
                $this->redirect(rtrim($redirect_url, '?&'));
            }
        }

        $action = waRequest::param('action', 'default');
        $this->view->assign('action', ifempty($action, 'default'));
        $this->setThemeTemplate('index.html');

        /**
         * @event frontend_head
         * @return array[string]string $return[%plugin_id%] html output
         */
        $this->view->assign('frontend_head', wa()->event('frontend_head'));

        /**
         * @event frontend_header
         * @return array[string]string $return[%plugin_id%] html output
         */
        $this->view->assign('frontend_header', wa()->event('frontend_header'));

        if (!$this->view->getVars('frontend_nav')) {
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
        }

        /**
         * @event frontend_footer
         * @return array[string]string $return[%plugin_id%] html output
         */
        $this->view->assign('frontend_footer', wa()->event('frontend_footer'));

        $this->view->assign('currencies', $this->getConfig()->getCurrencies());

        // set globals
        $params = waRequest::param();
        foreach ($params as $k => $v) {
            if (in_array($k, array('url', 'module', 'action', 'meta_keywords', 'meta_description', 'private',
                'url_type', 'type_id', 'payment_id', 'shipping_id', 'currency', 'stock_id'))) {
                unset($params[$k]);
            }
        }
        $this->view->getHelper()->globals($params);
    }
}
