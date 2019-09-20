<?php

class shopMarketingCostRowsAction extends shopMarketingViewAction
{
    public function execute()
    {
        shopReportsSalesAction::jsRedirectIfDisabled();

        $start = waRequest::request('start', 0, 'int');
        $limit = waRequest::request('limit', $this->getConfig()->getOption('marketing_expenses_per_page'), 'int');

        $expense_model = new shopExpenseModel();
        $expenses = $expense_model->getList(array(
            'start' => $start,
            'limit' => $limit,
        ));

        // Data for period bars in table
        foreach ($expenses as &$e) {
            $e['start_ts'] = strtotime($e['start']);
            $e['end_ts'] = strtotime($e['end']);
        }
        unset($e);

        // Update graph unles it's a lazy loading request
        $graph_data = null;
        if (!$start) {
            list($start_date, $end_date, $group_by) = shopReportsSalesAction::getTimeframeParams();
            $graph_data = $expense_model->getChart(array(
                'start_date' => $start_date,
                'end_date'   => $end_date,
                'group_by'   => $group_by,
            ));
        }

        $def_cur = wa()->getConfig()->getCurrency();
        $this->view->assign(array(
            'graph_data' => $graph_data,
            'expenses'   => $expenses,
            'def_cur'    => $def_cur,
            'is_update'  => true,
            'start'      => $start,
            'limit'      => $limit,
        ));
    }
}