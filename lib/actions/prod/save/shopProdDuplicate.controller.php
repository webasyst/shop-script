<?php

class shopProdDuplicateController extends waJsonController
{
    public function execute()
    {
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        $new_ids = [];
        if (!$presentation_id) {
            $product_ids = waRequest::post('product_id', [], waRequest::TYPE_ARRAY_INT);
            if (!$product_ids) {
                $this->errors[] = [
                    'id' => 'not_selected',
                    'text' => _w('Товары не выбраны'),
                ];
                return;
            }
        } else {
            $presentation = new shopPresentation($presentation_id, true);
            $options = [];
            if ($presentation->getFilterId() > 0) {
                $options['prepare_filter_id'] = $presentation->getFilterId();
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
        if (count($new_ids) > 1) {
            $this->logAction('products_duplicate', count($new_ids) . '$' . implode(',', $new_ids));
        } elseif (isset($new_ids[0]) && is_numeric($new_ids[0])) {
            $this->logAction('product_duplicate', $new_ids[0]);
        }
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
