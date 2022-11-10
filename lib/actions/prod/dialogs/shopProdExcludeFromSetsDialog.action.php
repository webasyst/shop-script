<?php

class shopProdExcludeFromSetsDialogAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            throw new waRightsException(_w('Access denied'));
        }

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
            'items' => $this->getItems($product_ids),
        ]);

        $this->setTemplate('templates/actions/prod/main/dialogs/products.exclude_from_sets.html');
    }

    /**
     * @param $product_ids
     * @return array
     * @throws waException
     */
    protected function getItems($product_ids)
    {
        $items = [];
        if ($product_ids) {
            $set_products_model = new shopSetProductsModel();
            $set_products = $set_products_model->getByField('product_id', $product_ids, 'set_id');
            $required_set_ids = array_keys($set_products);

            $set_model = new shopSetModel();
            $items = $set_model->getSetsWithGroups($required_set_ids, false);
        }
        return $items;
    }
}
