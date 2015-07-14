<?php

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
        if (empty($data['features_selectable'])) {
            $data['features_selectable'] = array();
        }

        # verify sku_type before save
        if ($data['type_id']) {
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
                        'options' => implode(' x ', $features_counts).' '._w('option', 'options', $features_total_count),
                        'skus'    => _w('%d SKU in total', '%d SKUs in total', $features_total_count)
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

                if (
                    !empty($this->response['raw']['category_id'])
                    &&
                    isset($this->response['categories'][$this->response['raw']['category_id']])
                ) {
                    $this->response['category_name'] = strip_tags($this->response['categories'][$this->response['raw']['category_id']]['name']);
                } else {
                    $this->response['category_name'] = null;
                }

                $this->response['categories'] = array_values($this->response['categories']);

                $this->response = array_merge($this->response, $this->getMeta($product->getId()));

                //$product->categories

//                $sales_rate = waRequest::post('sales_rate', 0, waRequest::TYPE_STRING_TRIM);
//                $sales_rate = (double)str_replace(',', '.', $sales_rate);

//                $runout = $product->getRunout($sales_rate);
//                if (!empty($runout['product'])) {
//                    $runout['product']['date_str'] = wa_date("humandate", $runout['product']['date']);
//                    $runout['product']['days_str'] = _w('%d day', '%d days', $runout['product']['days']);
//                    if ($runout['product']['days'] < 3 * 365 && $runout['product']['days'] > 0) {
//                        $runout['product_str'] =
//                            sprintf(_w('Based on last 30 days sales dynamic (%d items of %s sold during last 30 days), you will run out of %s in <strong>%d days</strong> (on %s)'),
//                                $sales_rate * 30, $product->name, $product->name, $runout['product']['days'], wa_date("humandate", $runout['product']['date'])
//                            );
//                    }
//                } else {
//                    $runout['product'] = new stdClass(); /* {} */
//                }
//                if (!empty($runout['sku'])) {
//                    foreach ($runout['sku'] as &$sk_r) {
//                        if (empty($sk_r['stock'])) {
//                            $sk_r['date_str'] = wa_date("humandate", $sk_r['date']);
//                            $sk_r['days_str'] = _w('%d day', '%d days', $sk_r['days']);
//                        } else {
//                            foreach ($sk_r['stock'] as &$st_r) {
//                                $st_r['date_str'] = wa_date("humandate", $st_r['date']);
//                                $st_r['days_str'] = _w('%d day', '%d days', $st_r['days']);
//                            }
//                        }
//                    }
//                    unset($sk_r, $st_r);
//                } else {
//                    $runout['sku'] = new stdClass(); /* {} */
//                }

                $forecast = $product->getNextForecast();
                if ($forecast['date'] !== null) {
                    $this->response['raw']['runout_str'] = sprintf(
                        _w('Based on your average monthly sales volume for %s during last three months (%d units per month), you will run out of this product in <strong>%d days</strong> (on %s).'),
                        htmlspecialchars($product->name), $forecast['sold_rounded'], $forecast['days'], wa_date("humandate", $forecast['date'])
                    );
                }

                $this->response['storefront_map'] = $product_model->getStorefrontMap($product->id);

            }
        } catch (Exception $ex) {
            $this->setError($ex->getMessage());
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
            // price in light of l18n: if ru - delimeter is ',', if en - delimeter is '.'
            $sku['price_loc'] = (string)((float)$sku['price']);
            $sku['price_str'] = wa_currency($sku['price'], $currency);
            $sku['price_html'] = wa_currency_html($sku['price'], $currency);
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
                if (!empty($r['private'])) {
                    continue;
                }
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
                    $pos = strrpos($frontend_url, $product->url);
                    $frontend_urls[] = array(
                        'url'  => $frontend_url,
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

}
