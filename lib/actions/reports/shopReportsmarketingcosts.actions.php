<?php
/**
 * Marketing costs editor
 */
class shopReportsmarketingcostsActions extends waViewActions
{
    /**
     * Marketing costs page with chart and expenses table
     */
    public function defaultAction()
    {
        shopReportsSalesAction::jsRedirectIfDisabled();

        list($start_date, $end_date, $group_by, $request_options) = shopReportsSalesAction::getTimeframeParams();

        $limit = $this->getConfig()->getOption('marketing_expenses_per_page');
        $expense_model = new shopExpenseModel();
        $expenses = $expense_model->getList(array(
            'limit' => $limit,
        ));

        // Data for period bars in table
        foreach($expenses as &$e) {
            $e['start_ts'] = strtotime($e['start']);
            $e['end_ts'] = strtotime($e['end']);
        }
        unset($e);

        // Data for the chart
        $graph_data = $expense_model->getChart(array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'group_by' => $group_by,
        ));

        $def_cur = wa()->getConfig()->getCurrency();
        $this->view->assign(array(
            'storefronts' => shopReportsSalesAction::getStorefronts(),
            'menu_types' => shopReportsSalesAction::getMenuTypes(),
            'expense' => $expense_model->getEmptyRow(),
            'request_options' => $request_options,
            'campaigns' => self::getCampaigns(),
            'sources' => self::getSources(),
            'graph_data' => $graph_data,
            'expenses' => $expenses,
            'def_cur' => $def_cur,
            'limit' => $limit,
            'start' => 0,
        ));
        $this->setTemplate('templates/actions/reports/ReportsMarketingcosts.html');
    }

    /**
     * Renders HTML block for a single expense editor,
     * and accepts submit from the form it renders.
     */
    protected function editorAction()
    {
        shopReportsSalesAction::jsRedirectIfDisabled();

        $expense_id = waRequest::request('expense_id', '', 'int');

        // Get existing record data from DB
        $expense_model = new shopExpenseModel();
        if ($expense_id) {
            $expense = $expense_model->getById($expense_id);
        }
        if (empty($expense) || !$expense_id) {
            $expense_id = '';
            $expense = $expense_model->getEmptyRow();
        }

        // Handle validation and saving if data came via POST
        $errors = array();
        unset($expense['id']);
        if (waRequest::post()) {
            $expense = array_intersect_key(waRequest::post('expense', array(), 'array') + $expense, $expense);

            if (waRequest::post('expense_period_type') == 'timeframe') {
                $expense['start'] = waRequest::post('expense_period_from', '', 'string');
                $expense['end'] = waRequest::post('expense_period_to', '', 'string');
                if (empty($expense['start']) && empty($expense['end'])) {
                    $errors['expense_period_single'] = _w('This field is required.');
                } else {
                    if (empty($expense['start'])) {
                        $errors['expense_period_from'] = _w('This field is required.');
                    } else if (!strtotime($expense['start'])) {
                        $errors['expense_period_from'] = _w('Incorrect format.');
                    }
                    if (empty($expense['end'])) {
                        $errors['expense_period_to'] = _w('This field is required.');
                    } else if (!strtotime($expense['end'])) {
                        $errors['expense_period_to'] = _w('Incorrect format.');
                    }
                    if (strtotime($expense['start']) > strtotime($expense['end'])) {
                        list($expense['start'], $expense['end']) = array($expense['end'], $expense['start']);
                    }
                }
            } else {
                $expense['start'] = $expense['end'] = waRequest::post('expense_period_single', '', 'string');
                if (empty($expense['start'])) {
                    $errors['expense_period_single'] = _w('This field is required.');
                } else if (!strtotime($expense['start'])) {
                    $errors['expense_period_single'] = _w('Incorrect format.');
                }
            }

            if (empty($expense['amount'])) {
                $errors['expense[amount]'] = _w('This field is required.');
            } else if (!is_numeric($expense['amount'])) {
                $errors['expense[amount]'] = _w('Incorrect format.');
            }

            if (empty($expense['type'])) {
                $errors['channel_selector'] = _w('This field is required.');
            } else if (empty($expense['name'])) {
                $errors['expense[name]'] = _w('This field is required.');
            }

            if (empty($expense['color'])) {
                $expense['color'] = '#f00';
            }

            if (!$errors) {
                if ($expense_id) {
                    $expense_model->updateById($expense_id, $expense);
                } else {
                    $expense_id = $expense_model->insert($expense);
                }

                // Clear sales chart cache for the period
                $sales_model = new shopSalesModel();
                $sales_model->deletePeriod($expense['start'], $expense['end']);
            }
        }

        $expense['id'] = $expense_id;

        // Prepare data for template
        $def_cur = wa()->getConfig()->getCurrency();
        $this->view->assign(array(
            'storefronts' => shopReportsSalesAction::getStorefronts(),
            'campaigns' => self::getCampaigns($expense['type'] == 'campaign' ? $expense['name'] : null, $expense['color']),
            'sources' => self::getSources($expense['type'] == 'source' ? $expense['name'] : null, $expense['color']),
            'expense' => $expense,
            'def_cur' => $def_cur,
            'errors' => $errors,
        ));

        $this->setTemplate('templates/actions/reports/mcosts_editor.html');
    }

    /** List of expenses for lazy-loading and to reload table after successfull save */
    protected function rowsAction()
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
        foreach($expenses as &$e) {
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
                'end_date' => $end_date,
                'group_by' => $group_by,
            ));
        }

        $def_cur = wa()->getConfig()->getCurrency();
        $this->view->assign(array(
            'graph_data' => $graph_data,
            'expenses' => $expenses,
            'def_cur' => $def_cur,
            'is_update' => true,
            'start' => $start,
            'limit' => $limit,
        ));
        $this->setTemplate('templates/actions/reports/mcosts_rows.html');
    }

    /** Delete expense by id */
    protected function deleteAction()
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
        exit;
    }

    public static function getSources($additional_source=null, $color=null)
    {
        $traffic_sources = wa('shop')->getConfig()->getOption('traffic_sources');

        $m = new waModel();
        $sql = "SELECT op.value, SUM(o.total*o.rate) AS total_sales
                FROM shop_order_params AS op
                    JOIN shop_order AS o
                        ON o.id=op.order_id
                WHERE name='referer_host'
                GROUP BY op.value
                ORDER BY total_sales DESC, value";
        $result = array();
        foreach($m->query($sql) as $row) {
            if(empty($row['value'])) {
                continue;
            }
            if (!empty($traffic_sources[$row['value']]['color'])) {
                $color = $traffic_sources[$row['value']]['color'];
            } else {
                $color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
            }
            $result[$row['value']] = array(
                'name' => $row['value'],
                'label' => $row['value'].' ('.shop_currency($row['total_sales']).')',
                'color' => $color,
                'sales' => $row['total_sales'],
            );
        }

        if ($additional_source && empty($result[$additional_source])) {
            $result[$additional_source] = array(
                'name' => $additional_source,
                'label' => $additional_source,
                'color' => ifempty($color, sprintf('#%06X', mt_rand(0, 0xFFFFFF))),
                'sales' => 0,
            );
        }

        $sql = "SELECT DISTINCT name, color FROM shop_expense WHERE type='source' ORDER BY id DESC";
        foreach($m->query($sql) as $row) {
            if (empty($result[$row['name']])) {
                $result[$row['name']] = array(
                    'name' => $row['name'],
                    'label' => $row['name'],
                    'color' => $row['color'],
                    'sales' => 0,
                );
            } else {
                $result[$row['name']]['color'] = $row['color'];
            }
        }

        usort($result, array('shopReportsmarketingcostsActions', 'sortHelper'));
        return $result;
    }

    public static function sortHelper($a, $b) {
        if ($a['sales'] > $b['sales']) {
            return -1;
        }
        if ($a['sales'] < $b['sales']) {
            return 1;
        }
        return strcmp($a['name'], $b['name']);
    }

    public static function getCampaigns($additional_campaign=null, $color=null)
    {
        $m = new waModel();
        $sql = "SELECT op.value, SUM(o.total*o.rate) AS total_sales
                FROM shop_order_params AS op
                    JOIN shop_order AS o
                        ON o.id=op.order_id
                WHERE name='utm_campaign'
                GROUP BY op.value
                ORDER BY total_sales DESC, value";
        $result = array();
        foreach($m->query($sql) as $row) {
            $result[$row['value']] = array(
                'name' => $row['value'],
                'label' => $row['value'].' ('.shop_currency($row['total_sales']).')',
                'color' => '#f00',
                'sales' => $row['total_sales'],
            );
        }

        if ($additional_campaign && empty($result[$additional_campaign])) {
            $result[$additional_campaign] = array(
                'name' => $additional_campaign,
                'label' => $additional_campaign,
                'color' => ifempty($color, '#f00'),
                'sales' => 0,
            );
        }

        $sql = "SELECT DISTINCT name, color FROM shop_expense WHERE type='campaign' ORDER BY id DESC";
        foreach($m->query($sql) as $row) {
            if (empty($result[$row['name']])) {
                $result[$row['name']] = array(
                    'name' => $row['name'],
                    'label' => $row['name'],
                    'color' => $row['color'],
                    'sales' => 0,
                );
            } else {
                $result[$row['name']]['color'] = $row['color'];
            }
        }

        usort($result, array('shopReportsmarketingcostsActions', 'sortHelper'));
        return $result;
    }
}

