<?php
class shopFrontendLayout extends waLayout
{
    public function execute()
    {

        if (wa()->getEnv() == 'frontend' && ($currency = waRequest::get("currency"))) {
            if ($this->getConfig()->getCurrencies(array($currency))) {
                wa()->getStorage()->set('shop/currency', $currency);
                wa()->getStorage()->remove('shop/cart');
            }
            $url = $this->getConfig()->getCurrentUrl();
            $url = preg_replace('/[\?&]currency='.$currency.'/i', '', $url);
            $this->redirect($url);
        }

        $this->view->assign('action', waRequest::param('action', 'default'));
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
