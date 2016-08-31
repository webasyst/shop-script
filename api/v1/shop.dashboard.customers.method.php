<?php

class shopDashboardCustomersMethod extends shopApiMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $period = waRequest::get('period', 30*24*3600, 'int');
        if ($period <= 0) {
            throw new waAPIException('invalid_request', 'period must be a positive integer', 400);
        }
        list($graph_data, $total) = self::getGraphData($period);
        $this->response = array(
            'by_day' => $graph_data,
        );
    }

    protected static function getGraphData($period)
    {
        $date_end = date('Y-m-d 23:59:59');
        $date_start = date('Y-m-d 00:00:00', strtotime($date_end) - $period);

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
        foreach ($rows as $row) {
            $new_customers += $row['customers'];
            $date = str_replace('-', '', $row['date']);
            $graph_data[$date] = array(
                'date' => $date,
                'new' => $row['customers'],
                'total' => $new_customers,
            );
        }

        // Add empty rows
        $empty_row = array(
            'new' => 0,
            'total' => 0,
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
                $empty_row['total'] = ifset($graph_data[$date]['total'], $empty_row['total']);
            }
        }
        ksort($graph_data);

        return array(array_values($graph_data), $new_customers);
    }
}
