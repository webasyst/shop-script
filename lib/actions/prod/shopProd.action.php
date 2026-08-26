<?php
/**
 * /products/<id>/
 * Redirects to default tab.
 */
class shopProdAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::param('id', '', waRequest::TYPE_STRING);

        $product = new shopProduct($product_id, [
            'format_fractional_values' => true,
        ]);

        if (!$product->id) {
            if ($product_id == 'new') {
                $product->name = '';
                $product->id = 'new';
                $product->status = 1;
            } else {
                throw new waException(_w('Product not found.'), 404);
            }
        }

        /**
         * @var shopConfig $config
         */
        $config = $this->getConfig();

        if (intval($product->id)) {
            $this->view->assign('edit_rights', $product->checkRights());
            $this->view->assign('can_delete',  $product->checkRights(array('level' => 'delete')));
        } else {
            $this->view->assign('edit_rights', true);
            $this->view->assign('can_delete', false);

            $product['skus'] = array(
                '-1' => array(
                    'id'             => -1,
                    'sku'            => '',
                    'available'      => 1,
                    'status'         => 1,
                    'name'           => '',
                    'price'          => 0.0,
                    'purchase_price' => 0.0,
                    'compare_price'  => 0.0,
                    'count'          => null,
                    'stock'          => array(),
                    'virtual'        => 0
                ),
            );
            $product->currency = $config->getCurrency();
        }

        list($report_rights, $sales) = shopProdSidebarAction::getReportsData($product);

        $sales_data = [];
        foreach ($sales as $s) {
            $sales = ifset($s, 'sales', 0);
            $profit = $sales - ifset($s, 'purchase', 0);
            $sales_data[] = [
                'date' => str_replace('-', '', $s['date']),
                'sales' => $sales,
                'profit' => $profit,
                'loss' => $profit,
            ];
        }

        $this->view->assign([
            'product_id' => $product_id,
            'product' => $product,
            'primary_currency' => $config->getCurrency(),
            'report_rights' => $report_rights,
            'sales_data' => $sales_data,
        ] );

        $this->setLayout(new shopBackendProductsEditSectionLayout([
            'product' => $product,
            'content_id' => 'summary',
        ]));
    }
}
