<?php

class shopProdRemoveTagsDialogAction extends waViewAction
{
    public function execute()
    {
        $product_ids = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        if ($presentation_id) {
            $presentation = new shopPresentation($presentation_id, true);
            $options = [];
            if ($presentation->getFilterId() > 0) {
                $options['prepare_filter'] = $presentation->getFilterId();
                $options['exclude_products'] = $product_ids;
            }
            $collection = new shopProductsCollection('', $options);
            $all_product_ids = $presentation->getProducts($collection, [
                'fields' => ['id'],
                'offset' => max(0, waRequest::post('offset', 0, waRequest::TYPE_INT)),
            ]);
            $product_ids = array_keys($all_product_ids);
        }

        $this->view->assign([
            'tags' => $this->getTags($product_ids)
        ]);

        $this->setTemplate('templates/actions/prod/main/dialogs/products.remove_tags.html');
    }

    /**
     * @param $product_ids
     * @return array
     */
    protected function getTags($product_ids)
    {
        $tags = [];
        if ($product_ids) {
            $product_tags_model = new shopProductTagsModel();
            $used_tags = $product_tags_model->getTags($product_ids);
            foreach ($used_tags as $tag_id => $tag_name) {
                $tags[] = [
                    'name' => $tag_name,
                    'value' => $tag_id,
                ];
            }
        }

        return $tags;
    }
}
