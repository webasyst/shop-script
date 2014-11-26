<?php

/**
 * Class shopFeatureValuesConverter
 */
class shopFeatureValuesConverter
{
    const CONTROL_SELECT = 'select';
    const CONTROL_CHECKBOX = 'checkbox';
    const CONTROL_INPUT = 'input';

    static private $ban_map = array(
        'varchar' => 'boolean,2d.*,3d.*',
        'text'    => 'boolean,2d.*,3d.*',
        'boolean' => 'range.*,2d.*,3d.*',
        'double'  => '2d.*,3d.*',
        'range.*' => 'boolean,2d.*,3d.*',
        '2d.*'    => '^2d.*',
        '3d.*'    => '^3d.*',
    );

    /**
     * @var shopFeatureModel
     */
    private $feature_model;

    /**
     *
     * @var shopProductFeaturesModel
     */
    private $product_features_model;

    /**
     * @var shopProductFeaturesSelectableModel
     */
    private $product_features_selectable_model;

    private $feature_id;
    private $to;

    public static function run($feature_id, $to, $control = 'input')
    {
        $converter = new self($feature_id, $to, $control);
        return $converter->convert();
    }

    // all realization method is private

    private function __construct($feature_id, $to, $control = 'input')
    {
        $this->feature_model = new shopFeatureModel();
        $this->product_features_model = new shopProductFeaturesModel();
        $this->product_features_selectable_model = new shopProductFeaturesSelectableModel();
        $this->feature_id = $feature_id;

        if (!is_array($to)) {
            $to = array(
                'type' => (string)$to
            );

            if ($control === self::CONTROL_CHECKBOX) {
                $to['multiple'] = 1;
                $to['selectable'] = 1;
            } elseif ($control === self::CONTROL_SELECT) {
                $to['multiple'] = 0;
                $to['selectable'] = 1;
            } else {
                $to['multiple'] = 0;
                $to['selectable'] = 0;
            }

        } else {
            $to['multiple'] = isset($to['multiple']) ? $to['multiple'] : 0;
            $to['selectable'] = isset($to['selectable']) ? $to['selectable'] : 0;
        }

        $this->to = $to;

    }

    private function convert()
    {
        // models
        $this->feature_model = new shopFeatureModel();
        $feature = $this->feature_model->getById($this->feature_id);
        if (!$feature) {
            return false;
        }

        // check if types are equals
        if ($this->to['type'] == $feature['type'] &&
            $this->to['selectable'] == $feature['selectable'] &&
            $this->to['multiple'] == $feature['multiple']
        ) {
            return false;
        }

        // check for ban

        $ban_rule = '';
        foreach (self::$ban_map as $type_from => $list) {
            if ($type_from === $feature['type']) {
                $ban_rule = $list;
                break;
            } elseif (substr($type_from, -1) === '*') {
                $type_from = substr($type_from, 0, -2);
                $f_type = explode('.', $feature['type']);
                if ($type_from === $f_type[0]) {
                    $ban_rule = $list;
                    break;
                }
            }
        }
        if ($ban_rule === '*') {
            return false;
        } elseif (substr($ban_rule, 0, 1) === '^') {
            $not_banned = substr($ban_rule, 1);
            if (substr($not_banned, -1) === '*') {
                $not_banned = substr($not_banned, 0, -2);
                $type_to = explode('.', $this->to['type']);
                if ($not_banned !== $type_to[0]) {
                    return false;
                }
            } elseif ($not_banned !== $this->to['type']) {
                return false;
            }
        } else {
            $ban_list = explode(',', $ban_rule);
            foreach ($ban_list as $ban_type) {
                if ($ban_type === $this->to['type']) {
                    return false;   // ban this conversion
                } elseif (substr($ban_type, -1) === '*') {
                    $ban_type = substr($ban_type, 0, -2);
                    $type_to = explode('.', $this->to['type']);
                    if ($ban_type === $type_to[0]) {
                        return false;   // ban this conversion
                    }
                }
            }
        }

        $this->_convert($feature, $this->to);
        return true;
    }

    private function isMultidimensional($feature)
    {
        $s = substr($feature['type'], 0, 2);
        if ($s === '2d' || $s === '3d') {
            return true;
        }
        return false;
    }

    private function getChildren($feature)
    {
        return $this->feature_model->getByField('parent_id', $feature['id'], 'id');
    }

    private function getValues($feature, $to)
    {
        $values = array();
        if (!$this->isMultidimensional($feature)) {
            foreach ($this->feature_model->getValues(array($feature)) as $f) {
                foreach ($f['values'] as $k => $v) {
                    if ($feature['type'] === 'boolean' && ($to['type'] === 'double' || $to['type'] === 'range.double')) {
                        $values[-$k] = $v->value;
                    } else {
                        $values[-$k] = (string)$v;
                    }
                }
            }
        } else {
            $features = $this->getChildren($feature);
            foreach ($this->feature_model->getValues($features) as $f) {
                foreach ($f['values'] as $k => $v) {
                    $values[-$k] = array(
                        'feature_id' => $f['id'],
                        'value'      => (string)$v
                    );
                }
            }
        }

        return $values;
    }

