<?php
/**
 * Generate product page.
 */
class shopProdAiGeneratePageController extends waJsonController
{
    public function execute()
    {
        if (!shopLicensing::hasPremiumLicense()) {
            throw new waException(_w('Only available with the premium license.'));
        }

        $product_id = waRequest::post('product_id');
        $request_data = waRequest::post('data', null, 'array');

        if (!$product_id || !$request_data) {
            throw new waException('product_id and data are required');
        }

        $product = new shopProduct($product_id);
        $description_request = (new shopAiApiRequest())
            ->loadFieldsFromApi('store_product_page')
            ->loadFieldValuesFromSettings()
            ->loadFieldValuesFromProduct($product)
            ->setFieldValues($request_data);

        $res = $description_request->generate();
        if (!empty($res['content'])) {
            $this->response['content'] = $res['content'];
        } else if (!empty($res['error_description']) || !empty($res['error'])) {
            $this->errors[] = $res;
        } else {
            throw new waException("Unable to contact AI API", 500);
        }
    }
}
