<?php
/**
 * Copy existing presentation into a new one and return full data of new presentation
 */
class shopProdPresentationDuplicateController extends waJsonController
{
    public function execute()
    {
        $presentation_id = waRequest::post('presentation', null, waRequest::TYPE_INT);
        $data = [
            'name' => mb_substr(waRequest::post('name', '', waRequest::TYPE_STRING_TRIM), 0, 255),
        ];
        $this->formatValues($data);
        $presentation_model = new shopPresentationModel();
        $new_id = $presentation_model->duplicate($presentation_id, shopPresentationModel::DUPLICATE_MODE_CREATE, $data);
        $this->response = $presentation_model->getById($new_id, [
            'columns' => true,
        ]);
    }

    protected function formatValues(&$data)
    {
        if (mb_strlen($data['name']) === 0) {
            $data['name'] = _w('(new saved view)');
        }
    }
}
