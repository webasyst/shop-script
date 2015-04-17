<?php
/**
 * Sales report data as CSV.
 */
class shopReportsExportsalesController extends waController
{
    public function execute()
    {
        list($start_date, $end_date, $group_by, $request_options) = shopReportsSalesAction::getTimeframeParams();

        $model_options = array();
        $type_id = waRequest::request('type', 'sources', 'string');
        $storefront = waRequest::request('storefront', null, 'string');
        if ($storefront) {
            $model_options['storefront'] = $storefront;
        }

        $sales_model = new shopSalesModel();
        $sales_by_day = $sales_model->getPeriodByDate($type_id, $start_date, $end_date, $model_options + array(
            'date_group' => $group_by,
        ));

        $result = array(_w('Date').','._w('Sales').','._w('Profit'));
        foreach($sales_by_day as $d) {
            $result[] = join(',', array(
                $d['date'],
                str_replace(',', '.', round($d['sales'], 2)),
                str_replace(',', '.', round($d['profit'], 2)),
            ));
        }
        $result = join("\n", $result);

        $filename = sprintf('sales_%s_%s_%s.csv',
            trim(preg_replace('~[^a-z]+~i', '_', ifempty($storefront, 'all')), '_'),
            $start_date,
            ifempty($end_date, date('Y-m-d'))
        );

        $response = wa()->getResponse();
        $response->setStatus(200);
        $response->addHeader("Content-Length", mb_strlen($result));
        $response->addHeader("Cache-Control", "no-cache, must-revalidate");
        $response->addHeader("Content-type", "text/csv; charset=utf-8");
        $response->addHeader("Content-Disposition", "attachment; filename=\"{$filename}\"");
        $response->addHeader("Last-Modified", strtotime($end_date));
        $response->sendHeaders();
        echo $result;
        exit;
    }
}

