<?php

class shopProdSetMoveController extends waJsonController
{
    const TYPE_SET = 'set';
    const TYPE_GROUP = 'group';

    public function execute()
    {
        $id = waRequest::post('id', null, waRequest::TYPE_STRING_TRIM);
        $parent_group_id = waRequest::post('parent_id', null, waRequest::TYPE_INT);
        $sort = waRequest::post('sort', [], waRequest::TYPE_ARRAY);

        $this->validateData($sort);
        if (!$this->errors) {
            $this->move($id, $parent_group_id, $sort);
        }
    }

    protected function validateData($sort)
    {
        foreach ($sort as $item) {
            if ($item['type'] != self::TYPE_SET && $item['type'] != self::TYPE_GROUP) {
                $this->errors = [
                    'id' => 'incorrect_params',
                    'text' => _w('The item being moved is not a set or a set folder.')
                ];
                break;
            }
            if (($item['type'] == self::TYPE_GROUP && $item['id'] < 1)
                || ($item['type'] == self::TYPE_SET && mb_strlen($item['id']) == 0)
            ) {
                $this->errors = [
                    'id' => 'incorrect_params',
                    'text' => _w('Failed to move the item.')
                ];
                break;
            }
        }
    }

    protected function move($id, $parent_group_id, $sort)
    {
        $set_group_model = new shopSetGroupModel();
        $set_model = new shopSetModel();
        $index = 0;
        foreach ($sort as $item) {
            if ($item['type'] == self::TYPE_GROUP) {
                $set_group_model->updateByField('id', (int)$item['id'], ['sort' => $index]);
                $index++;
            } elseif ($item['type'] == self::TYPE_SET) {
                $new_data = [
                    'sort' => $index
                ];
                if ($item['id'] == $id) {
                    $new_data['group_id'] = $parent_group_id;
                }
                $set_model->updateByField('id', $item['id'], $new_data);
                $index++;
            }
        }

        $this->response['moved'] = true;
    }
}
