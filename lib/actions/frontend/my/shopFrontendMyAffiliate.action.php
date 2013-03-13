<?php

/**
 * Affiliation program history in customer account.
 */
class shopFrontendMyAffiliateAction extends shopFrontendAction
{
    public function execute()
    {
        if (!shopAffiliate::isEnabled()) {
            throw new waException(_w('Unknown page'), 404);
        }

        $scm = new shopCustomerModel();
        $customer = $scm->getById(wa()->getUser()->getId());

        $atm = new shopAffiliateTransactionModel();
        $affiliate_history = $atm->getByContact(wa()->getUser()->getId());

        $this->view->assign('customer', $customer);
        $this->view->assign('affiliate_history', $affiliate_history);

        // Set up layout and template from theme
        $this->setThemeTemplate('my.affiliate.html');
        if (!waRequest::isXMLHttpRequest()) {
            $this->setLayout(new shopFrontendLayout());
            $this->getResponse()->setTitle(_w('Affiliate program'));
            $this->layout->assign('breadcrumbs', self::getBreadcrumbs());
            $this->layout->assign('nofollow', true);
        }
    }

    public static function getBreadcrumbs()
    {
        return array(
            array(
                'name' => _w('My account'),
                'url' => wa()->getRouteUrl('/frontend/my'),
            ),
        );
    }
}

