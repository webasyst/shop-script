<?php

class shopSettingsRecommendationsSaveController extends waJsonController
{
    protected $type_id;
    /**
     * @var shopTypeModel
     */
    protected $type_model;
    protected $type;

    public function execute()
    {
        $setting = waRequest::get('setting');
        $this->type_id = waRequest::post('type_id');
        $this->type_model = new shopTypeModel();
        $this->type = $this->type_model->getById($this->type_id);
        if (!$this->type) {
            throw new waException("Type not found");
        }
        if ($setting == 'cross-selling') {
            $this->saveCrossSelling();
        } elseif ($setting == 'upselling') {
            $this->saveUpSelling();
        } else {
            throw new waException("Unknown setting");
        }

    }

    protected function saveCrossSelling()
    {
        $value = waRequest::post('value');
        $this->type_model->updateById($this->type_id, array('cross_selling' => $value));
    }

    protected function saveUpSelling()
    {
        $value = waRequest::post('value');
        $this->type_model->updateById($this->type_id, array('upselling' => $value));

        $type_upselling_model = new shopTypeUpsellingModel();
        $type_upselling_model->deleteByField('type_id', $this->type_id);

        if ($value) {
            $rows = array();
            $data = waRequest::post('data', array());
            foreach ($data as $feature => $row) {
                if (!isset($row['feature'])) {
                    continue;
                }
                $rows[] = array(
                    'type_id' => $this->type_id,
                    'feature' => $feature,
                    'feature_id' => isset($row['feature_id']) ? $row['feature_id'] : null,
                    'cond' => $row['cond'],
                    'value' => isset($row['value']) ? (is_array($row['value']) ? implode(',', $row['value']) : $row['value']) : '',
                );
            }
            if ($rows) {
                $type_upselling_model->multipleInsert($rows);
            }

            $this->response['type_id'] = $this->type_id;
            $this->response['data'] = array(
                'price' => array('feature' => 'price'),
                'tag' => array('feature' => 'tag'),
                'type_id' => array('feature' => 'type_id'),
            );
            $type_features_model = new shopTypeFeaturesModel();
            $rows = $type_features_model->getByType($this->type_id);
            foreach ($rows as $row) {
                $this->response['data'][$row['code']] = array(
                    'feature' => $row['code'],
                    'feature_id' => $row['feature_id']
                );
            }
            $data = $type_upselling_model->getByType($this->type_id);
            foreach ($data as $row) {
                $this->response['data'][$row['feature']] = array(
                    'feature_id' => $row['feature_id'],
                    'feature' => $row['feature'],
                    'cond' => $row['cond'],
                    'value' => $row['value']
                );
            }
            $this->response['html'] = shopSettingsRecommendationsAction::getConditionHTML($data);
            $this->response['data'] = array_values($this->response['data']);
        }
    }
}
