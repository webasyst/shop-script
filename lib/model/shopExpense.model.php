<?php
/**
 * Table shop_expense is used to store marketing costs
 * for editor in Reports - Sales section of backend.
 */
class shopExpenseModel extends waModel
{
    protected $table = 'shop_expense';

    public function getEmptyRow()
    {
        $result = parent::getEmptyRow();
        $result['start'] = $result['end'] = date('Y-m-d');
        return $result;
    }

    public function getList($options=array())
    {
        $limit_sql = '';
        if (isset($options['limit'])) {
            $start = (int) ifset($options['start']);
            $limit = (int) ifset($options['limit']);
            if (!$limit) {
                $limit = 25;
            }
            $limit_sql = "LIMIT {$start}, {$limit}";
        }
        $sql = "SELECT *
                FROM {$this->table}
                ORDER BY start DESC
                $limit_sql";
        return $this->query($sql)->fetchAll('id');
    }

    public function getChart($options=array())
    {
        $date_start = ifset($options['start_date']);
        $date_end = ifset($options['end_date']);
        $group_by = ifset($options['group_by']);

        empty($date_end) && ($date_end = date('Y-m-d 23:59:59'));
        if (empty($date_start)) {
            $order_model = new shopOrderModel();
            $date_start = $order_model->getMinDate();
        }
        if ($group_by !== 'months') {
            $group_by = 'days';
        }

        $sql = "SELECT *, DATEDIFF(end, start) + 1 AS days_count
                FROM {$this->table}
                WHERE start <= ?
                    AND end >= ?
                ORDER BY start";
        $expenses = $this->query($sql, $date_end, $date_start)->fetchAll();

        // Loop over all days of a period calculating expense amount for each day or month.
        // Basically, this is a GROUP BY date, type, and name.
        $end_ts = strtotime($date_end);
        $start_ts = strtotime($date_start);
        $data = array(); // date => [name => [type#color => array of data]]
        $result = array();

        for ($t = $start_ts; $t <= $end_ts; $t += 3600*24) {
            if ($group_by == 'days') {
                $date = date('Y-m-d', $t);
            } else {
                $date = date('Y-m-01', $t);
            }

            // Prepare data for marketing costs for this day
            $data[$date] = array();
            foreach($expenses as $i => $e) {
                if (strtotime($e['end']) < $t) {
                    unset($expenses[$i]);
                    continue;
                }
                if (strtotime($e['start']) > $t) {
                    break;
                }

                if ($e['days_count'] > 0) {
                    $serie_id = md5($e['name'].$e['type']);
                    if (empty($data[$date][$serie_id])) {
                        $data[$date][$serie_id] = array(
                            'amount' => 0,
                            'color' => $e['color'],
                        );
                        if (empty($result[$serie_id])) {
                            $result[$serie_id] = array(
                                'label' => '<span class="name">'.htmlspecialchars($e['name']).'</span> <span class="note">'.($e['note'] ? '('.htmlspecialchars($e['note']).')' : '').'</span>',
                                'color' => $e['color'],
                                'name' => $e['name'],
                                'type' => $e['type'],
                                'data' => array(),
                            );
                        }
                    }
                    $amount = $e['amount'] / $e['days_count'];
                    if ($group_by != 'days') {
                        $amount *= date('t', $t); // number of days in given month
                    }
                    $data[$date][$serie_id]['amount'] += $amount;
                }
            }
        }
        if (!$result) {
            // Fake serie
            $result[''] = array(
                'label' => '',
                'color' => 'transparent',
                'name' => '',
                'type' => '',
                'data' => array(),
            );
        }

        $serie_ids = array_keys($result);
        foreach($data as $date => $series) {
            $dt = strtotime($date)*1000;
            foreach($serie_ids as $serie_id) {
                $d = ifset($series[$serie_id]);
                $amount = ifset($d['amount'], 0);
                $result[$serie_id]['data'][] = array(
                    'y' => $amount,
                    'time' => $dt,
                    'header' => self::getCohortHeader($group_by, $dt/1000),
                    'amount_html' => shop_currency($amount),
                    'color' => ifset($d['color'], $result[$serie_id]['color']),
                );
            }
        }

        return array_values($result);
    }

    public static function getCohortHeader($group_by, $reg_ts)
    {
        switch($group_by) {
            case 'quarters':
                return '<span class="nowrap">Q'.(floor((date('n', $reg_ts) - 1) / 3) + 1).'&nbsp;'.date('Y', $reg_ts).'</span>';
            case 'months':
                return _ws(date('F', $reg_ts)).'&nbsp;'.date('Y', $reg_ts);
            case 'weeks':
                $row_header = '<span class="nowrap">'.wa_date('humandate', $reg_ts).' —</span><br>';
                $row_header .= '<span class="nowrap">'.wa_date('humandate', strtotime('+6 days', $reg_ts)).'</span>';
                return $row_header;
            default:
                return '<span class="nowrap">'.wa_date('humandate', $reg_ts).'</span>';
        }
    }
}

