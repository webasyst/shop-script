<?php

/**
 * Class shopCategoryGetFeaturesController
 *
 * If there is a feature_id it searches only for it
 * If the category === new then it searches for all available features excluding ignored
 * If the category == category_id then looks at which category;
 *      - for dynamic searches for all features ignoring the already saved features and those that are ignored from the query.
 *      - for static searches for all features that are selected based on the products in the category. Also Ignore features from the query.
 */
class shopCategoryGetFeaturesController extends waJsonController
{
    public function execute()
    {
        $feature_id = waRequest::request('feature_id', null, waRequest::TYPE_ARRAY_INT);
        $category = waRequest::request('category', null);
        $category_type = waRequest::request('category_type', null);
        $offset = waRequest::request('offset', 0, waRequest::TYPE_INT);
        $ignore_id = waRequest::request('ignore_id', [], waRequest::TYPE_ARRAY_INT);

        $features = [];

        if ($feature_id) {
            $features = $this->getFeature($feature_id);
        }

        if (!$feature_id && $category) {
            $options_feature = [
                'status' => null,
            ];

            if ($category_type == shopCategoryModel::TYPE_STATIC) {
                $options_feature['type_id'] = $this->getTypesId($category);
            }

            if (!empty($options_feature)) {
                $options_feature['offset'] = $offset;
                $options_feature['frontend'] = true;
                $options_feature['ignore_id'] = $ignore_id;
                $features = $this->getFeatures($options_feature);
            }
        }

        $features = $this->extendFeatures($features);
        $this->response['features'] = $features;
    }

    protected function extendFeatures($features)
    {
        if (empty($features)) {
            return $features;
        }

        foreach ($features as $id => &$feature) {
            $feature['name'] = htmlspecialchars($feature['name']);
            $feature['code'] = htmlspecialchars($feature['code']);

            if (!empty($feature['values'])) {
                $features_values = &$features[$id]['values'];

                if ($feature['type'] === 'color') {
                    foreach ($feature['values'] as $f_id => $f) {
                        if ($f instanceof shopColorValue) {
                            $features_values[$f_id] = $f->getRaw();
                            $features_values[$f_id]['hex'] = $f->hex;
                        }
                    }
                } elseif (substr($feature['type'], 0, 5) === 'range') {
                    foreach ($feature['values'] as $f_id => $f) {
                        if ($f instanceof shopRangeValue) {
                            $features_values[$f_id] = [
                                'begin' => $f->begin_base_unit,
                                'end'   => $f->end_base_unit,
                            ];
                        }
                        $unit_data = shopDimension::getBaseUnit($feature['type']);
                        $feature['unit'] = ifset($unit_data, 'title', '');
                    }
                } else {
                    foreach ($feature['values'] as $f_id => &$f) {
                        $features_values[$f_id] = (string)$f;
                        unset($f);
                    }
                }
            }
            unset($feature);
        }

        return $features;
    }

    protected function getFeature($id)
    {
        return (new shopFeatureModel())->getFeatures('id', $id, 'id', true);
    }

    protected function getTypesId($id)
    {
        return (new shopCategoryHelper())->getTypesId($id);
    }

    protected function getFeatures($options_feature)
    {
        $category_helper = new shopCategoryHelper();

        $features = $category_helper->getFilters($options_feature);
        $features = $category_helper->getFeaturesValues($features, true);

        return $features;
    }
}