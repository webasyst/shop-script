<?php
/**
 * Sales report data as CSV.
 */
class shopReportsExportsalesController extends waController
{
    public function execute()
    {
        list($start_date, $end_date, $group_by, $request_options) = shopReportsSalesAction::getTimeframeParams();

        $filter = $this->getFilter();

        $model_options = array();
        $type_id = waRequest::request('type', 'sources', 'string');
        $sales_channel = waRequest::request('sales_channel', null, 'string');
        if ($sales_channel) {
            $model_options['sales_channel'] = $sales_channel;
        }

        $sales_model = new shopSalesModel();
        $sales_by_day = $sales_model->getPeriodByDate($type_id, $start_date, $end_date, $model_options + array(
            'date_group' => $group_by,
            'filter' => $filter
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

        $filename = $this->buildFilename(
            array(
                'type_id' => $type_id,
                'sales_channel' => $sales_channel,
                'filter' => $filter,
                'start_date' => $start_date,
                'end_date' => !empty($end_date) ? $end_date : date('Y-m-d')
            )
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

    public function getFilter()
    {
        $filter = (array) $this->getRequest()->request('filter');
        foreach ($filter as $field => $value) {
            $filter[$field] = urldecode($value);
        }
        return $filter;
    }

    public function buildFilename($params = array())
    {
        $sep = '_';

        $template = array(
            'prefix' => 'sales',
            'sales_channel' => '',
            'type_id' => '',
            'filter' => '',
            'start_date' => '',
            'end_date' => ''
        );

        if (!empty($params['sales_channel'])) {
            $params['sales_channel'] = str_replace('storefront:', '', $params['sales_channel']);
        }

        foreach ($template as $placeholder => &$value) {
            if (!empty($params[$placeholder])) {
                $param = $params[$placeholder];
                if (is_array($param)) {
                    ksort($param);
                    $param = join($sep, $param);
                }
                $value = shopHelper::transliterate($param);
                $value = trim(preg_replace('~[^a-z0-9\-]+~i', $sep, $value), $sep);
            }
        }
        unset($value);

        $filename = array();
        foreach ($template as $value) {
            if ($value) {
                $filename[] = $value;
            }
        }

        return join($sep, $filename) . '.csv';
    }
}

