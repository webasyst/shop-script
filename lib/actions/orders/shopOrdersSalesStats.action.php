<?php
/**
 * HTML for sales stats block in Point of Sale view of orders list.
 */
class shopOrdersSalesStatsAction extends waViewAction
{
    public function execute()
    {
        $date = $this->getSelectedDate();
        $pos_id = $this->getSelectedPos();
        list($date_start, $date_end) = $this->getDateRangeFromDays($date, 10);

        $points_of_sale = (new shopSalesChannelModel())->getByField('type', 'pos', true);
        if ($pos_id !== null) {
            $poi_ids_str = ['pos:'.$pos_id];
        } else {
            $poi_ids_str = array_map(function($c){
                return 'pos:'.$c['id'];
            }, $points_of_sale);
            $poi_ids_str[] = 'pos:';
        }

        $sales_model = new shopSalesModel();
        $sales_by_payment = $sales_model->getPeriod('payment', $date, $date, [
            'sales_channel' => $poi_ids_str,
        ]);
        $sales_by_currency = $sales_model->getPeriod('currencies', $date, $date, [
            'sales_channel' => $poi_ids_str,
        ]);
        $sales_by_day = $sales_model->getPeriodByDate('currencies', $date_start, $date_end, [
            'sales_channel' => $poi_ids_str,
        ]);

        // shop default currency should be first in list
        $primary_currency = wa('shop')->getConfig()->getCurrency();
        usort($sales_by_currency, function($a, $b) use ($primary_currency) {
            if ($a['name'] == $primary_currency) {
                return -1;
            } else if ($b['name'] == $primary_currency) {
                return 1;
            }
            return 0;
        });

        foreach ($sales_by_day as &$item) {
            $item['total_html'] = wa_currency_html($item['sales'], $primary_currency, '%k{h}');
        }
        unset($item);

        $this->view->assign([
            'date' => $date,
            'date_start' => $date_start,
            'date_end' => $date_end,
            'sales_by_payment' =>$sales_by_payment,
            'sales_by_currency' => $sales_by_currency,
            'sales_by_day' => $sales_by_day,
            'primary_currency' => $primary_currency,
            'points_of_sale' => $points_of_sale,
            'selected_pos_id' => $pos_id,
        ]);
    }

    public function getSelectedDate()
    {
        if (!empty($this->params['date'])) {
            // take date from constructor params when this action is called from shopOrdersAction
            $date = $this->params['date'];
        } else {
            $date = waRequest::request('date', null, 'string');
        }
        if ($date) {
            $date_ts = strtotime($date);
        }
        if (empty($date_ts) || $date_ts > strtotime(date('Y-m-d'))) {
            return date('Y-m-d');
        }
        return date('Y-m-d', $date_ts); // make sure it's yyyy-mm-dd and not something else strtotime() reads
    }

    public function getSelectedPos()
    {
        if (!empty($this->params['viewposid'])) {
            // take date from constructor params when this action is called from shopOrdersAction
            $viewposid = $this->params['viewposid'];
        } else {
            $viewposid = waRequest::request('viewposid', null, 'string');
        }

        if (!isset($viewposid)) {
            return null; // no filtering
        }
        if (!$viewposid || $viewposid < 0) {
            // unspecified point of sale
            return '';
        }
        // point of sale with id
        return (int) $viewposid;
    }

    public function getDateRangeFromDays(string $date, int $range)
    {
        $date_ts = strtotime($date);

        $range_to_subtract = ($range-1)*24*3600;
        $end_ts = strtotime(date('Y-m-d'));
        $start_ts = $end_ts - $range_to_subtract;

        while($date_ts < $start_ts) {
            $end_ts = $start_ts - 24*3600;
            $start_ts = $end_ts - $range_to_subtract;
        }

        return [date('Y-m-d', $start_ts), date('Y-m-d', $end_ts)];
    }
}
