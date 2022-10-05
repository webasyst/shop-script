<?php
/**
 * Rename existing presentation and return full data of presentation
 */
class shopProdPresentationRenameController extends waJsonController
{
    public function execute()
    {
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);
        $data = [
            'name' => mb_substr(waRequest::post('name', '', waRequest::TYPE_STRING_TRIM), 0, 255),
        ];
        $this->formatValues($data);

        $presentation_model = new shopPresentationModel();
        $presentation_model->updateById($presentation_id, $data);

        $this->response = $presentation_model->getById($presentation_id, [
            'columns' => true,
        ]);
    }

    protected function formatValues(&$data)
    {
        if (mb_strlen($data['name']) === 0) {
            $data['name'] = _w('(no name)');
        }
    }
}
