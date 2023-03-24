<?php

class shopProdAddToSetsController extends waJsonController
{
    /**
     * @var shopSetModel
     */
    private $set_model;

    public function execute()
    {
        $this->set_model = new shopSetModel();
        $set_products_model = new shopSetProductsModel();

        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            $this->errors[] = [
                'id' => 'access_denied',
                'text' => _w('Access denied'),
            ];
        }

        $set_ids = waRequest::post('set_ids', []);

        if (!$set_ids) {
            return;
        }

        $product_id = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);
        $all_product_ids = null;
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        $options = [];
        $offset = 0;
        $hash = '';
        if (!$presentation_id) {
            $all_product_ids = $product_id;
            $hash = 'id/'.join(',', $all_product_ids);
        } else {
            $presentation = new shopPresentation($presentation_id, true);
            if ($presentation->getFilterId() > 0) {
                $options['exclude_products'] = $product_id;
                $hash = 'filter/'.$presentation->getFilterId();
            }
            $offset = max(0, waRequest::post('offset', 0, waRequest::TYPE_INT));
        }

        /**
         * Attaches a product to the sets. Get data before changes
         *
         * @param array $set_ids
         * @param string $hash
         * @param array|string $all_product_ids products_id
         *
         * @event products_add_sets.before
         */
        $params = [
            'set_ids'     => $set_ids,
            'hash'        => $hash,
            'products_id' => $all_product_ids,
        ];
        wa('shop')->event('products_add_sets.before', $params);

        $collection = new shopProductsCollection($hash, $options);
        $count = 100;
        $total_count = $collection->count();
        $all_updated_products = [];
        while ($offset < $total_count) {
            $product_ids = array_keys($collection->getProducts('id', $offset, $count));
            if (!$product_ids) {
                break;
            }
            $set_products_model->add($product_ids, $set_ids);
            $all_updated_products = array_merge($all_updated_products, $product_ids);
            $offset += count($product_ids);
        }

        $count_all_updated_products = count($all_updated_products);
        if ($count_all_updated_products > 1) {
            for ($offset = 0; $offset < $count_all_updated_products; $offset += 5000) {
                $part_updated_products = array_slice($all_updated_products, $offset, 5000);
                $this->logAction('products_edit', count($part_updated_products) . '$' . implode(',', $part_updated_products));
            }
        } elseif (isset($all_updated_products[0]) && is_numeric($all_updated_products[0])) {
            $this->logAction('product_edit', $all_updated_products[0]);
        }

        $sets = $this->set_model->getByField('id', $set_ids, 'id');

        /**
         * Attaches a product to the sets
         *
         * @param array $set_ids
         * @param string $hash
         * @param array|string $all_product_ids products_id
         *
         * @event products_add_sets.after
         */
        $params = [
            'set_ids'     => $set_ids,
            'hash'        => $hash,
            'products_id' => $all_product_ids,
        ];
        wa('shop')->event('products_add_sets.after', $params);

        $this->response['sets'] = $sets;
    }
}
