<?php

class shopSettingsRecommendationsAction extends waViewAction
{
    public function execute()
    {
        // Fetch info about product features
        $features = $this->getFeaturesData();

        // Fetch info about product types
        $data = array(); // type_id => list of features
        $type_values = array(); // list of pairs  [type_id, type_name]
        $type_model = new shopTypeModel();
        $types = $type_model->getAll('id');
        foreach ($types as $type_id => $type) {
            $type_values[] = array($type_id, $type['name']);
            $data[$type_id]['price'] = array('feature' => 'price');
            $data[$type_id]['type_id'] = array('feature' => 'type_id');
            $data[$type_id]['tag'] = array('feature' => 'tag');
        }
        unset($type);

        // Pseudo-feature to match by product type
        $features['type_id'] = array(
            'name' => _w('Type'),
            'type' => 'varchar',
            'selectable' => 1,
            'values' => $type_values,
        );

        // Which features are enabled for which types
        $type_features_model = new shopTypeFeaturesModel();
        $rows = $type_features_model->getAll();
        foreach ($rows as $row) {
            if (isset($features[$row['feature_id']])) {
                $code = $features[$row['feature_id']]['code'];
                $data[$row['type_id']][$code] = array(
                    'feature'    => $code,
                    'feature_id' => $row['feature_id'],
                );
            }
        }

        // Which features are set up for upselling for which types
        $type_upselling_model = new shopTypeUpsellingModel();
        $rows = $type_upselling_model->getAll();
        foreach ($rows as $row) {
            $data[$row['type_id']][$row['feature']] = array(
                'feature_id' => $row['feature_id'],
                'feature'    => $row['feature'],
                'cond'       => $row['cond'],
                'value'      => $row['value'],
            );
        }

        // Fetch product categories
        $category_model = new shopCategoryModel();
        $categories = $category_model->getFullTree('id, name, depth', true);

        // Prepare stuff for HTML and JS
        foreach ($types as $type_id => &$type) {
            if ($type['upselling']) {
                $type['upselling_html'] = self::getConditionHTML($data[$type['id']], $features);
            }
        }
        unset($type);
        foreach ($data as &$row) {
            $row = array_values($row);
        }
        unset($row);

        $this->view->assign(array(
            'types'      => $types,
            'categories' => $categories,
            'features'   => $features,
            'data'       => $data,
        ));
    }

    /**
     * Helper to format upselling condition for HTML.
     *
     * @param $data
     * @param array $features
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
                } elseif ($row['feature'] == 'type_id') {
                    $html = _w('Type');
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
                        $html .= _w($row['cond']);
                    }
                    $html .= ' ';

                    if ($row['feature'] == 'type_id') {
                        $type_model = new shopTypeModel();
                        $type = $type_model->getById($row['value']);
                        $html .= $type['name'];
                    } else {
                        $feature_values_model = shopFeatureModel::getValuesModel($features ? $features[$row['feature_id']]['type'] : $row['feature_type']);
                        if (strpos($row['value'], ',') !== false) {
                            $value_ids = explode(',', $row['value']);
                            $values = array();
                            foreach ($value_ids as $v_id) {
                                $values[] = $feature_values_model->getFeatureValue($v_id);
                            }
                            $html .= implode(', ', $values);
                        } else {
                            $html .= $feature_values_model->getFeatureValue($row['value']);
                        }
                    }
                    break;
            }
            $result[] = $html;
        }
        return implode('; ', $result);
    }

    protected function getFeaturesData()
    {
        $feature_model = new shopFeatureModel();
        $features = $feature_model->getAll('id');

        $fids = array();
        foreach($features as &$f) {
            $f['selectable'] = (int)$f['selectable'];
            $f['multiple'] = (int)$f['multiple'];
            if ($f['type'] == shopFeatureModel::TYPE_DIVIDER) {
                unset($features[$f['id']]);
            }
            if ($f['selectable']) {
                $fids[$f['id']] = $f;
                $f['values'] = array();
            }
        }
        unset($f);

        // Some features have option names, get them
        if ($fids) {
            foreach ($feature_model->getValues($fids) as $feature_id => $f) {
                foreach ($f['values'] as $value_id => $value) {
                    $features[$feature_id]['values'][] = array($value_id, (string)$value);
                }
            }
            unset($fids);
        }

        return $features;
    }
}

