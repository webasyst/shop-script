<?php
/**
 * HTML for dialog used to mass generate products description using AI.
 */
class shopProdMassAIGenerateDescriptionDialogAction extends waViewAction
{
    public function execute()
    {
        $product_ids = waRequest::request('product_ids', [], 'array');

        $presentation_id = waRequest::request('presentation_id', 0, 'int');
        if ($presentation_id) {
            $presentation = new shopPresentation($presentation_id, true);
            $options = [];
            if ($presentation->getFilterId() > 0) {
                $options['prepare_filter'] = $presentation->getFilterId();
            }
            $collection = new shopProductsCollection('', $options);
            $products = $presentation->getProducts($collection, [
                'fields' => ['id']
            ]);
            $product_ids = array_keys($products);
        }

        $this->view->assign([
            'sections' => $this->getFormData(),
            'product_ids' => $product_ids,
            'has_ai_config' => shopAiApiRequest::fieldValuesSavedInSettings(),
        ]);
    }

    protected function getFormData()
    {
        $request = (new shopAiApiRequest())
            ->loadFieldsFromApi('store_product')
            ->loadFieldValuesFromSettings();

        unset(
            $request->fields['product_name'],
            $request->fields['categories'],
            $request->fields['advantages'],
            $request->fields['traits']
        );

        return $request->getSectionsWithFields();
    }
}
