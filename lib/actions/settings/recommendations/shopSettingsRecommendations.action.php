<?php

class shopSettingsRecommendationsAction extends waViewAction
{
    public function execute()
    {
        $type_model = new shopTypeModel();
        $types = $type_model->getAll('id');

        $category_model = new shopCategoryModel();
        $categories = $category_model->getFullTree('id, name, depth', true);

        $features_model = new shopFeatureModel();
        $features = $features_model->getAll('id');

        $data = array();

        foreach ($types as $type_id => $type) {
            $data[$type_id]['price'] = array('feature' => 'price');
            $data[$type_id]['tag'] = array('feature' => 'tag');
        }

        $type_features_model = new shopTypeFeaturesModel();
        $rows = $type_features_model->getAll();
        foreach ($rows as $row) {
            if (isset($features[$row['feature_id']])) {
                $code = $features[$row['feature_id']]['code'];
                $data[$row['type_id']][$code] = array(
                    'feature'    => $code,
                    'feature_id' => $row['feature_id']
                );
            }
        }

        $type_upselling_model = new shopTypeUpsellingModel();
        $rows = $type_upselling_model->getAll();
        foreach ($rows as $row) {
            $data[$row['type_id']][$row['feature']] = array(
                'feature_id' => $row['feature_id'],
                'feature'    => $row['feature'],
                'cond'       => $row['cond'],
                'value'      => $row['value']
            );
        }

        foreach ($data as & $row) {
            $row = array_values($row);
        }
        unset($row);

        foreach ($types as & $type) {
            if ($type['upselling']) {
                $type['upselling_html'] = self::getConditionHTML($data[$type['id']], $features);
            }
        }
        unset($type);

        $fids = array();
        foreach ($features as $f_key => $f) {
            $features[$f_key]['selectable'] = (int)$f['selectable'];
            $features[$f_key]['multiple'] = (int)$f['multiple'];
            if ($f['selectable']) {
                $fids[$f['id']] = $f;
            }
        }

        if ($fids) {
            $fids = $features_model->getValues($fids);
            foreach ($fids as $feature_id => $f) {
                foreach ($f['values'] as $value_id => $value) {
                    $features[$feature_id]['values'][] = array($value_id, $value);
                }
            }
            unset($fids);
        }

        $this->view->assign(array(
            'types'      => $types,
            'categories' => $categories,
            'features'   => $features,
            'data'       => $data
        ));
    }

    /**
     * @param $data
     * @param array $features строка 1
     строка 2
     * @return string
     */
    public static function getConditionHTML($data, $features = array())
    {
        $result = array();

        foreach ($data as $row) {
            if (empty($row['cond'])) {
                continue;
            }
            if (!empty($row['feature_id'])) {
                if ($features) {
                    $html = $features[$row['feature_id']]['name'];
                } else {
                    $html = $row['feature_name'];
                }
            } else {
                if ($row['feature'] == 'price') {
                    $html = _w('Price');
                } elseif ($row['feature'] == 'tag') {
                    $html = _w('Tags');
                } else {
                    continue;
                }
            }
            $html .= ' ';
            switch ($row['cond']) {
                case 'between':
                    $v = explode(',', $row['value']);
                    $html .= '<span class="s-plus-minus">'.($v[1] > 0 ? '+' : '').$v[1].'%<br>'.($v[0] > 0 ? '+' : '').$v[0].'%</span>';
                    break;
                case 'contain':
                    $html .= $row['cond'].' "'.$row['value'].'"';
                    break;
                case 'same':
                    $html .= _w('matches base product value');
                    break;
                case 'notsame':
                    $html .= _w('differs from base product value');
                    break;
                case 'all':
                case 'any':
                case 'is':
                    if ($row['cond'] == 'any') {
                        $html .= _w('any of selected values (OR)');
                    } elseif ($row['cond'] == 'all') {
                        $html .= _w('all of selected values (AND)');
                    } else {
                        $html .= $row['cond'];
                    }
                    $html .= ' ';

                    $feature_values_model = shopFeatureModel::getValuesModel($features ? $features[$row['feature_id']]['type'] : $row['feature_type']);
                    if (strpos($row['value'], ',') !== false) {
                        $value_ids = explode(',', $row['value']);
                        $values = $feature_values_model->getById($value_ids);
                        foreach ($values as & $v) {
                            $v = $v['value'];
                        }
                        unset($v);
                        $html .= implode(', ', $values);
                    } else {
                        $v = $feature_values_model->getById($row['value']);
                        $html .= $v['value'];
                    }
                    break;
            }
            $result[] = $html;
        }
        return implode('; ', $result);
    }

}
