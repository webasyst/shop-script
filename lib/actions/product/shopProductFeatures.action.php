<?php
class shopProductFeaturesAction extends waViewAction
{
    public function execute()
    {
        if ($id = waRequest::get('id', 0, waRequest::TYPE_INT)) {
            #load product

            $this->view->assign('product', $product = new shopProduct($id));

            #load product types
            $type_model = new shopTypeModel();
            $this->view->assign('product_types', $product_types = $type_model->getAll($type_model->getTableId(), true));
            if ($param = waRequest::request('param', array(), waRequest::TYPE_ARRAY_INT)) {
                $type_id = reset($param);
                if (!isset($product_types[$type_id])) {
                    $type_id = $product->type_id;
                }
            } else {
                $type_id = $product->type_id;
            }

            $this->view->assign('type_id', $type_id);

            #load feature's values
            $model = new shopFeatureModel();

            $changed_features = array();
            if ($data = waRequest::post('product')) {
                $changed_features = (empty($data['features']) || !is_array($data['features'])) ? array() : $data['features'];
                foreach ($changed_features as $code => $value) {
                    if (isset($product->features[$code])) {
                        if (is_array($value)) {
                            $intersect = array_unique(array_merge($value, (array) $product->features[$code]));
                            if (count($value) == count($intersect)) {
                                unset($changed_features[$code]);
                            }
                        } elseif ($value === $product->features[$code]) {
                            unset($changed_features[$code]);
                        }
                    }
                }
            }
            #load changed feature's values
            $this->view->assign('changed_features', $changed_features);
            $codes = array_keys($product->features);
            foreach ($changed_features as $code => $value) {
                if ($value !== '') {
                    $codes[] = $code;
                }
            }
            $codes = array_unique($codes);

            $features = $model->getByType($type_id, 'code');
            foreach ($features as $code => & $feature) {
                $feature['internal'] = true;
                $key = array_search($code, $codes);
                if ($key !== false) {
                    unset($codes[$key]);
                }
            }
            unset($feature);

            if ($codes) {
                $features += $model->getByField('code', $codes, 'code');
            }

            foreach ($features as $code => & $feature) {
                $feature['feature_id'] = intval($feature['id']);
            }
            unset($feature);
            $features = $model->getValues($features);

            $this->view->assign('features', $features);
        }
    }
}
