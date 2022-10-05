<?php
/**
 * Copy existing filter into a new one and return full data of new filter
 */
class shopProdFilterDuplicateController extends waJsonController
{
    public function execute()
    {
        $filter_id = waRequest::post('filter_id', null, waRequest::TYPE_INT);
        $data = [
            'name' => mb_substr(waRequest::post('name', '', waRequest::TYPE_STRING_TRIM), 0, 255),
        ];
        $this->formatValues($data);
        $filter_model = new shopFilterModel();
        $new_id = $filter_model->duplicate($filter_id, shopFilterModel::DUPLICATE_MODE_CREATE, $data);
        $this->response = $filter_model->getById($new_id, [
            'rules' => true,
        ]);
    }

    protected function formatValues(&$data)
    {
        if (mb_strlen($data['name']) === 0) {
            $data['name'] = _w('(new filter)');
        }
    }
}
