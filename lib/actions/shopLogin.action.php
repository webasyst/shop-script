<?php

class shopLoginAction extends waLoginAction
{

    public function execute()
    {
        $this->setLayout(new shopFrontendLayout());
        $this->setThemeTemplate('login.html');
        try {
            parent::execute();
        } catch (waException $e) {
            if ($e->getCode() == 404) {
                $this->view->assign('error_code', $e->getCode());
                $this->view->assign('error_message', $e->getMessage());
                $this->setThemeTemplate('error.html');
            } else {
                throw $e;
            }
        }
        wa()->getResponse()->setTitle(_w('Login'));
    }

    protected function afterAuth()
    {
        $referer = waRequest::server('HTTP_REFERER');
        $referer = substr($referer, strlen($this->getConfig()->getHostUrl()));
        $checkout_url = wa()->getRouteUrl('shop/frontend/checkout');

        if ($referer && !strncasecmp($referer, $checkout_url, strlen($checkout_url))) {
            $url = $referer;
        } elseif ($referer != wa()->getRouteUrl('/login')) {
            $url = $this->getStorage()->get('auth_referer');
        }
        if (empty($url)) {
            if (waRequest::param('secure')) {
                $url = $this->getConfig()->getCurrentUrl();
            } else {
                $url = wa()->getRouteUrl('shop/frontend/my');
            }
        }
        $this->getStorage()->del('auth_referer');
        $this->getStorage()->del('shop/cart');
        $this->redirect($url);
    }
}