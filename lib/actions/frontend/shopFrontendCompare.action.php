<?php

class shopFrontendCompareAction extends waViewAction
{
    public function execute()
    {
        $ids = waRequest::param('id', array(), waRequest::TYPE_ARRAY_INT);
        $products = array();
        foreach ($ids as $id) {
            $products[$id] = new shopProduct($id);
        }


        $feature_model = new shopFeatureModel();
        $all_features = $feature_model->getAll('code');

        $features = array();
        $i = 0;

        $compare_link = wa()->getRouteUrl('/frontend/compare', array('id' => '%ID%'));
        foreach ($products as $p) {
            $temp_ids = $ids;
            unset($temp_ids[array_search($p['id'], $temp_ids)]);
            $p['delete_url'] = str_replace('%ID%', implode(',', $temp_ids), $compare_link);
            foreach ($p['features'] as $f => $v) {
                if (isset($features[$f]) && $features[$f]['same']) {
                    if ($v !== $features[$f]['value']) {
                        $features[$f]['same'] = false;
                    }
                } else {
                    $features[$f] = $all_features[$f];
                    if (!$i) {
                        $features[$f]['same'] = true;
                        $features[$f]['value'] = $v;
                    } else {
                        $features[$f]['same'] = false;
                    }
                }
            }
            foreach ($features as $f => $v) {
                if (!isset($p['features'][$f])) {
                    $features[$f]['same'] = false;
                }
            }
            $i++;
        }

        $this->view->assign('features', $features);
        $this->view->assign('products', $products);

        $this->setLayout(new shopFrontendLayout());
        $this->setThemeTemplate('compare.html');
    }
}