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
        foreach ($products as $p) {
            foreach ($p['features'] as $f => $v) {
                if (isset($features[$f]) && $features[$f]['same']) {
                    if ($v !== $features[$f]['value']) {
                        $features[$f]['same'] = false;
                    }
                } else {
                    $features[$f] = $all_features[$f];
                    $features[$f]['same'] = $i ? false: true;
                    $features[$f]['value'] = $v;
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