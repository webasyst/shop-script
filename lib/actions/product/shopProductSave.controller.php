<?php

/**
 * Class shopProductSaveController
 * @method shopConfig getConfig()
 */
class shopProductSaveController extends waJsonController
{
    /**
     * @var shopProduct
     */
    protected $product;
    protected $product_model;

    public function execute()
    {

        $update = waRequest::post('update'); // just update one or any field of product
        if ($update) {
            $this->update($update);

            $product = new shopProduct(waRequest::request('id'));
            $this->response['default_meta_title'] = shopProduct::getDefaultMetaTitle($product);

            return;
        }

        $data = waRequest::post('product');

        $id = (empty($data['id']) || !intval($data['id'])) ? null : $data['id'];
        if (!$id && isset($data['id'])) {
            unset($data['id']);
        }

        # edit product info - check rights
        $product_model = new shopProductModel();
        if ($id) {
            if (!$product_model->checkRights($id)) {
                throw new waRightsException(_w("Access denied"));
            }
        } else {
            if (!$product_model->checkRights($data)) {
                throw new waRightsException(_w("Access denied"));
            }
        }

        $skus = waRequest::post('skus', array());
        if (isset($data['skus'])) {
            foreach ($skus as $s_id => $s) {
                if (isset($data['skus'][$s_id])) {
                    $data['skus'][$s_id] += $s;
                } else {
                    $data['skus'][$s_id] = $s;
                }
            }
        } else {
            $data['skus'] = $skus;
        }

        if (empty($data['categories'])) {
            $data['categories'] = array();
        }
        if (empty($data['sets'])) {
            $data['sets'] = array();
        }
        if (empty($data['tags'])) {
            $data['tags'] = array();
        }
        if (waRequest::request('ignore_features_selectable')) {
            // Product has been modified in new editor
            // and has incompatible set of features_selectable.
            // Do not attempt to save it via old editor
            // because it breaks stuff.
            unset(
                $data['ignore_features_selectable'],
                $data['features_selectable'],
                $data['sku_type']
            );
        } else {
            # verify sku_type before save
            if (!empty($data['type_id'])) {
                $features_model = new shopFeatureModel();
                if ($features_model->isTypeMultipleSelectable($data['type_id'])) {
                    if ($data['sku_type'] == shopProductModel::SKU_TYPE_SELECTABLE) {
                        if (empty($data['features_selectable'])) {
                            throw new waException(_w("Check at least one feature value"));
                        }
                    }
                } else {
                    $data['sku_type'] = shopProductModel::SKU_TYPE_FLAT;
                }
            } else {
                $data['sku_type'] = shopProductModel::SKU_TYPE_FLAT;
            }

            if ($data['sku_type'] == shopProductModel::SKU_TYPE_FLAT) {
                $data['features_selectable'] = array();
            }
        }

        $this->ensureWeightForSkus($data);
        $this->ensureGtinForSkus($data);

        $errors = $this->validateFeatures($data);
        if ($errors) {
            $this->errors = $errors;
            return;
        }

        try {
            $product = new shopProduct($id);

            // for logging changes in stocks
            shopProductStocksLogModel::setContext(shopProductStocksLogModel::TYPE_PRODUCT);
            if ($product->save($data, true, $this->errors)) {
                $features_counts = null;
                if ($product->sku_type == shopProductModel::SKU_TYPE_SELECTABLE) {
                    $features_counts = array();
                    foreach ($product->features_selectable as $f) {
                        if (isset($f['selected'])) {
                            $features_counts[] = $f['selected'];
                        } else {
                            $features_counts[] = count($f['values']);
                        }
                    }

                    $features_total_count = array_product($features_counts);
                    $this->response['features_selectable_strings'] = array(
                        'options' => sprintf(_w('Parameters: %s'), implode(' x ', $features_counts)),
                        'skus'    => sprintf(_w('SKUs: %d'), $features_total_count),
                    );
                }

                shopProductStocksLogModel::clearContext();

                if ($id) {
                    $this->logAction('product_edit', $id);
                } else {
                    $this->logAction('product_add', $product->getId());
                    $product->type && wa()->getUser()->setSettings('shop', 'last_type_id', $product->type);
                }
                $this->response['id'] = $product->getId();
                $this->response['name'] = $product->name;
                $this->response['url'] = $product->url;
                $this->response['frontend_urls'] = $this->getUrl($product);
                $this->response['categories'] = $this->getCategories($product);
                $this->response['tags'] = $this->getTags($product);
                $this->response['raw'] = $this->workupData($product->getData());

                if (!empty($this->response['raw']['category_id'])
                    &&
                    isset($this->response['categories'][$this->response['raw']['category_id']])
                ) {
                    $this->response['category_name'] = strip_tags($this->response['categories'][$this->response['raw']['category_id']]['name']);
                } else {
                    $this->response['category_name'] = null;
                }

                $this->response['categories'] = array_values($this->response['categories']);

                $this->response = array_merge($this->response, $this->getMeta($product->getId()));

                $forecast = $product->getNextForecast();
                if ($forecast['date'] !== null && $forecast['days'] < shopProduct::MAX_FORECAST_DAYS) {
                    $this->response['raw']['runout_str'] = sprintf(
                        _w('Based on your average monthly sales volume for %s during last three months (%d units per month), you will run out of this product in <strong>%d days</strong> (on %s).'),
                        htmlspecialchars($product->name),
                        $forecast['sold_rounded'],
                        $forecast['days'],
                        wa_date("humandate", $forecast['date'])
                    );
                } else {
                    $this->response['raw']['runout_str'] = sprintf(
                        _w('Based on your average monthly sales volume for %s during last three months (%d units per month), you will run out of this product in more than 10 years (on %s).'),
                        htmlspecialchars($product->name),
                        $forecast['sold_rounded'],
                        wa_date("humandate", $forecast['date'])
                    );
                }
                $this->response['storefront_map'] = $product_model->getStorefrontMap($product->id);

            }
        } catch (Exception $ex) {
            if (get_class($ex) === 'waDbException' && in_array($ex->getCode(),  [1267, 1366])) {
                $this->setError(_w('Enable the emoji support in system settings.'));
            } else {
                $this->setError($ex->getMessage());
            }
        }
    }

