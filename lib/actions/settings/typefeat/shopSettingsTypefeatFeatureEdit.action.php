<?php
/**
 * Single feature editor dialog HTML.
 */
class shopSettingsTypefeatFeatureEditAction extends waViewAction
{
    public function execute()
    {
        $feature_code = waRequest::request('code', '', waRequest::TYPE_STRING);
        $feature = $this->getFeature($feature_code);

        $selected_type_id = null;
        if (empty($feature['id'])) {
            $selected_type_id = waRequest::request('type_id');
        }

        $types = $this->getTypes($feature, $selected_type_id);

        list($feature['kind'], $feature['format']) = $this->getKindAndFormat($feature);
        $feature['values'] = $this->formatValues($feature);

        list($can_disable_sku, $sku_values_count) = self::analyzeSkus($feature);

        $all_types_is_checked = !empty($feature['types'][0]);
        if (empty($feature['id']) && $selected_type_id === 'all_existing') {
            $all_types_is_checked = true;
        }

        $this->view->assign([
            'can_disable_sku' => $can_disable_sku,
            'sku_values_count' => $sku_values_count,
            'kinds' => $this->getAllFeatureKinds($feature),
            'formats' => $this->getAllFeatureFormats($feature),
            'all_types_is_checked' => $all_types_is_checked,
            'selected_type' => ifset($types, $selected_type_id, null),
            'feature' => $feature,
            'types' => $types,
        ]);
    }

    protected function getFeature($feature_code)
    {
        $feature_model = new shopFeatureModel();
        $feature = $feature_model->getByCode($feature_code);

        if ($feature) {
            $features = [$feature];
            $features = $feature_model->getValues($features, null);
            $type_features_model = new shopTypeFeaturesModel();
            $type_features_model->fillTypes($features);
            $feature = reset($features);
        } else {
            $feature = $feature_model->getEmptyRow();
            $feature['type'] = waRequest::request('mode', 'varchar', 'string');
            $feature['available_for_sku'] = waRequest::request('available_for_sku', 0, 'int');
            $feature['values'] = [];
            $feature['types'] = [];
        }

        $feature['visible_in_frontend'] = $feature['status'] == 'public';

        return $feature;
    }

    protected function formatValues($feature)
    {
        if (empty($feature['selectable'])) {
            return [];
        }

        $values = [];

        foreach($feature['values'] as $i => $fv) {
            if ($fv instanceof shopColorValue) {
                $values[] = [
                    'id'    => $fv['id'],
                    'value' => $fv['value'],
                    'unit'  => null,
                    'code'  => ifempty(ref($fv['hex']), '#FFFFFF'),
                ];
            } else if ($fv instanceof shopDimensionValue) {
                $values[] = [
                    'id'    => $fv['id'],
                    'value' => $fv['value'],
                    'unit'  => $fv['unit'],
                    'code'  => null,
                ];
            } else if (is_scalar($fv)) {
                $values[] = [
                    'id'    => $i,
                    'value' => $fv,
                    'unit'  => null,
                    'code'  => null,
                ];
            } else {
                // Not supported, but let's do all we can
                $values[] = [
                    'id'    => $i,
                    'value' => (string) $fv,
                    'unit'  => null,
                    'code'  => null,
                ];
            }
        }

        return $values;
    }

    public function getKindAndFormat($feature)
    {
        // Is it a selector or a checklist?
        if ($feature['selectable']) {
            if ($feature['multiple']) {
                $format = 'checklist';
            } else {
                $format = 'selector';
            }

            switch($feature['type']) {
                case 'color':
                    $kind = 'color';
                    break;
                case 'varchar':
                    $kind = 'text';
                    break;
                case 'double':
                    $kind = 'numeric';
                    break;
                default:
                    $type = explode('.', $feature['type'], 2);
                    if ($type[0] == 'dimension' && !empty($type[1])) {
                        $kind = $type[1];
                    } else {
                        return ['unknown', 'none'];
                    }
                    break;
            }

            return [$kind, $format];
        }

        switch($feature['type']) {
            case 'varchar':
            case 'gtin':
                return ['text', 'input'];
            case 'text':
                return ['text', 'textarea'];
            case 'double':
                return ['numeric', 'number'];
            case 'boolean':
                return ['boolean', 'none'];
            case 'color':
                return ['color', 'value'];
            case 'date':
                return ['date', 'date'];
            case '3d.length':
            case '3d.dimension.length':
                return ['volume', '3d'];
            case '2d.dimension.length':
            case '2d.length':
                return ['area', '2d'];
        }

        // Everything else must contain a dot at this point
        $type = explode('.', $feature['type'], 2);
        if (empty($type[1])) {
            return ['unknown', 'none'];
        }

        // Range format?
        if ($type[0] == 'range') {
            if ($type[1] == 'double') {
                return ['numeric', 'range'];
            } else {
                return [$type[1], 'range'];
            }
        }

        // Dimension?
        if ($type[0] == 'dimension') {
            return [$type[1], 'number'];
        }

        // Number x Number? Number x Number x Number?
        if ($type[0] == '2d' || $type[0] == '3d') {
            if ($type[1] == 'double') {
                return ['numeric', $type[0]];
            }
            $subtype = explode('.', $type[1], 2);
            if ($subtype[0] == 'dimension') {
                return [$subtype[1], $type[0]];
            } else {
                return [$type[1], $type[0]];
            }
        }

        return ['unknown', 'none'];
    }

