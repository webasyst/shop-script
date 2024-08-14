<?php

class shopProductSalesMethod extends shopApiMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $product_id = (int) $this->get('id');
        $product_model = new shopProductModel();
        $product = $product_model->getById($product_id);
        if (!$product) {
            throw new waAPIException('invalid_param', _w('Product not found.'), 404);
        }

        $order_model = new shopOrderModel();
        $sales_total = $order_model->getTotalSalesByProduct($product['id'], $product['currency']);
        $sales_total['profit'] = $sales_total['total'] - $sales_total['purchase'];
        $sales_total['sales'] = $sales_total['total'];
        unset($sales_total['total']);

        $date = time() - 30*24*3600;
        $sales_data = $order_model->getSalesByProduct($product['id'], date('Y-m-d', $date));
        $sales_by_day = array();
        $empty_row = array_fill_keys(array_keys($sales_total), 0);
        for ($i = 0; $i <= 30; $i++) {
            $date = date('Y-m-d', $date);
            if (empty($sales_data[$date])) {
                $sales_by_day[$date] = array(
                    'date' => $date,
                ) + $empty_row;
            } else {
                $sales_by_day[$date] = $sales_data[$date];
                $sales_by_day[$date]['quantity'] = (int)$sales_by_day[$date]['quantity'];
                $sales_by_day[$date]['discount'] = (float)$sales_by_day[$date]['discount'];
                $sales_by_day[$date]['purchase'] = (float)$sales_by_day[$date]['purchase'];
                $sales_by_day[$date]['subtotal'] = (float)$sales_by_day[$date]['subtotal_sales'];
                $sales_by_day[$date]['profit'] = (float)($sales_by_day[$date]['sales'] - $sales_by_day[$date]['purchase']);
                unset($sales_by_day[$date]['subtotal_sales']);
            }
            $date = strtotime($date." +1 day");
        }

        $this->response['total'] = $sales_total;
        $this->response['days'] = array_values($sales_by_day);
    }
}