    public function update($data)
    {
        $id = waRequest::get('id', 0, waRequest::TYPE_INT);
        if (!$id) {
            return;
        }

        $product_model = new shopProductModel();
        if (!$product_model->checkRights($id)) {
            throw new waException(_w("Access denied"));
        }

        // available fields
        $fields = array('name');
        $update = array();
        foreach ($data as $name => $value) {
            if (in_array($name, $fields) !== false) {
                $update[$name] = $value;
            }
        }
        if ($update) {
            $product_model->updateById($id, $update);
            $this->logAction('product_edit', $id);
        }
    }

    public function workupData($data)
    {
        $currency = $data['currency'] ? $data['currency'] : $this->getConfig()->getCurrency();

        $file_names = array(); // sku_id => filename of attachment
        foreach ($data['skus'] as $id => &$sku) {
            if (!isset($sku['file_name'])) {
                $file_names[$sku['id']] = ''; // need to obtain filename
            }
            // price in light of l18n: if ru - delimiter is ',', if en - delimiter is '.'
            $sku['price_loc'] = (string)((float)$sku['price']);
            $sku['price_str'] = wa_currency($sku['price'], $currency);
            $sku['price_html'] = wa_currency_html($sku['price'], $currency);
            $sku['compare_price_loc'] = (string)((float)$sku['compare_price']);
            $sku['purchase_price_loc'] = (string)((float)$sku['purchase_price']);
            $sku['stock_icon'] = array();
            $sku['stock_icon'][0] = shopHelper::getStockCountIcon(ifset($sku['count']));
            if (!empty($sku['stock'])) {
                foreach ($sku['stock'] as $stock_id => $count) {
                    $sku['stock_icon'][$stock_id] = shopHelper::getStockCountIcon($count, $stock_id);
                }
            }
        }
        unset($sku);

        // obtain filename
        if ($file_names) {
            $product_skus_model = new shopProductSkusModel();
            $file_names = $product_skus_model->select('id, file_name')->where("id IN('".implode("','", array_keys($file_names))."')")->fetchAll('id', true);
            foreach ($file_names as $sku_id => $file_name) {
                $data['skus'][$sku_id]['file_name'] = $file_name;
            }
        }

        return $data;
    }

