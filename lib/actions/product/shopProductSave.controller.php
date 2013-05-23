<?php
class shopProductSaveController extends waJsonController
{
    public function execute()
    {
        $update = waRequest::post('update'); // just update one or any field of product
        if ($update) {
            $this->update($update);
            return;
        }

        $data = waRequest::post('product');
        $id = (empty($data['id']) || !intval($data['id'])) ? null : $data['id'];
        if (!$id && isset($data['id'])) {
            unset($data['id']);
        }

        $features = array();
        $features_model = new shopFeatureModel();
        if ($data['type_id']) {
            $features = $features_model->getMultipleSelectableFeaturesByType($data['type_id']);
        }

        if (!$features) {
            $data['sku_type'] = shopProductModel::SKU_TYPE_FLAT;
        }

        if ($data['sku_type'] == shopProductModel::SKU_TYPE_SELECTABLE && !waRequest::post('features_selectable', array())) {
            throw new waException(_w("Check at least one feature value"));
        }

        // edit product info - check rights

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

        if (empty($data['categories'])) {
            $data['categories'] = array();
        }

        if (empty($data['tags'])) {
            $data['tags'] = array();
        }

        $product = new shopProduct($id);
        try {
            $features_counts = null;

            $features_selectable_model = new shopProductFeaturesSelectableModel();
            if ($data['sku_type'] == shopProductModel::SKU_TYPE_SELECTABLE) {
                $features_selectable = waRequest::post('features_selectable', array());

                $features_counts = array();
                foreach ($features_selectable as $values) {
                    $features_counts[] = count($values);
                }

                if (!$id || !$this->arrayEq($features_selectable_model->getByProduct($id), $features_selectable)) {
                    $data['skus'] = $this->generateSku($product, $features_selectable, isset($data['skus']) ? $data['skus'] : array(), array('price' => $data['base_price_selectable']));
                }
            }

            if ($product->save($data, true, $this->errors)) {

                // selectable features
                if ($product->sku_type == shopProductModel::SKU_TYPE_FLAT) {

                    // removing selectable features and virual skus
                    $features_selectable_model->save(array(), $product->id);
                    $product_skus_model = new shopProductSkusModel();
                    $product_skus_model->deleteJoin('shop_product_features', $product->id, array('virtual' => 1));
                    $product_skus_model->deleteByField(array('product_id' => $product->id, 'virtual' => 1));

                } else {
                    $features_selectable_model->save($features_selectable, $product->id);
                }

                $this->response['id'] = $product->getId();
                $this->response['name'] = $product->name;
                $this->response['url'] = $product->url;

                $frontend_url = null;
                $fontend_base_url = null;

                $routing = wa()->getRouting();
                $domain_routes = $routing->getByApp($this->getAppId());
                foreach ($domain_routes as $domain => $routes) {
                    foreach ($routes as $r) {
                        if (empty($r['type_id']) || (in_array($product->type_id, (array) $r['type_id']))) {
                            $routing->setRoute($r, $domain);
                            $frontend_url = $routing->getUrl('/frontend/product', array('product_url' => $product->url), true);
                            break;
                        }
                    }
                }
                if ($frontend_url) {
                    $pos = strrpos($frontend_url, $product->url);
                    $fontend_base_url = $pos !== false ? rtrim(substr($frontend_url, 0, $pos), '/').'/' : $frontend_url;
                }

                $this->response['frontend_url'] = $frontend_url;
                $this->response['fontend_base_url'] = $fontend_base_url;
                $this->response['raw'] = $this->workupData($product->getData());

                if ($features_counts !== null) {

                    $features_total_count = array_product($features_counts);

                    $this->response['features_selectable_strings'] = array(
                        'options' => implode(' x ', $features_counts).' '._w('option', 'options', $features_total_count),
                        'skus'    => _w('%d SKU in total', '%d SKUs in total', $features_total_count)
                    );
                }
            }
        } catch (Exception $ex) {
            $this->setError($ex->getMessage());
        }
    }

