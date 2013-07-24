<?php

class shopProductsLoadListController extends shopProductListAction
{
    public function execute()
    {
        $config = $this->getConfig();
        $offset = waRequest::get('offset', 0, waRequest::TYPE_INT);
        $total_count = waRequest::get('total_count', 0, waRequest::TYPE_INT);
        $products_per_page = $config->getOption('products_per_page');

        $products = $this->collection->getProducts('*, image', $offset, $products_per_page);
        $this->workupProducts($products);

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

    protected function assign($data)
    {
        $data = parent::preAssign($data);
        echo json_encode(array('status' => 'ok', 'data' => $data));
        exit;
    }
}