    private function changeType($feature, $to)
    {
        $feature_id = $feature['id'];
        $type = $feature['type'];
        if (!$this->isMultidimensional($feature)) {
            // change type of feature
            $feature['type'] = $to['type'];
            $feature['selectable'] = $to['selectable'];
            $feature['multiple'] = $to['multiple'];
            $this->feature_model->updateById(
                $feature_id,
                array(
                    'type'       => $to['type'],
                    'selectable' => $to['selectable'],
                    'multiple'   => $to['multiple']
                )
            );

            // remove old values
            $value_model = shopFeatureModel::getValuesModel($type);
            $value_model->deleteByField('feature_id', $feature['id']);

        } else {
            $parts = explode('.', $to['type']);
            if ($parts[1] !== 'double' && count($parts) == 2) {
                $to['type'] = $parts[0].'.dimension.'.$parts[1];
            }
            $feature['type'] = $to['type'];
            $feature['selectable'] = $to['selectable'];
            $feature['multiple'] = $to['multiple'];
            $this->feature_model->updateById(
                $feature_id,
                array(
                    'type'       => $to['type'],
                    'selectable' => $to['selectable'],
                    'multiple'   => $to['multiple']
                )
            );
            $c_type = substr($to['type'], 3);
            $children = $this->getChildren($feature);
            foreach ($children as $c) {
                $this->feature_model->updateById(
                    $c['id'],
                    array(
                        'type'       => $c_type,
                        'selectable' => $to['selectable'],
                        'multiple'   => $to['multiple']
                    )
                );
            }

            // remove old values
            $parts = explode('.', $type);
            $value_model = shopFeatureModel::getValuesModel($parts[1]);
            $value_model->deleteByField('feature_id', array_keys($children));

        }
        return $feature;
    }

    private function setValues($feature, $values)
    {
        if (!$this->isMultidimensional($feature)) {
            return $this->feature_model->setValues($feature, $values, false);
        } else {
            $type = $feature['type'];
            $parts = explode('.', $type);
            $values_model = shopFeatureModel::getValuesModel($parts[1]);
            $children = $this->getChildren($feature);

            $data = array();
            $sort = 0;
            foreach ($values as $id => $value) {
                $val = $value['value'];
                $f_id = $value['feature_id'];
                $row = &$data[];
                $row = $values_model->addValue($f_id, $val, $id, $children[$f_id]['type'], ++$sort);
                $row['feature_id'] = $f_id;
            }
            foreach ($children as $c) {
                $this->feature_model->recount($c);
            }

            return $data;
        }
    }

    private function bindToProducts($feature, $values)
    {
        if (is_array($feature)) {
            $feature_id = $feature['id'];
        } else {
            $feature_id = (int)$feature;
            $feature = $this->feature_model->getById($feature_id);
        }

        $is_multidimensional = $this->isMultidimensional($feature);

        // bind products with values of new feature
        foreach ($values as $v) {

            if ($feature['type'] !== 'boolean') {
                if (isset($v['original_id'])) {
                    $new_id = $v['original_id'];
                } else {
                    $new_id = $v['id'];
                }
                if (isset($v['original_id'])) {
                    $old_id = -$v['id'];
                } else {
                    $old_id = -$v['insert_id'];
                }
            } else {
                $old_id = -$v['id'];
                $new_id = $v['value'];
            }

            if (!$is_multidimensional) {
                $key = array(
                    'feature_id'       => $feature_id,
                    'feature_value_id' => $old_id,
                );
                $value = array(
                    'feature_value_id' => $new_id,
                );
            } else {
                $key = array(
                    'feature_id'       => $v['feature_id'],
                    'feature_value_id' => $old_id,
                );
                $value = array(
                    'feature_value_id' => $new_id,
                );
            }

            $this->product_features_model->updateByField($key, $value);

            $key['value_id'] = $key['feature_value_id'];
            unset($key['feature_value_id']);
            $value['value_id'] = $value['feature_value_id'];
            unset($value['feature_value_id']);

            $this->product_features_selectable_model->updateByField($key, $value);
        }
    }

    private function _convert($feature, $to)
    {
        $feature_id = $feature['id'];

        // change only multiple/selectable flags
        if ($to['type'] == $feature['type']) {
            $this->feature_model->updateById(
                $feature_id,
                array(
                    'multiple'   => $to['multiple'],
                    'selectable' => $to['selectable']
                )
            );
        } else {

            // get all features values
            $values = $this->getValues($feature, $to);

            // change type of feature (with removing old values)
            $feature = $this->changeType($feature, $to);

            // reset values taking into account new type
            $new_values = $this->setValues($feature, $values);

            // bind new values to products
            $this->bindToProducts($feature, $new_values);
        }
    }
}
