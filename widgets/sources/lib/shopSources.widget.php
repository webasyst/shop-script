<?php

class shopSourcesWidget extends waWidget
{
    const LIMIT = 12;

    public function defaultAction()
    {
        $settings = $this->getSettings();
        $period = (int)$settings['period'];
        $date_end = date('Y-m-d 23:59:59');
        $date_start = date('Y-m-d', strtotime($date_end) - ifempty($period, 30*24*3600));

        $sales_model = new shopSalesModel();
        $table_data = $sales_model->getPeriod('sources', $date_start, $date_end, array(
            'storefront' => $settings['storefront'],
            'order' => '!'.$settings['metric'],
            'limit' => self::LIMIT,
        ));

        $max_metric = 0;
        $def_cur = wa('shop')->getConfig()->getCurrency(true);
        foreach($table_data as &$row) {
            $row['metric'] = $row[$settings['metric']];
            if ($settings['metric'] != 'orders') {
                $row['metric_html'] = shop_currency_html($row['metric'], $def_cur, $def_cur);
            } else {
                $row['metric_html'] = $row['metric'];
            }

            $max_metric = max($max_metric, $row['metric']);
            $row['orig_name'] = $row['name'];
            if (!$row['name']) {
                $row['name'] = _w('(direct)');
            }
        }
        foreach($table_data as &$row) {
            $row['percent'] = round($row['metric'] * 100 / $max_metric, 2);
        }
        unset($row);

        $this->display(array(
            'is_tv' => !!$this->getInfo('dashboard_id'),
            'metric' => $settings['metric'],
            'sources' => $table_data,
            'widget_id' => $this->id,
        ));
    }

    protected function getSettingsConfig()
    {
        $result = parent::getSettingsConfig();
        foreach(shopReportsSalesAction::getStorefronts() as $s) {
            if ($s) {
                $result['storefront']['options'][] = array(
                    'value' => $s,
                    'title' => $s,
                );
            }
        }
        return $result;
    }
}
