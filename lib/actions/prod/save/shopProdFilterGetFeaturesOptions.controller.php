<?php

class shopProdFilterGetFeaturesOptionsController extends waController
{
    public function execute()
    {
        $term = waRequest::post('term', '', waRequest::TYPE_STRING_TRIM);
        $feature_id = waRequest::post('feature_id', null, waRequest::TYPE_INT);

        $feature_model = new shopFeatureModel();
        $feature = $feature_model->getById($feature_id);

        $values = [];
        if (($feature['type'] == shopFeatureModel::TYPE_VARCHAR
            || $feature['type'] == shopFeatureModel::TYPE_COLOR)
            && empty($feature['selectable']) && mb_strlen($term)
        ) {
            $values = $this->getValuesByTerm($feature, $term, 20);
        }

        echo json_encode(array_values($values));
    }

    /**
     * @param array $feature
     * @param string $term
     * @param int $limit
     * @return array
     * @throws waDbException
     */
    public function getValuesByTerm($feature, $term, $limit = null)
    {
        $values_model = shopFeatureModel::getValuesModel($feature['type']);
        $data = [
            'search_value' => "%$term%",
            'feature_id' => $feature['id'],
        ];
        $sql = "SELECT fv.* FROM `{$values_model->getTableName()}` fv
                JOIN `shop_product_features` pf ON pf.feature_value_id = fv.id
                WHERE pf.feature_id = i:feature_id AND fv.value LIKE s:search_value";
        if ($limit > 0) {
            $sql .= " LIMIT i:limit";
            $data['limit'] = $limit;
        }

        $values = $values_model->query($sql, $data)->fetchAll('id');

        foreach ($values as &$value) {
            if ($feature['type'] == shopFeatureModel::TYPE_COLOR) {
                $color_value = new shopColorValue($value);
                $value['code'] = $color_value->convert($color_value::HEX);
            }
            $value["name"] = $value["value"];
            $value["value"] = $value["id"];
        }

        return $values;
    }
}