    protected function getTypes($feature, $selected_type_id)
    {
        $type_model = new shopTypeModel();
        $types = $type_model->getAll('id');
        foreach($types as &$t) {
            $t['is_checked'] = !empty($feature['types'][$t['id']]) || $selected_type_id == $t['id'];
        }
        unset($t);
        return $types;
    }

    protected function getAllFeatureKinds($feature)
    {
        $result = shopFeatureModel::getAllFeatureKinds();

        // Do not allow to change type of special feature 'weight'
        if (!empty($feature['id']) && ifset($feature, 'code', '') == 'weight' && isset($result['weight'])) {
            $result['weight']['formats'] = ['number'];
            $result = [
                'weight' => $result['weight'],
            ];
        }

        if (!empty($feature['kind'])) {
            if (!isset($result[$feature['kind']])) {
                // Show this option in selector only when feature has unknown type
                $result[$feature['kind']] = [
                    'title' => _w('Unknown feature type').' ('.htmlspecialchars($feature['kind']).')',
                    'formats' => [],
                    'unable_to_save' => true,
                ];
            } else {
                if (!empty($feature['format'])) {
                    // Show unknown format in selector even if feature has pre-saved format that is not allowed for its kind
                    if ($result[$feature['kind']]['formats'] && !in_array($feature['format'], $result[$feature['kind']]['formats'])) {
                        $result[$feature['kind']]['formats'][] = $feature['format'];
                    }
                }
            }
        }

        // Will be used later - see below. Have to read it here because the following loop
        // can remove 'length' from the list of possible feature kinds.
        $length_dimensions = ifset($result, 'length', 'dimensions', null);

        foreach($result as $id => &$kind) {
            $kind['id'] = $id;

            // Disable certain kinds and formats in selector when existing feature is being edited.
            // Since we can't convert anything into anything - some options are restricted.
            if (!empty($feature['id'])) {

                $restore_empty_formats = false;
                if (empty($kind['formats'])) {
                    $restore_empty_formats = true;
                    $kind['formats'] = ['']; // special case e.g. for boolean
                }

                foreach($kind['formats'] as $i => $format_id) {
                    $feature_to = [];
                    list(
                        $feature_to['selectable'],
                        $feature_to['multiple'],
                        $feature_to['type']
                    ) = shopSettingsTypefeatFeatureSaveController::parseTypeByKindAndFormat([
                        'kind' => $kind['id'],
                        'format' => $format_id,
                    ]);
                    if (!shopFeatureValuesConverter::isConvertible($feature, $feature_to)) {
                        unset($kind['formats'][$i]);
                    }
                }

                if (empty($kind['formats'])) {
                    unset($result[$id]);
                } else if ($restore_empty_formats) {
                    $kind['formats'] = []; // special case
                } else {
                    $kind['formats'] = array_values($kind['formats']); // fix holes in indices
                }
            }
        }
        unset($kind);

        // Values for default unit selector.
        // All feature kinds except area and volume use their normal feature dimensions for default unit.
        // Area and volume use their dimensions unless selected format is 2d or 3d.
        // Area and colume for 2d and 3d use dimensions of length.
        if ($length_dimensions) {
            if (isset($result['area'])) {
                $result['area']['length_dimensions'] = $length_dimensions;
            }
            if (isset($result['volume'])) {
                $result['volume']['length_dimensions'] = $length_dimensions;
            }
        }

        return $result;
    }

    protected function getAllFeatureFormats($feature)
    {
        $result = shopFeatureModel::getAllFeatureFormats();

        // Unknown types keep as is
        if (!empty($feature['format']) && !isset($result[$feature['format']])) {
            $result[$feature['format']] = [
                'id' => $feature['format'],
                'title' => _w('Unknown format').' ('.$feature['format'].')',
                'values' => (bool)$feature['selectable'],
            ];
        }

        return $result;
    }

    /**
     * In some cases, depending how feature is used in existing products and SKUs,
     * user is not allowed to disable `available_for_sku` checkbox in the editor,
     * or has to be warned about data loss. This method figures this out.
     */
    public static function analyzeSkus($feature)
    {
        // Can not disable SKU checkbox for builtin features
        if ($feature['builtin']) {
            return [false, 0];
        }

        // Can do anything with new feature or when feature
        // is not available for SKU
        if (empty($feature['id']) || empty($feature['available_for_sku'])) {
            return [true, 0];
        }

        // Unable to turn off values for SKU when there is at least one product
        // with SKUs generated based off of this feature.
        $product_features_selectable_model = new shopProductFeaturesSelectableModel();
        $sku_selectable_count = $product_features_selectable_model->countByField('feature_id', $feature['id']);
        if ($sku_selectable_count > 0) {
            return [false, 0];
        }

        // Otherwise user is allowed to switch off the SKUs checkbox.
        // But when there are SKUs with values of this feature, editor has
        // to show warning that disabling SKU checkbox will delete some data.
        // Therefore count such values.

        // Have to count both feature itself, as well as its child sub-features
        $feature_model = new shopFeatureModel();
        $ids = array_keys($feature_model->select('id')->where('parent_id='.intval($feature['id']))->fetchAll('id'));
        $ids[] = $feature['id'];

        $product_features_model = new shopProductFeaturesModel();
        $sku_values_count = $product_features_model->countSkusByFeature($ids);

        return [true, $sku_values_count];
    }
}