    protected function getUrl($product)
    {

        $frontend_urls = array();

        $routing = wa()->getRouting();
        $domain_routes = $routing->getByApp($this->getAppId());
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $r) {
                if (empty($r['type_id']) || (in_array($product->type_id, (array)$r['type_id']))) {
                    $routing->setRoute($r, $domain);
                    $url_params = array('product_url' => $product->url);
                    if ($product->category_id) {
                        if (empty($category_model)) {
                            $category_model = new shopCategoryModel();
                        }
                        $category = $category_model->getById($product->category_id);
                        if ($category) {
                            if (!empty($r['url_type']) && ($r['url_type'] == 1)) {
                                $url_params['category_url'] = $category['url'];
                            } else {
                                $url_params['category_url'] = $category['full_url'];
                            }
                        }
                    }
                    $frontend_url = $routing->getUrl('/frontend/product', $url_params, true);
                    $pos = strrpos($frontend_url, (string)$product->url);
                    $frontend_urls[] = array(
                        'url'  => waIdna::dec($frontend_url),
                        'base' => $pos !== false ? rtrim(substr($frontend_url, 0, $pos), '/').'/' : $frontend_url
                    );
                }
            }
        }
        return $frontend_urls;
    }

    public function getCategories(shopProduct $product)
    {
        $model = new shopCategoryProductsModel();
        return $model->getData($product);
    }

    public function getTags(shopProduct $product)
    {
        $tags = array();
        foreach ($product->tags as $tag_id => $tag_name) {
            $tags[] = array(
                'id'   => $tag_id,
                'name' => $tag_name,
                'url'  => urlencode($tag_name)
            );
        }
        return $tags;
    }

    public function getMeta($product_id)
    {
        $product = new shopProduct($product_id);
        return array(
            'default_meta_title'       => shopProduct::getDefaultMetaTitle($product),
            'default_meta_keywords'    => shopProduct::getDefaultMetaKeywords($product),
            'default_meta_description' => shopProduct::getDefaultMetaDescription($product),
        );
    }

    /**
     * Ensure catabase consistency by making sure
     * shop_feature.available_for_sku is enabled for feature weight.
     *
     * This flag should always be enabled for weight and can not be disabled via settings.
     * Saving weight via shop settings UI will fix it, too.
     *
     * Still this seems to be a good place to make that safeguard check.
     */
    protected function ensureWeightForSkus($data)
    {
        foreach(ifset($data, 'skus', []) as $sku) {
            if (isset($sku['features']['weight'])) {
                $feature_model = new shopFeatureModel();
                $feature_model->updateByField('code', 'weight', [
                    'available_for_sku' => 1,
                ]);
                return;
            }
        }
    }

    /**
     * Same as ensureWeightForSkus but for GTIN feature
     * @param $data
     * @throws waDbException
     * @throws waException
     */
    protected function ensureGtinForSkus($data)
    {
        foreach(ifset($data, 'skus', []) as $sku) {
            if (isset($sku['features']['gtin'])) {
                $feature_model = new shopFeatureModel();
                $feature_model->updateByField('code', 'gtin', [
                    'available_for_sku' => 1,
                ]);
                return;
            }
        }
    }

    protected function validateFeatures($data)
    {
        $errors = [];

        $pfm = new shopProductFeaturesModel();
        $gtin = isset($data['features']['gtin']) ? trim($data['features']['gtin']) : '';
        $is_valid = $pfm->validateGtinFeatureValue($gtin);
        if (!$is_valid) {
            $errors['features']['gtin'] = _w('Invalid GTIN code, it must have only 8, 12, 13, or 14 digits.');
        }

        $sku_errors = $this->validateSkuFeatures($data);
        if ($sku_errors) {
            $errors['skus'] = $sku_errors;
        }

        return $errors;
    }

    protected function validateSkuFeatures($data)
    {
        $errors = [];
        $pfm = new shopProductFeaturesModel();
        $status_count = 0;
        foreach ($data['skus'] as $sku_id => $sku) {
            $gtin = isset($sku['features']['gtin']) ? trim($sku['features']['gtin']) : '';
            $is_valid = $pfm->validateGtinFeatureValue($gtin);
            if (!$is_valid) {
                $errors[$sku_id]['features']['gtin'] = _w('Invalid GTIN code, it must have only 8, 12, 13, or 14 digits.');
            }
            if (!empty($sku['status'])) {
                $status_count++;
            }
        }

        if (!empty($data['skus']) && empty($status_count)) {
            $errors['status'] = _w("At least one SKU must be visible in the storefront.");
        }

        $main_sku_id = ifset($data['sku_id'], null);
        if ($main_sku_id && empty($errors['status']) && empty($data['skus'][$main_sku_id]['status'])) {
            $errors['status'] = _w('The main SKU cannot be hidden. Either select another main SKU or make the main SKU visible.');
        }

        return $errors;
    }


}
