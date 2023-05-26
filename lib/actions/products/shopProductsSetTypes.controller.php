<?php

class shopProductsSetTypesController extends waJsonController
{
    /**
     * @var shopTypeModel
     */
    private $type_model;
    /**
     * @var shopProductModel
     */
    private $product_model;

    public function __construct()
    {
        $this->type_model = new shopTypeModel();
        $this->product_model = new shopProductModel();
    }

    public function execute()
    {
        $type_id = $this->getType();
        if (!$type_id) {
            return;
        }

        $hash = shopProductsAddToCategoriesController::getHash(waRequest::TYPE_STRING_TRIM);
        $all_product_ids = null;

        if (!$hash) {
            $all_product_ids = waRequest::post('product_id', array(), waRequest::TYPE_ARRAY_INT);
            $hash = 'id/'.join(',', $all_product_ids);
        }

        /**
         * Attaches a product to the types. Get data before changes
         *
         * @param array[string]int $all_product_ids[%id][id] Product id(s)
         * @param string $type_id product type id
         * @param string $hash Collection Hash
         * @event products_types_set.before
         */
        $params = array(
            'type_id'     => $type_id,
            'products_id' => $all_product_ids,
            'hash'        => $hash,
        );
        wa('shop')->event('products_types_set.before', $params);

        if (substr($hash, 0, 5) != 'type/') {
            $collection = new shopProductsCollection($hash);
            $offset = 0;
            $count = 100;
            $total_count = $collection->count();
            $all_updated_products = [];
            while ($offset < $total_count) {
                $product_ids = array_keys($collection->getProducts('*', $offset, $count));
                if (!$product_ids) {
                    break;
                }
                $filtered = $this->product_model->filterAllowedProductIds($product_ids);
                $this->product_model->updateType($filtered, $type_id);
                shopProdSetTypeController::updateBasePrice($filtered);
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
        } else {
            $this->product_model->changeType(substr($hash, 5), $type_id);
        }

        /**
         * Attaches a product to the types
         *
         * @param array[string]int $all_product_ids[%id][id] Product id(s)
         * @param string $type_id product type id
         * @param hash $hash Collection Hash
         * @event products_types_set.after
         */
        $params = array(
            'type_id'     => $type_id,
            'products_id' => $all_product_ids,
            'hash'        => $hash,
        );
        wa('shop')->event('products_types_set.after', $params);

        $this->response['types'] = $this->type_model->getTypes();

    }

    public function getType()
    {
        $type_id = waRequest::post('type_id', null, waRequest::TYPE_INT);
        if (!$type_id) {
            return null;
        } else {
            $types = shopTypeModel::extractAllowed(array($type_id));
            if (!$types) {
                return null;
            } else {
                return $type_id;
            }
        }
    }
}