    protected function generateSku(shopProduct $product, $features_selectable, $old_sku, $data = array())
    {

        $features_model = new shopFeatureModel();
        $features = $features_model->getById(array_keys($features_selectable));
        $features = $features_model->getValues($features);

        if ($product->id) {
            // remove old virtual skus
            $product_skus_model = new shopProductSkusModel();
            $product_skus_model->deleteJoin('shop_product_features', $product->id, array('virtual' => 1));
            $product_skus_model->deleteByField(array('product_id' => $product->id, 'virtual' => 1));
            foreach ($old_sku as $s_id => $s) {
                if (!empty($s['virtual'])) {
                    unset($old_sku[$s_id]);
                }
            }
        }
        $features_selectable_model = new shopProductFeaturesSelectableModel();
        $selectable = $features_selectable_model->getByProduct($product->id);

        $product_features_model = new shopProductFeaturesModel();
        $rows = $product_features_model->getSkuFeatures($product->id);
        $old_sku_features = array();
        foreach ($rows as $sku_id => $sf) {
            $sku_f = "";
            foreach ($selectable as $f_id => $f) {
                if (isset($sf[$f_id])) {
                    $sku_f .= $f_id.":".$sf[$f_id].";";
                }
            }
            $old_sku_features[$sku_f] = $sku_id;
        }

        $skus_features = $this->arrayCartesian($features_selectable);
        $i = -1;
        $skus = array();
        foreach ($skus_features as $f) {
            $names = array();
            $sku_f = "";
            foreach ($f as $f_id => $v_id) {
                $names[] = $features[$f_id]['values'][$v_id];
                $sku_f .= $f_id.":".$v_id.";";
            }
            // already exists
            if (isset($old_sku_features[$sku_f])) {
                continue;
            }
            $skus[$i--] = array_merge(array(
                'name'      => implode(', ', $names),
                'features'  => $f,
                'virtual'   => 1,
                'available' => 1,
                'count'     => null
            ), $data);
        }
        foreach ($old_sku as $s_id => $s) {
            $skus[$s_id] = $s;
        }
        return $skus;
    }

    protected function arrayEq($a1, $a2)
    {
        if (count($a1) != count($a2)) {
            return false;
        }
        foreach ($a1 as $k => $v) {
            if (!isset($a2[$k]) || count($v) != count($a2[$k])) {
                return false;
            }
            foreach ($v as $sv) {
                if (!in_array($sv, $a2[$k])) {
                    return false;
                }
            }
        }
        return true;
    }

    protected function arrayCartesian($arrays)
    {
        $result = array();
        $keys = array_keys($arrays);
        $reverse_keys = array_reverse($keys);
        $size = intval(count($arrays) > 0);
        foreach ($arrays as $array) {
            $size *= count($array);
        }
        for ($i = 0; $i < $size; $i++) {
            $result[$i] = array();
            foreach ($keys as $j) {
                $result[$i][$j] = current($arrays[$j]);
            }
            foreach ($reverse_keys as $j) {
                if (next($arrays[$j])) {
                    break;
                } elseif (isset($arrays[$j])) {
                    reset($arrays[$j]);
                }
            }
        }
        return $result;
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
        }
    }

    public function workupData($data)
    {
        $currency = $data['currency'] ? $data['currency'] : $this->getConfig()->getCurrency();
        foreach ($data['skus'] as & $sku) {
            $sku['price_str'] = wa_currency($sku['price'], $currency);
            $sku['stock_icon'] = array();
            $sku['stock_icon'][0] = shopHelper::getStockCountIcon($sku['count']);
            if (!empty($sku['stock'])) {
                foreach ($sku['stock'] as $stock_id => $count) {
                    $sku['stock_icon'][$stock_id] = shopHelper::getStockCountIcon($count, $stock_id);
                }
            }
        }
        unset($sku);
        return $data;
    }
}
