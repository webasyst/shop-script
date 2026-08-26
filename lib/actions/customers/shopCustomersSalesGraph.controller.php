<?php

class shopCustomersSalesGraphController extends waJsonController
{
    private const DEFAULT_TIMEFRAME = '30';

    public function execute()
    {
        if (!$this->getRights('reports')) {
            throw new waRightsException(_w("Access denied"));
        }

        $contact_id = waRequest::request('contact_id');
        $timeframe = waRequest::request('timeframe', self::DEFAULT_TIMEFRAME);

        $this->response = [
            'timeframe' => $timeframe,
            'graph_data' => $this->getData($contact_id),
        ];
    }

    private function getData(string $contact_id)
    {
        // Get parameters from GET/POST
        list($start_date, $end_date, $group_by) = self::getTimeframeParams();

        $sales_model = new shopSalesModel();

        $options = array(
            'date_group' => $group_by,
            'contact_id' => $contact_id,
        );
        $type_id = 'sources';
        $graph_data = self::getGraphData($sales_model->getPeriodByDate($type_id, $start_date, $end_date, $options));

        return $graph_data;
    }

    public static function getGraphData($sales_by_day)
    {
        $graph_data = array();
        foreach ($sales_by_day as &$d) {
            $graph_row = array(
                'date' => str_replace('-', '', $d['date'])
            );
            $graph_row['sales'] = ifset($d['sales'], 0);
            $graph_row['profit'] = ifset($d['profit'], 0);
            $graph_row['loss'] = ifset($d['profit'], 0); // profit can be negative; it renders as a red loss below zero
            $graph_data[] = $graph_row;
        }
        unset($d);
        return $graph_data;
    }

    public static function getTimeframeParams()
    {
        $request_options = array();

        $timeframe = waRequest::request('timeframe', self::DEFAULT_TIMEFRAME);
        $request_options['timeframe'] = $timeframe;
        if ($timeframe === 'all') {
            $start_date = null;
            $end_date = null;
        } elseif ($timeframe == 'custom') {
            $from = waRequest::request('from', 0);
            $from_timestamp = strtotime($from . ' 00:00:00');
            if ($from_timestamp) {
                $from = $from_timestamp;
            }
            $start_date = $from ? date('Y-m-d', $from) : null;

            $to = waRequest::request('to', 0);
            $to_timestamp = strtotime($to . ' 23:59:59');
            if ($to_timestamp) {
                $to = $to_timestamp;
            }
            $end_date = $to ? date('Y-m-d', $to) : null;

            $from && ($request_options['from'] = $from);
            $to && ($request_options['to'] = $to);
        } else {
            if (!wa_is_int($timeframe)) {
                $timeframe = 30;
            }
            $start_date = date('Y-m-d', time() - $timeframe * 24 * 3600);
            $end_date = null;
        }

        $group_by = waRequest::request('groupby', 'days');
        if ($group_by !== 'months') {
            $group_by = 'days';
        } else {
            $request_options['groupby'] = $group_by;
        }

        return array($start_date, $end_date, $group_by, $request_options);
    }
}
