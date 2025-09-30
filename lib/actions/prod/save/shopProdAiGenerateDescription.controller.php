<?php
/**
 * Generate product description using AI.
 */
class shopProdAiGenerateDescriptionController extends waJsonController
{
    public function execute()
    {
        $product_id = waRequest::post('product_id');
        $request_data = waRequest::post('data', null, 'array');
        $save_to_product = waRequest::post('save_to_product', null, 'int');
        $skip_if_exists = waRequest::post('skip_if_exists', null, 'int');
        $do_not_ask = waRequest::post('do_not_ask', null, 'string');
        $fields_to_fill = waRequest::post('fields_to_fill', [], 'array');
        $force_save_settings = waRequest::post('force_save_settings', 0, 'int');

        if (!$product_id && !$request_data) {
            throw new waException('At least one of: product_id or data must be provided');
        }

        $description_request = (new shopAiApiRequest())
            ->loadFieldsFromApi('store_product')
            ->loadFieldValuesFromSettings();

        if ($product_id) {
            $product = new shopProduct($product_id);
            $description_request->loadFieldValuesFromProduct($product);
        } else if ($save_to_product) {
            throw new waException('product_id is required to use save_to_product');
        } else if ($skip_if_exists) {
            throw new waException('product_id is required to use skip_if_exists');
        }

        if ($request_data) {
            // Non-premium users are not allowed to benefit from custom AI settings
            $is_premium = shopLicensing::hasPremiumLicense();
            if (!$is_premium) {
                $request_data = array_intersect_key($request_data, [
                    'text_length' => 1,
                    'product_name' => 1,
                    'categories' => 1,
                    'advantages' => 1,
                    'traits' => 1,
                ]);
            }
            $description_request->setFieldValues($request_data);
            if ($is_premium && ($force_save_settings || !shopAiApiRequest::fieldValuesSavedInSettings())) {
                // first call saves preferences to global settings
                $description_request->saveFieldValuesToSettings();
            }
            $app_settings_model = new waAppSettingsModel();
            $current_dna = $app_settings_model->get('shop', 'ai_dna_descr');
            if ($do_not_ask && !$current_dna) {
                $app_settings_model->set('shop', 'ai_dna_descr', '1');
            } else if ($do_not_ask === '0' && $current_dna) {
                $app_settings_model->del('shop', 'ai_dna_descr');
            }
        }

        if (!$fields_to_fill) {
            $fields_to_fill = ['description' => 1];
        }
        if (isset($fields_to_fill['description'])) {
            if (!$skip_if_exists || !strlen(trim(ifset($product, 'description', '')))) {
                $res = $description_request->generate();
                if (!empty($res['description'])) {
                    $product['description'] = $this->response['description'] = $res['description'];
                } else if (!empty($res['error_description']) || !empty($res['error'])) {
                    $this->errors[] = $res;
                    return;
                }
            }
        }
        if (isset($fields_to_fill['summary'])) {
            if (!$skip_if_exists || !strlen(trim(ifset($product, 'summary', '')))) {
                $description_request->setFieldValue('text_length', 'minimal');
                if ($product['description']) {
                    $description_request->setFieldValue('traits', $product['description']);
                }
                $res = $description_request->generate();
                if (!empty($res['description'])) {
                    $product['summary'] = $this->response['summary'] = strip_tags($res['description']);
                } else if (!empty($res['error_description']) || !empty($res['error'])) {
                    $this->errors[] = $res;
                    return;
                }
            }
        }
        if (isset($fields_to_fill['meta_title']) || isset($fields_to_fill['meta_keywords']) || isset($fields_to_fill['meta_description'])) {
            $seo_request = (new shopAiApiRequest())
                ->loadFieldsFromApi('store_product_seo')
                ->loadFieldValuesFromSettings();
            if ($request_data) {
                $seo_request->setFieldValues($request_data);
            }
            if (!empty($product)) {
                $seo_request->loadFieldValuesFromProduct($product);
                if ($request_data) {
                    // product values override $request_data for SEO, except for advantages and traits
                    $rd2 = array_intersect_key($request_data, [
                        'advantages' => true,
                        'traits' => true,
                    ]);
                    $rd2 = array_filter($rd2);
                    if ($rd2) {
                        $seo_request->setFieldValues($rd2);
                    }
                }
            }
            $res = $seo_request->generate();
            if (!empty($res['error_description']) || !empty($res['error'])) {
                $this->errors[] = $res;
                return;
            }
            foreach (['meta_title', 'meta_keywords', 'meta_description'] as $k) {
                $res_k = substr($k, 5);
                if (isset($fields_to_fill[$k]) && !empty($res[$res_k])) {
                    $product[$k] = $this->response[$k] = $res[$res_k];
                }
            }
        }

        if ($save_to_product) {
            if ($this->response) {
                $product->save([], false);
            }
            $this->response = [
                'product' => [ 'id' => $product['id'] ],
            ];
        } else if ($product_id) {
            $this->response['product'] = [
                'id' => $product['id'],
                'name' => $product['name']
            ];
            foreach($fields_to_fill as $key => $_) {
                if (isset($product[$key])) {
                    $this->response['product'][$key] = $product[$key];
                }
            }
        }
    }
}
