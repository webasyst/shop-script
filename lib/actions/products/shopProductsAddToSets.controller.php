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
        $set_ids = waRequest::post('set_id', array());

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
            $product_ids = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
            if (!$product_ids) {
                return;
            }
            // add just selected products
            $this->set_products_model->add($product_ids, $set_ids);
        } else {
            // add all products of collection with this hash
            $collection = new shopProductsCollection($hash);
            $offset = 0;
            $count = 100;
            $total_count = $collection->count();
            while ($offset < $total_count) {
                $ids = array_keys($collection->getProducts('*', $offset, $count));
                $this->set_products_model->add($ids, $set_ids);
                $offset += count($ids);
            }
        }

        // form a response
        $sets = $this->set_model->getByField('id', $set_ids, 'id');
        if (isset($sets[$new_set_id])) {
            $this->response['new_set'] = $sets[$new_set_id];
            unset($sets[$new_set_id]);
        }
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
            'id'  => $id, 'name' => $name,
        ));
    }
}
