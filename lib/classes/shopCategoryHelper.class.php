<?php

class shopCategoryHelper
{
    /**
     * @param $options
     * @return array
     * @throws waException
     */
    public function getFilters($options)
    {
        $feature_model = new shopFeatureModel();
        $filters = $feature_model->getFilterFeatures($options, shopFeatureModel::SEARCH_STEP);
        shopFeatureModel::appendTypeNames($filters);

        return $filters;
    }

    /**
     * @param $options
     * @return bool|int
     * @throws waException
     */
    public function getCount($options)
    {
        $feature_model = new shopFeatureModel();
        $count = $feature_model->getFeaturesCount($options);
        return $count;
    }

    /**
     * @param $features
     * @param null $all
     * @return array[]
     * @throws waException
     */
    public function getFeaturesValues($features, $all = null)
    {
        $feature_model = new shopFeatureModel();
        if ($features) {
            $features = $feature_model->getValues($features, $all);
        }

        return $features;
    }

    /**
     * @param $id
     * @return array
     * @throws waException
     */
    public function getTypesId($id)
    {
        $product_collection = new shopProductsCollection("category/{$id}");
        $product_collection->groupBy('type_id');
        $types = $product_collection->getProducts('type_id');

        return waUtils::getFieldValues($types, 'type_id');
    }

    /**
     * @return array
     */
    public function getDefaultFilters()
    {
        return [
            'id'        => 'price',
            'name'      => _w('Price'),
            'type'      => '',
            'code'      => '',
            'type_name' => '',
        ];
    }
}