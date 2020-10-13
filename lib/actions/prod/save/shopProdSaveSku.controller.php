<?php
/**
 * Accepts POST data from Sku tab in product editor.
 */
class shopProdSaveSkuController extends waJsonController
{
    public function execute()
    {
        $product_raw_data = waRequest::post('product', null, 'array');
        $product = new shopProduct(ifempty($product_raw_data, 'id', null));
        if (!$product['id']) {
            throw new waException(_w('Not found'), 404);
        }
        $this->checkRights($product['id']);

        $product_data = $this->prepareProductData($product, $product_raw_data);

        if ($product_data && !$this->errors) {
            $product = $this->saveProduct($product, $product_data);
        }

        if (!$this->errors) {
            $this->response = $this->prepareResponse($product);
        }
    }

    /**
     * Takes data from POST and prepares format suitable as input for for shopProduct class.
     * If something gets wrong, writes errors into $this->errors.
     *
     * @param shopProduct
     * @param array
     * @return array
     */
    protected function prepareProductData(shopProduct $product, array $product_raw_data)
    {
        $product_data = array_intersect_key($product_raw_data, [
            'badge' => true,
            'sku_id' => true,
            'currency' => true,
            'features' => true,
            'skus' => true,
        ]);

        // When no values come for a `checklist` feature type, it means we need to remove all its values.
        // Add empty array as data for the checklist if product already contains value for it.
        // Do that when user attempts to save at least one feature.
        if (isset($product_data['features'])) {

            $codes = [];
            foreach($product->features as $code => $values) {
                // checklist values is an array, ignore everything else
                if (is_array($values)) {
                    // Only bother if data did not come from POST
                    if (!isset($product_data['features'][$code])) {
                        $codes[] = $code;
                    }
                }
            }

            if ($codes) {
                $feature_model = new shopFeatureModel();
                $features = $feature_model->getByField('code', $codes, 'code');
                foreach ($features as $code => $feature) {
                    // make sure feature is indeed a checklist
                    if ($feature['selectable'] && $feature['multiple']) {
                        $product_data['features'][$code] = [];
                    }
                }
            }
            unset($features, $feature, $codes, $code);
        }

        // Mark SKUs for deletion if data for sku_id is missing
        // shopProduct does not touch SKUs when data is not provided
        // to delete a SKU, its existing sku_id must point to any scalar value
        if (isset($product_data['skus']) && is_array($product_data['skus'])) {
            $product_data['skus'] += array_fill_keys(array_keys($product['skus']), '');
        } else {
            unset($product_data['skus']);
        }

        // SKU type is whether customer selects SKU from a flat list (SKU_TYPE_FLAT)
        // or selects based on features (SKU_TYPE_SELECTABLE).
        $new_sku_type = ifset($product_raw_data, 'sku_type', $product['sku_type']);
        // Selectable features are those shown to customers in frontend when they select SKU
        $new_features_selectable_ids = ifset($product_raw_data, 'features_selectable', null);
        if (isset($new_features_selectable_ids) && !is_array($new_features_selectable_ids)) {
            // empty string means empty array
            $new_features_selectable_ids = [];
        }
        if ($new_sku_type == shopProductModel::SKU_TYPE_FLAT || !empty($new_features_selectable_ids)) {
            $product_data['features_selectable_ids'] = $new_features_selectable_ids;
            $product_data['sku_type'] = $new_sku_type;
        }

        return $product_data;
    }

    /**
     * @throws waRightsException
     */
    protected function checkRights($id)
    {
        $product_model = new shopProductModel();
        if (!$id || !$product_model->checkRights($id)) {
            throw new waRightsException(_w("Access denied"));
        }
    }

    /**
     * Takes product and data prepared by $this->prepareProductData()
     * Saves to DB and returns shopProduct just saved.
     * If something goes wrong, writes errors into $this->errors and returns null.
     * @param shopProduct $product
     * @param array $product_data
     * @return shopProduct or null
     */
    protected function saveProduct(shopProduct $product, array $product_data)
    {
        try {
            // Save selectable features separately, not via shopProduct class
            // because 'features_selectable' key of shopProduct generates SKUs on the fly,
            // which is not what we want in this new editor.
            $features_selectable_ids = ifset($product_data, 'features_selectable_ids', null);
            unset($product_data['features_selectable_ids']);

            // Save product
            $errors = null;
            if ($product->save($product_data, true, $errors)) {
                $this->logAction('product_edit', $product['id']);

                // Separately save selectable features
                if (isset($features_selectable_ids)) {
                    $product_features_selectable_model = new shopProductFeaturesSelectableModel();
                    $product_features_selectable_model->setFeatureIds($product, $features_selectable_ids);
                }
            }

        } catch (Exception $ex) {
            $message = $ex->getMessage();
            if (SystemConfig::isDebug()) {
                $message .= $ex->getTraceAsString();
            }
            $this->errors[] = [
                'id' => "general",
                'text' => _w('Unable to save product').' '.$message,
            ];
        }
        if ($errors) {
            $this->errors[] = [
                'id' => "general",
                'text' => _w('Unable to save product').' '.wa_dump_helper($errors),
            ];
        }

        return $product;
    }

    protected function prepareResponse(shopProduct $product)
    {
        return [
            'id' => $product['id'],
        ];
    }
}
