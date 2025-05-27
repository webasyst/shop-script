<?php
/**
 * HTML for dialog used to generate product description (and summary and SEO) using AI.
 */
class shopProdAiGenerateDescriptionDialogAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::request('product_id', null, 'int');
        $do_not_save = waRequest::post('do_not_save', 0, 'int');
        $field_to_fill = waRequest::post('field_to_fill', null, 'string');

        $this->view->assign([
            'sections' => $this->getFormFields(new shopProduct($product_id), $field_to_fill),
            'product_id' => $product_id,
            'do_not_save' => $do_not_save,
            'field_to_fill' => $field_to_fill,
        ]);
    }

    protected function getFormFields(shopProduct $p, $field_to_fill)
    {
        $request = new shopAiApiRequest();
        if ($field_to_fill && substr($field_to_fill, 0, 5) === 'meta_') {
            $request->loadFieldsFromApi('store_product_seo');
        } else {
            $request->loadFieldsFromApi('store_product');
        }
        $request->loadFieldValuesFromSettings()
                ->loadFieldValuesFromProduct($p);
        if ($field_to_fill === 'summary') {
            unset($request->fields['text_length']);
        }
        return $request->getSectionsWithFields();
    }
}
