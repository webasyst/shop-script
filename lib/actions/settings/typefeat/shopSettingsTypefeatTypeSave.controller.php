<?php
/**
 * Accept POST from type editor dialog to save new or existing product type.
 * New type can be created from a template or name + icon form. This also adds features.
 */
class shopSettingsTypefeatTypeSaveController extends waJsonController
{
    public function execute()
    {
        $model = new shopTypeModel();

        $data = array();
        $id = waRequest::post('id', 0, waRequest::TYPE_INT);

        if (!$id && waRequest::post('source') == 'template') {
            $data = $model->insertTemplate(waRequest::post('template'), true);
        } else {
            $data['name'] = waRequest::post('name');
            $data['icon'] = waRequest::post('icon_url', false, waRequest::TYPE_STRING_TRIM);
            if (empty($data['icon'])) {
                $data['icon'] = waRequest::post('icon', 'icon.box', waRequest::TYPE_STRING_TRIM);
            }

            if (!empty($id)) {
                $model->updateById($id, $data);
                $data['id'] = $id;
            } else {
                $data['sort'] = $model->select('MAX(sort)+1 as max_sort')->fetchField('max_sort');
                $data['id'] = $model->insert($data);
            }
        }

        $this->response = $data;
    }
}
