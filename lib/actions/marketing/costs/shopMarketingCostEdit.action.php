<?php

class shopMarketingCostEditAction extends shopMarketingViewAction
{
    public function execute()
    {
        shopReportsSalesAction::jsRedirectIfDisabled();

        $expense_id = waRequest::request('expense_id', '', 'int');

        // Get existing record data from DB
        $expense_model = new shopExpenseModel();
        if ($expense_id) {
            $expense = $expense_model->getById($expense_id);
        }
        if (empty($expense) || !$expense_id) {
            $expense = $expense_model->getEmptyRow();
        }

        // Promo, on the basis of which it is necessary to create a cost
        $promo_id = waRequest::request('promo_id', null, waRequest::TYPE_INT);
        $promo_model = new shopPromoModel();
        $promo = $promo_model->getById($promo_id);

        // Prepare data for template
        $def_cur = wa()->getConfig()->getCurrency();
        $this->view->assign(array(
            'storefronts' => shopReportsSalesAction::getStorefronts(),
            'campaigns'   => shopMarketingCostsAction::getCampaigns($expense['type'] == 'campaign' ? $expense['name'] : null, $expense['color']),
            'sources'     => shopMarketingCostsAction::getSources($expense['type'] == 'source' ? $expense['name'] : null, $expense['color']),
            'promos'      => shopMarketingCostsAction::getPromos($expense['type'] == 'promo' ? $expense['name'] : null, $expense['color']),
            'promo'       => $promo,
            'expense'     => $expense,
            'def_cur'     => $def_cur,
        ));
    }
}