<?php

class shopLoginAction extends waLoginAction
{

    public function execute()
    {
        $this->setLayout(new shopFrontendLayout());
        $this->setThemeTemplate('login.html');
        try {
            parent::execute();
            $this->saveReferer();
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

    protected function redirectAfterAuth()
    {
        $referer = waRequest::server('HTTP_REFERER');
        $referer = substr($referer, strlen($this->getConfig()->getHostUrl()));
        $checkout_url = wa()->getRouteUrl('shop/frontend/checkout');
        $auth_referer = $this->getStorage()->get('auth_referer');

        if ($referer && !strncasecmp($referer, $checkout_url, strlen($checkout_url))) {
            $url = $referer;
        } else if ($auth_referer) {
            $url = $auth_referer;
        } else {
            $url = $referer;
        }

        // Do not allow to redirect to login URL
        $login_url = wa()->getRouteUrl('/login');
        if (!strncasecmp($url, $login_url, strlen($login_url))) {
            $url = null;
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
