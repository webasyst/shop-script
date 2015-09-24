<?php

class shopCustomersWidget extends waWidget
{
    public function defaultAction()
    {
        $settings = $this->getSettings();
        $period = (int) $settings['period'];
        $date_end = date('Y-m-d 23:59:59');
        $date_start = date('Y-m-d', strtotime($date_end) - ifempty($period, 30*24*3600));

        if (true || $settings['metric'] == 'total') {
            $this->displayOneLine($settings, $date_start, $date_end);
        } else {
            $this->displayPie($settings, $date_start, $date_end); // !!!
        }
    }

    protected function displayPie($settings, $date_start, $date_end)
    {
        $this->display(array(
            'widget_id' => $this->id,
            'widget_url' => $this->getStaticUrl(),
        ), $this->getTemplatePath('Pie.html'));
    }

    protected function displayOneLine($settings, $date_start, $date_end)
    {
        $m = new waModel();
        $sql = "SELECT COUNT(DISTINCT contact_id) FROM `shop_order`";
        $total_customers = $m->query($sql)->fetchField();

        list($graph_data, $new_customers) = self::getGraphData($date_start, $date_end, $settings);

        $this->display(array(
            'widget_id' => $this->id,
            'total_customers' => $total_customers,
            'widget_url' => $this->getStaticUrl(),
            'new_customers' => $new_customers,
            'graph_data' => $graph_data,
        ), $this->getTemplatePath('OneLine.html'));
    }

    protected static function getGraphData($date_start, $date_end, $settings)
    {
        $m = new waModel();
        $sql = "SELECT DATE(c.create_datetime) AS `date`, COUNT(*) AS customers
                FROM wa_contact AS c
                    JOIN shop_customer AS sc
                        ON c.id=sc.contact_id
                WHERE c.create_datetime >= ?
                    AND c.create_datetime <= ?
                GROUP BY `date`
                ORDER BY `date`";
        $rows = $m->query($sql, $date_start, $date_end);

        $new_customers = 0;
        $graph_data = array();
        foreach($rows as $row) {
            $new_customers += $row['customers'];
            $date = str_replace('-', '', $row['date']);
            $graph_data[$date] = array(
                'date' => $date,
                'customers' => $new_customers,
            );
        }

        // Add empty rows
        $empty_row = array(
            'customers' => 0,
        );
        $end_ts = strtotime($date_end);
        $start_ts = strtotime($date_start);
        for ($t = $start_ts; $t <= $end_ts; $t += 3600*24) {
            $date = date('Ymd', $t);
            if (empty($graph_data[$date])) {
                $graph_data[$date] = array(
                    'date' => $date,
                ) + $empty_row;
            } else {
                foreach($empty_row as $k => $v) {
                    $empty_row[$k] = $graph_data[$date][$k] = (int) $graph_data[$date][$k];
                }
            }
        }
        ksort($graph_data);

        return array(array_values($graph_data), $new_customers);
    }
}