<?php

class shopProdCategoryFilterAutocompleteController extends waController
{
    public function execute()
    {
        $term = waRequest::request('term', '', waRequest::TYPE_STRING_TRIM);

        $product_fields = $this->getProductFields($term);
        $features = $this->getFeatures($term);
        echo json_encode(array_values(array_merge($product_fields, $features)));
    }

    /**
     * @param string $term
     * @return array[]
     * @throws waException
     */
    protected function getProductFields($term)
    {
        $category_helper = new shopCategoryHelper();
        $fields = $category_helper->getProductFields([
            'use_key' => false,
        ]);

        foreach ($fields as &$field) {
            $field['type'] = 'product_param';
            $field['data']['display_type'] = 'product';
            if ($field['data']['render_type'] == 'range') {
                $field['data']['options'] = [
                    ['name' => '', 'value' => ''],
                    ['name' => '', 'value' => '']
                ];
            }
        }

        return array_filter($fields, function ($field) use ($term) {
            return mb_stripos($field['name'], $term) !== false;
        });
    }

    protected function getFeatures($term)
    {
        $result = [];

        $model = new shopFeatureModel();
        $options = [
            'term' => $term,
            'ignore_text' => true,
            'ignore_complex_types' => true,
            'count' => false,
            'ignore' => false,
        ];
        $features = $model->getFilterFeatures($options, shopFeatureModel::SEARCH_STEP, 'code', 'count DESC');

        $selectable_values = shopPresentation::addSelectableValues($features);
        $features = shopProdSkuAction::formatFeatures($selectable_values);

        foreach ($features as $feature) {
            $feature['display_type'] = 'feature';
            $result[] = [
                'name' => $feature['name'],
                'code' => $feature['code'],
                'is_negative' => !$feature['selectable'] && ($feature['type'] == shopFeatureModel::TYPE_DOUBLE || mb_strpos($feature['type'], 'range.') === 0 || mb_strpos($feature['type'], 'dimension.') === 0),
                'type' => 'feature',
                'data' => $feature,
            ];
        }

        return $result;
    }
}