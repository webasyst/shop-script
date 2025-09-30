<?php
/** @since 11.4.0 */
class shopAiGptMethod extends shopApiMethod
{
    protected $method = 'POST';

    public function execute()
    {
        if (!shopLicensing::hasPremiumLicense()) {
            throw new waAPIException('payment_required', _w('Only available with the premium license.'), 402);
        }

        $entity_type = (string) $this->post('entity', true);

        switch ($entity_type) {
            case 'product':
                $this->executeProduct();
                break;
            default:
                throw new waAPIException('entity_required', sprintf_wp('Incorrect or empty “%s” parameter value.', 'entity'), 400);
                break;
        }
    }

    protected function executeProduct()
    {
        $field = (string) $this->post('field', true);

        switch ($field) {
            case 'description':
            case 'summary':
                $product_id = (int) $this->post('entity_id');

                $description_request = (new shopAiApiRequest())
                    ->loadFieldsFromApi('store_product')
                    ->loadFieldValuesFromSettings();

                if ($product_id) {
                    try {
                        $product = new shopProduct($product_id);
                        $description_request->loadFieldValuesFromProduct($product);
                    } catch (waException $e) {
                        throw new waAPIException('product_not_found', sprintf_wp('Incorrect value of the “%s” parameter.', 'product_id'), 400);
                    }
                }

                if ($field == 'summary') {
                    $description_request->setFieldValue('text_length', 'minimal');
                    if (!empty($product) && !empty($product['description'])) {
                        $description_request->setFieldValue('traits', $product['description']);
                    }
                }

                $res = $description_request->generate();
                if (isset($res['description'])) {
                    $this->response[$field] = $res['description'];
                } else {
                    throw new waAPIException(ifempty($res, 'error', 'server_error'), ifempty($res, 'error_description', 'Unable to generate AI response'), 500);
                }
                break;

            case 'meta_title':
            case 'meta_keywords':
            case 'meta_description':
                $product_id = (int) $this->post('entity_id', true);

                $seo_request = (new shopAiApiRequest())
                    ->loadFieldsFromApi('store_product_seo')
                    ->loadFieldValuesFromSettings();
                if (!empty($product)) {
                    $seo_request->loadFieldValuesFromProduct($product);
                }
                $res = $seo_request->generate();
                if (!empty($res['error_description']) || !empty($res['error'])) {
                    throw new waAPIException(ifempty($res, 'error', 'server_error'), ifempty($res, 'error_description', 'Unable to generate AI response'), 500);
                }
                foreach (['meta_title', 'meta_keywords', 'meta_description'] as $k) {
                    $res_k = substr($k, 5);
                    if (!empty($res[$res_k])) {
                        $this->response[$k] = $res[$res_k];
                    }
                }
                break;

            case 'page':
                $product_id = (int) $this->post('entity_id', true);
                $objective = (string) $this->post('objective', true);

                $product = new shopProduct($product_id);
                $description_request = (new shopAiApiRequest())
                    ->loadFieldsFromApi('store_product_page')
                    ->loadFieldValuesFromSettings()
                    ->loadFieldValuesFromProduct($product)
                    ->setFieldValues([
                        'objective' => $objective,
                    ]);

                $res = $description_request->generate();
                if (!empty($res['content'])) {
                    $this->response['content'] = $res['content'];
                } else {
                    throw new waAPIException(ifempty($res, 'error', 'server_error'), ifempty($res, 'error_description', 'Unable to generate AI response'), 500);
                }
                break;

            default:
                throw new waAPIException('field_required', sprintf_wp('Incorrect or empty “%s” parameter value.', 'field'), 400);
                break;
        }
    }
}
