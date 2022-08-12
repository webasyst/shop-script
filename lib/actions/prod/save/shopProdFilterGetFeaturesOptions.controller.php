<?php

class shopProdFilterGetFeaturesOptionsController extends waJsonController
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

        $this->response['values'] = $values;
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
        $op = $this->getSearchCondition();
        $data = [
            'search_value' => "%$term%",
            'feature_id' => $feature['id'],
        ];
        $sql = "SELECT fv.* FROM {$values_model->getTableName()} fv
                JOIN shop_product_features pf ON pf.feature_value_id = fv.id
                WHERE fv.feature_id = i:feature_id AND ($op)";
        if ($limit > 0) {
            $sql .= " LIMIT i:limit";
            $data['limit'] = $limit;
        }
        return $this->query($sql, $data)->fetchAll('id');
    }
}