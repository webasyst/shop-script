<?php
/**
 * Rename existing filter and return full data of filter
 */
class shopProdFilterRenameController extends waJsonController
{
    public function execute()
    {
        $filter_id = waRequest::post('filter_id', null, waRequest::TYPE_INT);
        $data = [
            'name' => mb_substr(waRequest::post('name', '', waRequest::TYPE_STRING_TRIM), 0, 255),
        ];
        $this->formatValues($data);

        $filter_model = new shopFilterModel();
        $filter_model->updateById($filter_id, $data);

        $this->response = $filter_model->getById($filter_id, [
            'rules' => true,
        ]);
    }

    protected function formatValues(&$data)
    {
        if (mb_strlen($data['name']) === 0) {
            $data['name'] = _w('(no name)');
        }
    }
}
