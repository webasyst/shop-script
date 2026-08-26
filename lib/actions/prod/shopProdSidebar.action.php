<?php
/**
 * Sidebar for product editor
 */
class shopProdSidebarAction extends waViewAction
{
    /**
     * shopProdSidebarAction constructor.
     * @param null|array $params
     *      int $params['product_id']
     *      array $params['backend_prod_event'] - result of throwing 'backend_prod' event
     */
    public function __construct($params = null)
    {
        if (!is_array($params)) {
            $params = [];
        }

        $params['id'] = isset($params['id']) && is_scalar($params['id']) ? intval($params['id']) : 0;
        $params['backend_prod_event'] = isset($params['backend_prod_event']) && is_array($params['backend_prod_event']) ? $params['backend_prod_event'] : [];

        parent::__construct($params);
    }

    public function execute()
    {
        $product_id = waRequest::param('id', '', waRequest::TYPE_STRING);
        $action = waRequest::param('action', '', waRequest::TYPE_STRING_TRIM);
        if (wa()->whichUI() == '1.3') {
            $url = wa()->getAppUrl() . shopHelper::getBackendEditorUrl($product_id, $action);
            $this->redirect($url);
            exit;
        }

        $can_edit = true;
        $prices_available = false;
        if (wa_is_int($product_id)) {
            $product_model = new shopProductModel();
            $can_edit = !!$product_model->checkRights($product_id);
        }

        $product = new shopProduct($product_id);
        if ($can_edit) {
            $prices_available = true;
        } else {
            if ($product["id"] && $product["status"] !== "-1") {
                $prices_available = true;
            }
        }

        /**
         * @var shopConfig $config
         */
        $config = $this->getConfig();

        list($report_rights, $sales) = self::getReportsData($product);

        $sales_data = [];
        if ($report_rights) {
            foreach ($sales as $s) {
                $amount = ifset($s, 'sales', null);
                $sales_data[] = ['period' => $s['date'], 'amount' => $amount,];
            }
        }

        $order_model = new shopOrderModel();
        $sales_total = $order_model->getTotalSalesByProduct($product['id'], $product['currency']);

        $this->view->assign([
            'id'               => $product_id,
            'can_edit'         => $can_edit,
            'prices_available' => $prices_available,
            'backend_prod_event' => $this->params['backend_prod_event'],
            'not_found' => (($product_id !== "new") && !$product->getId()),
            'primary_currency' => $config->getCurrency(),
            'report_rights' => $report_rights,
            'sales_data' => $sales_data,
            'total' => $sales_total['total'],
            'sales_quantity' => $sales_total['quantity'],
        ]);
    }

    public static function getReportsData(shopProduct $product)
    {
        $report_rights = wa()->getUser()->getRights('shop', 'reports');
        static $sales_data = [];
        if ($report_rights && !$sales_data) {
            $start_date = date("Y-m-d", strtotime(date('Y-m-d')." -30 day"));
            $sales_data = self::getSalesData($product, $start_date);
        }

        return [$report_rights, $sales_data];
    }

    protected static function getSalesData($product, $start_date)
    {
        $order_model = new shopOrderModel();
        $sales_by_day = $order_model->getSalesByProduct($product['id'], $start_date);

        // Prepare main chart data for template
        $graph_data = array();

        $date = strtotime($start_date);
        while ($date <= time()) {
            $date = date('Y-m-d', $date);
            if (empty($sales_by_day[$date])) {
                $item = ['date' => $date];
            } else {
                $item = $sales_by_day[$date];
            }
            $graph_data[] = $item;
            $date = strtotime($date." +1 day");
        }

        return $graph_data;
    }
}
