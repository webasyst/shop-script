<?php

class shopProdRemoveTagsController extends waJsonController
{
    public function execute()
    {
        $product_ids = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);
        $tag_ids = waRequest::post('tag_ids', [], waRequest::TYPE_ARRAY_INT);
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        $this->validateData($product_ids, $tag_ids, $presentation_id);
        if (!$this->errors) {
            if ($presentation_id) {
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

            if ($product_ids) {
                $this->exclude($product_ids, $tag_ids);
            }

            $this->response = [
                'product_ids' => $product_ids,
                'tags' => $tag_ids
            ];
        }
    }

    protected function validateData($product_ids, $tag_ids, $presentation_id)
    {
        if (!$tag_ids) {
            $this->errors = [
                'id' => 'empty_tags',
                'text' => _w('Product tags not found.')
            ];
        } elseif (!$presentation_id && !$product_ids) {
            $this->errors = [
                'id' => 'empty_products',
                'text' => _w('Products not specified.')
            ];
        }
    }

    protected function exclude($product_ids, $tag_ids)
    {
        $product_tags_model = new shopProductTagsModel();
        $product_tags_model->delete($product_ids, $tag_ids);
        $tag_model = new shopTagModel();
        $tag_model->recount($tag_ids);
        // clear cache
        if ($cache = wa('shop')->getCache()) {
            $cache->delete('tags');
        }
    }
}
