<?php

class shopProdProductDuplicateController extends waJsonController
{
    public function execute()
    {
        $product_ids = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        $new_ids = [];
        if (!$presentation_id) {
            if (!$product_ids) {
                $this->errors[] = [
                    'id' => 'not_selected',
                    'text' => _w('No products are selected.'),
                ];
                return;
            }
        } else {
            $presentation = new shopPresentation($presentation_id, true);
            $options = [];
            if ($presentation->getFilterId() > 0) {
                $options['prepare_filter'] = $presentation->getFilterId();
                $options['exclude_products'] = $product_ids;
            }
            $collection = new shopProductsCollection('', $options);
            $product_ids = $presentation->getProducts($collection, [
                'fields' => ['id'],
                'offset' => max(0, waRequest::post('offset', 0, waRequest::TYPE_INT)),
            ]);
            $product_ids = array_keys($product_ids);
        }

        if (!self::checkProductRights($product_ids)) {
            $this->errors[] = [
                'id' => 'access_rights',
                'text' => _w('Insufficient access rights to edit selected products.'),
            ];
            return;
        }

        $this->duplicate($product_ids, $this->errors, $new_ids);
        $count_new_ids = count($new_ids);
        if ($count_new_ids > 1) {
            for ($offset = 0; $offset < $count_new_ids; $offset += 5000) {
                $part_products = array_slice($new_ids, $offset, 5000);
                $this->logAction('products_duplicate', count($part_products) . '$' . implode(',', $part_products));
            }
        } elseif (isset($new_ids[0]) && is_numeric($new_ids[0])) {
            $this->logAction('product_duplicate', $new_ids[0]);
        }

        $this->response['html'] = wa()->getView()->fetch('templates/actions/prod/main/dialogs/products.duplicate_products.html');
    }

    protected function duplicate($product_ids, &$errors, &$new_ids)
    {
        foreach ($product_ids as $id) {
            $product = new shopProduct($id);
            $new_product = $product->duplicate([], $errors);
            if ($new_product) {
                $new_ids[] = $new_product->getId();
            }
        }
    }

    /**
     * @param array $product_ids
     * @return bool
     * @throws waException
     */
    public static function checkProductRights($product_ids)
    {
        if (empty($product_ids) || wa()->getUser()->isAdmin('shop')) {
            return true;
        }

        $product_model = new shopProductModel();
        foreach ($product_ids as $product_id) {
            try {
                if (!$product_model->checkRights($product_id)) {
                    return false;
                }
            } catch (waException $e) {
                return false;
            }
        }

        return true;
    }
}
