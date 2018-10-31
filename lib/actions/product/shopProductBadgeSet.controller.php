<?php

class shopProductBadgeSetController extends waJsonController
{
    public function execute()
    {
        $code = waRequest::post('code', null, waRequest::TYPE_STRING_TRIM);
        if (!$code) {
            throw new waException(_w("Empty code"));
        }

        $product_model = new shopProductModel();

        $hash = waRequest::post('hash', '', 'string');
        $products_id = null;

        if (!$hash) {
            $products_id = waRequest::request('product_id', array(), waRequest::TYPE_ARRAY_INT);
            if (!$products_id) {
                $products_id = waRequest::get('id', array(), waRequest::TYPE_ARRAY_INT);
            }

            $product = $product_model->getById($products_id);

            if (!$product) {
                throw new waException(_w("Unknown product"));
            }
            if (!$product_model->checkRights(reset($product))) {
                throw new waException(_w("Access denied"));
            }
            $hash = 'id/'.join(',', $products_id);
        }

        /**
         * Attaches stickers to products in bulk and single edits. Get data before changes
         *
         * @param array|string $products_id Products id(s)
         * @param string $code Badge code
         * @param string $hash Collection Hash
         *
         * @event product_badge_set.before
         */
        $params = array(
            'code'        => $code,
            'products_id' => $products_id,
            'hash'        => $hash,
        );
        wa('shop')->event('product_badge_set.before', $params);

        $offset = 0;
        $count = 100;
        $collection = new shopProductsCollection($hash);
        $total_count = $collection->count();
        while ($offset < $total_count) {
            $products_id = array_keys($collection->getProducts('*', $offset, $count));
            // !!! check access rights?..
            $product_model->updateById($products_id, array('badge' => $code));
            $offset += count($products_id);
        }

        /**
         * Attaches stickers to products in bulk and single edits
         *
         * @param array|string $products_id Products id(s)
         * @param string $code Badge code
         * @param string $hash Collection Hash
         *
         * @event product_badge_set.after
         */
        $params = array(
            'code'        => $code,
            'products_id' => $products_id,
            'hash'        => $hash,
        );
        wa('shop')->event('product_badge_set.after', $params);

        $badges = shopProductModel::badges();
        $this->response = isset($badges[$code]) ? $badges[$code]['code'] : $code;
    }
}
