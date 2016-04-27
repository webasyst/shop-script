<?php

class shopProductsLoadListController extends shopProductListAction
{
    public function execute()
    {
        $config = $this->getConfig();
        $offset = waRequest::get('offset', 0, waRequest::TYPE_INT);
        $total_count = waRequest::get('total_count', 0, waRequest::TYPE_INT);
        $products_per_page = $config->getOption('products_per_page');

        $columns = self::getEnabledColumns();
        $columns[] = 'image';
        $options = array(
            'fields' => '*,'.join(',', $columns),
            'offset' => $offset,
            'products_per_page' => $products_per_page,
            'view' => $this->getProductView()
        );
        $products = $this->getProducts($options);

        $count = count($products);
        if (!$total_count) {
            $total_count = $this->collection->count();
        }

        $this->assign(array(
            'products' => array_values($products),
            'total_count' => $total_count,
            'count' => $count,
            'progress' => array(
                'loaded' => _w('%d product','%d products', $offset + $count),
                'of' => sprintf(_w('of %d'), $total_count),
                'chunk' => _w('%d product','%d products', max(0, min($total_count - ($offset + $count), $count))),
            )
        ));
    }

    private function getProducts($options)
    {
        $fields = $options['fields'];
        if ($options['view'] === 'skus') {
            $fields .= ',skus,stock_counts';
        }
        $products = $this->collection->getProducts($fields, $options['offset'], $options['products_per_page']);
        $this->workupProducts($products);
        $products = array_values($products);
        return $products;
    }

    protected function assign($data)
    {
        $data = parent::preAssign($data);
        echo json_encode(array('status' => 'ok', 'data' => $data));
        exit;
    }

    protected static function getEnabledColumns()
    {
        $cols = waRequest::request('enabled_columns', '', 'string');
        if (!$cols) {
            return array();
        }
        return explode(',', $cols);
    }
}
