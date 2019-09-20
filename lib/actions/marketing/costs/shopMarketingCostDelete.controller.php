<?php

class shopMarketingCostDeleteController extends waJsonController
{
    public function execute()
    {
        shopReportsSalesAction::jsRedirectIfDisabled();

        $expense_id = waRequest::request('expense_id', '', 'int');
        if ($expense_id) {
            $expense_model = new shopExpenseModel();
            $expense = $expense_model->getById($expense_id);

            if ($expense) {
                // Clear sales chart cache for the period
                $sales_model = new shopSalesModel();
                $sales_model->deletePeriod($expense['start'], $expense['end']);

                $expense_model->deleteById($expense_id);
            }
        }
    }
}