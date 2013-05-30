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


        // save referer
        // @todo: save keywords for referers from search
        if (wa()->getEnv() == 'frontend' && $ref = waRequest::server('HTTP_REFERER')) {
            // check $ref domain
            $ref_parts = parse_url($ref);
            if ($ref_parts['host'] != waRequest::server('HTTP_HOST')) {
                wa()->getStorage()->set('shop/referer', waRequest::server('HTTP_REFERER'));
            }
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
        }

        /**
         * @event frontend_footer
         * @return array[string]string $return[%plugin_id%] html output
         */
        $this->view->assign('frontend_footer', wa()->event('frontend_footer'));

        $this->view->assign('currencies', $this->getConfig()->getCurrencies());
    }
}
