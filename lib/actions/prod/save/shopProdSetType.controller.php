<?php

class shopProdSetTypesController extends waJsonController
{
    /**
     * @var shopTypeModel
     */
    private $type_model;

    public function execute()
    {
        $this->type_model = new shopTypeModel();
        $product_model = new shopProductModel();
        $type_id = $this->getType();

        if (!$type_id) {
            $this->errors[] = [
                'id' => 'not_found',
                'text' => _w('Product type not found.'),
            ];
        }

        $all_product_ids = null;
        $product_id = waRequest::post('product_id', [], waRequest::TYPE_ARRAY_INT);
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
         * Attaches a product to the types. Get data before changes
         *
         * @param array[string]int $all_product_ids[%id][id] Product id(s)
         * @param string $type_id product type id
         * @param string $hash Collection Hash
         * @event products_types_set.before
         */
        $params = [
            'type_id'     => $type_id,
            'products_id' => $all_product_ids,
            'hash'        => $hash,
        ];
        wa('shop')->event('products_types_set.before', $params);

        $collection = new shopProductsCollection($hash, $options);
        $count = 100;
        $total_count = $collection->count();
        $all_updated_products = [];
        while ($offset < $total_count) {
            $product_ids = array_keys($collection->getProducts('id', $offset, $count));
            if (!$product_ids) {
                break;
            }
            $filtered = $product_model->filterAllowedProductIds($product_ids);
            $product_model->updateType($filtered, $type_id);
            $all_updated_products = array_merge($all_updated_products, $product_ids);
            $offset += count($product_ids);
        }
        if (count($all_updated_products) > 1) {
            $this->logAction('products_edit', count($all_updated_products) . '$' . implode(',', $all_updated_products));
        } elseif (isset($all_updated_products[0]) && is_numeric($all_updated_products[0])) {
            $this->logAction('product_edit', $all_updated_products[0]);
        }

        /**
         * Attaches a product to the types
         *
         * @param array[string]int $all_product_ids[%id][id] Product id(s)
         * @param string $type_id product type id
         * @param hash $hash Collection Hash
         * @event products_types_set.after
         */
        $params = [
            'type_id'     => $type_id,
            'products_id' => $all_product_ids,
            'hash'        => $hash,
        ];
        wa('shop')->event('products_types_set.after', $params);

        $this->response['types'] = $this->type_model->getTypes();

    }

    public function getType()
    {
        $type_id = waRequest::post('type_id', null, waRequest::TYPE_INT);
        if (!$type_id) {
            return null;
        } else {
            $types = shopTypeModel::extractAllowed([$type_id]);
            if (!$types) {
                return null;
            } else {
                return $type_id;
            }
        }
    }
}
