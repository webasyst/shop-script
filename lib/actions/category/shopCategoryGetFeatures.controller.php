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
        $offset = waRequest::request('offset', 0, waRequest::TYPE_INT);
        $ignore_id = waRequest::request('ignore_id', [], waRequest::TYPE_ARRAY_INT);

        $feature_model = new shopFeatureModel();
        $features = [];

        if ($feature_id) {
            $features = $feature_model->getFeatures('id', $feature_id, 'id', true);
        }

        if (!$feature_id && $category) {
            $options_feature = [];

            if ($category === 'new') {
                $options_feature = [
                    'ignore_id' => $ignore_id,
                    'frontend'  => true,
                    'offset'    => $offset,
                    'status'    => null,
                ];
            } else {
                $category_model = new shopCategoryModel();
                $settings = $category_model->getById($category);

                if ($settings['type'] == shopCategoryModel::TYPE_DYNAMIC) {
                    $options_feature = [
                        'ignore_id' => $ignore_id,
                        'frontend'  => true,
                        'offset'    => $offset,
                        'status'    => null,
                    ];
                }

                if ($settings['type'] == shopCategoryModel::TYPE_STATIC) {
                    $options_feature = array(
                        'type_id'   => $this->getTypesId($category),
                        'ignore_id' => $ignore_id,
                        'frontend'  => true,
                        'offset'    => $offset,
                    );
                }
            }

            if ($options_feature) {
                $features = $this->getFeaturesWithValues($options_feature);
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

    protected function getTypesId($id)
    {
        $product_collection = new shopProductsCollection("category/{$id}");
        $product_collection->groupBy('type_id');
        $types = $product_collection->getProducts('type_id');

        return waUtils::getFieldValues($types, 'type_id');
    }

    protected function getFeaturesWithValues($options)
    {
        $feature_model = new shopFeatureModel();

        $features = $feature_model->getFilterFeatures($options, 20);
        $features = $feature_model->getValues($features, true);
        shopFeatureModel::appendTypeNames($features);

        return $features;
    }
}