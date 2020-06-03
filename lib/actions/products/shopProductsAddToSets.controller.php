<?php

class shopProductsAddToSetsController extends waJsonController
{
    /**
     * @var shopSetModel
     */
    private $set_model;
    /**
     * @var shopSetProductsModel
     */
    private $set_products_model;


    public function __construct()
    {
        $this->set_model = new shopSetModel();
        $this->set_products_model = new shopSetProductsModel();
    }

    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            throw new waRightsException(_w('Access denied'));
        }

        $set_ids = waRequest::post('set_id', array());
        $all_product_ids = null;

        // create new set
        $new_set_id = null;
        if (waRequest::post('new_set')) {
            $new_set_id = $this->createSet(
                waRequest::post('new_set_name')
            );
            $set_ids[] = $new_set_id;
        }

        if (!$set_ids) {
            return;
        }

        // add products to sets
        $hash = waRequest::post('hash', '');
        if (!$hash) {
            $all_product_ids = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
            $hash = 'id/'.join(',', $all_product_ids);
        }

        /**
         * Attaches a product to the sets. Get data before changes
         *
         * @param int $new_set_id
         * @param array $set_ids with $new_set_id
         * @param string $hash
         * @param array|string products_id
         *
         * @event products_add_sets.before
         */
        $params = array(
            'set_ids'     => $set_ids,
            'new_set_id'  => $new_set_id,
            'hash'        => $hash,
            'products_id' => $all_product_ids,
        );
        wa('shop')->event('products_add_sets.before', $params);

        // add all products of collection with this hash
        $collection = new shopProductsCollection($hash);
        $offset = 0;
        $count = 100;
        $total_count = $collection->count();
        while ($offset < $total_count) {
            $product_ids = array_keys($collection->getProducts('*', $offset, $count));
            $this->set_products_model->add($product_ids, $set_ids);
            $offset += count($product_ids);
            if (!$product_ids) {
                break;
            }
        }

        // form a response
        $sets = $this->set_model->getByField('id', $set_ids, 'id');
        if (isset($sets[$new_set_id])) {
            $this->response['new_set'] = $sets[$new_set_id];
            unset($sets[$new_set_id]);
        }

        /**
         * Attaches a product to the sets
         *
         * @param int $new_set_id
         * @param array $set_ids with $new_set_id
         * @param string $hash
         * @param array|string products_id
         *
         * @event products_add_sets.after
         */
        $params = array(
            'set_ids'     => $set_ids,
            'new_set_id'  => $new_set_id,
            'hash'        => $hash,
            'products_id' => $all_product_ids,
        );
        wa('shop')->event('products_add_sets.after', $params);

        $this->response['sets'] = $sets;
    }

    public function createSet($name)
    {
        $id = str_replace('-', '_', shopHelper::transliterate($name));
        $id = $this->set_model->suggestUniqueId($id);
        if (empty($name)) {
            $name = _w('(no-name)');
        }
        return $this->set_model->add(array(
            'id'   => $id,
            'name' => $name,
        ));
    }
}
