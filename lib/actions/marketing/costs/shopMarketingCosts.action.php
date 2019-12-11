<?php
/**
 * Marketing costs editor
 */
class shopMarketingCostsAction extends shopMarketingViewAction
{
    /**
     * Marketing costs page with chart and expenses table
     */
    public function execute()
    {
        shopReportsSalesAction::jsRedirectIfDisabled();

        list($start_date, $end_date, $group_by, $request_options) = shopReportsSalesAction::getTimeframeParams();

        $limit = $this->getConfig()->getOption('marketing_expenses_per_page');
        $expense_model = new shopExpenseModel();
        $expenses = $expense_model->getList(array(
            'limit' => $limit,
        ));

        $promos = self::getPromos();

        // Data for period bars in table
        foreach($expenses as &$e) {
            $e['start_ts'] = strtotime($e['start']);
            $e['end_ts'] = strtotime($e['end']);
            if ($e['type'] == 'promo') {
                if (isset($promos[$e['name']])) {
                    $e['name'] = $promos[$e['name']]['label'];
                } else {
                    $e['name'] = _w('Deleted promo').' id='.$e['name'];
                }
            }
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
            'storefronts'     => shopReportsSalesAction::getStorefronts(),
            'menu_types'      => shopReportsSalesAction::getMenuTypes(),
            'expense'         => $expense_model->getEmptyRow(),
            'request_options' => $request_options,
            'campaigns'       => self::getCampaigns(),
            'sources'         => self::getSources(),
            'promos'          => $promos,
            'graph_data'      => $graph_data,
            'expenses'        => $expenses,
            'def_cur'         => $def_cur,
            'limit'           => $limit,
            'start'           => 0,
        ));
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

    public static function getSources($additional_source = null, $color = null)
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

        usort($result, array('shopMarketingCostsAction', 'sortHelper'));
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

        usort($result, array('shopMarketingCostsAction', 'sortHelper'));
        return $result;
    }

    public static function getPromos($additional_promo=null, $color=null)
    {
        $result = [];
        $promo_model = new shopPromoModel();
        $promos = $promo_model->getList([
            'with_images' => true,
        ]);
        usort($promos, function($a, $b) {
            return strcmp(mb_strtolower($a['name']), mb_strtolower($b['name']));
        });
        foreach($promos as $p) {
            $result[$p['id']] = [
                'name' => $p['id'],
                'label' => $p['name'],
                'color' => $p['color'],
                'sales' => 0,
            ];
        }

        $sql = "SELECT DISTINCT name, color FROM shop_expense WHERE type='promo' ORDER BY id DESC";
        foreach($promo_model->query($sql) as $row) {
            if (empty($result[$row['name']])) {
                $result[$row['name']] = array(
                    'name' => $row['name'],
                    'label' => _w('Deleted promo').' id='.$row['name'],
                    'color' => $row['color'],
                    'sales' => 0,
                );
            } else {
                $result[$row['name']]['color'] = $row['color'];
            }
        }

        if ($additional_promo && empty($result[$additional_promo])) {
            $result[$additional_promo] = array(
                'name' => $additional_promo,
                'label' => _w('Deleted promo').' id='.$additional_promo,
                'color' => ifempty($color, '#f00'),
                'sales' => 0,
            );
        }

        uasort($result, array('shopMarketingCostsAction', 'sortHelper'));
        return $result;
    }
}

