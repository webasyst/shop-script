<?php

class shopFrontendCompareAction extends waViewAction
{
    public function execute()
    {
        $ids = waRequest::param('id', array(), waRequest::TYPE_ARRAY_INT);
        if (!$ids) {
            $ids = waRequest::cookie('shop_compare', array(), waRequest::TYPE_ARRAY_INT);
        }
        $collection = new shopProductsCollection('id/'.implode(',', $ids));
        $products = $collection->getProducts();

        $features = array();
        $i = 0;

        $compare_link = wa()->getRouteUrl('/frontend/compare', array('id' => '%ID%'));
        foreach ($products as &$p) {
            $p = new shopProduct($p, true);
            $temp_ids = $ids;
            unset($temp_ids[array_search($p['id'], $temp_ids)]);
            $p['delete_url'] = str_replace('%ID%', implode(',', $temp_ids), $compare_link);
            if (!$temp_ids) {
                $p['delete_url'] = substr($p['delete_url'], 0, -1);
            }
            foreach ($p->features as $code => $v) {
                if (is_object($v)) {
                    $v = trim(isset($v['compare']) ? $v['compare'] : $v['value']);

                } elseif (is_array($v)) {
                    foreach ($v as &$_v) {
                        if (is_object($_v)) {
                            $_v = trim(isset($_v['compare']) ? $_v['compare'] : $_v['value']);
                        } else {
                            $_v = trim($_v);
                        }
                        unset($_v);
                    }
                    sort($v, SORT_STRING);
                    $v = serialize($v);
                } else {
                    $v = trim($v);
                }

                if (isset($features[$code]) && $features[$code]['same']) {
                    if ($v !== $features[$code]['value']) {
                        $features[$code]['same'] = false;
                    }
                } else {
                    if (!isset($features[$code])) {
                        $features[$code] = array();
                    }

                    if (!$i) {
                        $features[$code]['same'] = true;
                        $features[$code]['value'] = $v;
                    } else {
                        $features[$code]['same'] = false;
                    }
                }
            }
            foreach ($features as $code => $v) {
                if (!isset($p->features[$code])) {
                    $features[$code]['same'] = false;
                }
            }
            $i++;
            unset($p);
        }
        if ($features) {
            $feature_model = new shopFeatureModel();
            foreach ($all_features = $feature_model->getByCode(array_keys($features)) as $code => $f) {
                $features[$code] += $f;
            }
        }

        $this->view->assign('features', $features);
        $this->view->assign('products', $products);

        $this->setLayout(new shopFrontendLayout());
        $this->setThemeTemplate('compare.html');
    }
}