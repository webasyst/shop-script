<?php

class shopFrontendCompareAction extends waViewAction
{
    /**
     * @throws waDbException
     * @throws waException
     */
    public function execute()
    {
        $ids = waRequest::param('id', array(), waRequest::TYPE_ARRAY_INT);
        if (!$ids) {
            $ids = waRequest::cookie('shop_compare', array(), waRequest::TYPE_ARRAY_INT);
        }
        $collection = new shopProductsCollection('id/'.implode(',', $ids));
        $products = $collection->getProducts('*,skus_filtered,reviews_count');

        $all_features  = [];
        $features_prod = [];
        $features_sku  = [];

        $compare_link = wa()->getRouteUrl('/frontend/compare', array('id' => '%ID%'));
        foreach ($products as $p_id => &$prod) {
            $temp_f   = [];
            $temp_ids = $ids;
            $prod     = new shopProduct($prod, true);
            $pf_model = new shopProductFeaturesModel();

            unset($temp_ids[array_search($p_id, $temp_ids)]);
            $prod['delete_url'] = str_replace('%ID%', implode(',', $temp_ids), $compare_link);
            if (!$temp_ids) {
                $prod['delete_url'] = substr($prod['delete_url'], 0, -1);
            }

            /** Сбор общих характеристик товара */
            foreach ($prod->features as $name_feature => $val) {
                $temp_f[$name_feature] = [];
                $features_prod[$p_id][$name_feature] = (is_object($val) ? (string) $val : $val);
            }

            /** Сбор характеристик SKU товара */
            foreach ($prod->getSkus() as $sku_id => $sku_data) {
                $skus = $prod->getData('skus');
                $skus[$sku_id]['features'] = [];
                foreach ($pf_model->getValues($p_id, -$sku_id) as $k => $v) {
                    $temp_f[$k] = [];
                    $skus[$sku_id]['features'][$k] = (string) $v;
                    if (empty($features_sku[$p_id][$k])) {
                        $features_sku[$p_id][$k] = [$sku_id => (string) $v];
                    } else {
                        $features_sku[$p_id][$k][$sku_id] = (string) $v;
                    }
                }
                $prod->setData('skus', $skus);
                unset($skus);
            }

            /** Сортировка характеристик */
            $list_features = array_keys($prod->getListFeatures());
            foreach ($list_features as $code) {
                if (isset($temp_f[$code])) {
                    $all_features[$code] = ['same' => true];
                }
            }

            unset($prod, $p_id, $name_feature, $val, $sku_id, $sku_data, $k, $v, $temp_f, $code, $list_features, $pf_model);
        }

        /** Собираем воедино характеристики SKU в товар */
        foreach ($products as $p_id => &$prod) {
            if (empty($features_prod[$p_id])) {
                continue;
            }
            $prod_features = [];
            foreach ($all_features as $code => $a_feature) {
                if (!empty($features_sku[$p_id][$code])) {
                    $s_val = (is_array($features_sku[$p_id][$code]) ? $features_sku[$p_id][$code] : [(string) $features_sku[$p_id][$code]]);
                    if ((int) $prod->sku_count === count($features_sku[$p_id][$code])) {
                        $prod_features[$code] = $s_val;
                    } elseif (!empty($features_prod[$p_id][$code])) {
                        $prod_features[$code] = array_merge($s_val, (array) $features_prod[$p_id][$code]);
                    } else {
                        $prod_features[$code] = $s_val;
                    }
                } elseif (!empty($features_prod[$p_id][$code])) {
                    $prod_features[$code] = (array) $features_prod[$p_id][$code];
                }
            }
            $prod->features = $prod_features;
            unset($p_id, $prod, $prod_features, $s_val, $a_feature, $code);
        }

        /** Сравнение характеристик товаров */
        foreach ($products as &$prod) {
            $prod_features = $prod->features;
            foreach ($all_features as $code => &$a_feature) {
                if (empty($prod_features[$code])) {
                    $a_feature['same'] = false;
                    continue;
                }
                $prod_features[$code] = array_unique($prod_features[$code]);
                if (true === $a_feature['same']) {
                    if (empty($a_feature['value'])) {
                        $a_feature['value'] = $prod_features[$code];
                    }

                    $f_val = $prod_features[$code];
                    sort($f_val, SORT_STRING);
                    sort($a_feature['value'], SORT_STRING);
                    if ($a_feature['value'] !== $f_val) {
                        $a_feature['same'] = false;
                    }
                }
            }
            $prod->features = $prod_features;
            unset($prod, $prod_features, $a_feature, $code, $f_val);
        }

        if ($all_features) {
            $feature_model = new shopFeatureModel();
            foreach ($feature_model->getByCode(array_keys($all_features)) as $code => $f) {
                if ($f['status'] === 'public') {
                    $all_features[$code] += $f;
                } else {
                    unset($all_features[$code]);
                }
            }
        }


        /**
         * Add html to compare
         *
         * @param array $products
         * @param array $all_features
         *
         * @event frontend_compare
         */

        $params = array(
            'features' => &$all_features,
            'products' => &$products,
        );

        $frontend_compare = wa()->event('frontend_compare', $params);
        $this->view->assign('frontend_compare', $frontend_compare);

        $this->view->assign('features', $all_features);
        $this->view->assign('products', $products);

        $units = shopHelper::getUnits();
        $this->view->assign('units', $units);
        $this->view->assign('formatted_units', shopFrontendProductAction::formatUnits($units));
        $this->view->assign('fractional_config', shopFrac::getFractionalConfig());

        $this->setLayout(new shopFrontendLayout());
        $this->setThemeTemplate('compare.html');
    }
}
