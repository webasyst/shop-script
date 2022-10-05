<?php

class shopProdSetsSortController extends waJsonController
{
    public function execute()
    {
        $sort = waRequest::post('sort', null, waRequest::TYPE_STRING_TRIM);

        $this->validateData($sort);
        if (!$this->errors) {
            $this->sort($sort);
        }
    }

    protected function validateData($sort)
    {
        if ($sort != 'name ASC' && $sort != 'name DESC') {
            $this->errors = [
                'id' => 'incorrect_sort',
                'text' => _w('Failed to sort sets.')
            ];
        }
    }

    /**
     * @param string $sort
     * @throws waDbException
     * @throws waException
     */
    protected function sort($sort)
    {
        $set_group_model = new shopSetGroupModel();
        $groups = $set_group_model->getAll();
        $set_model = new shopSetModel();
        $sets = $set_model->select('`id`, `name`, `sort`, 1 `is_set`')->fetchAll();
        $sets_with_groups = array_merge($sets, $groups);
        usort($sets_with_groups, function($a, $b) use ($sort) {
            $set_order = strcmp($a['name'], $b['name']);
            return $sort == 'name ASC' ? $set_order : -$set_order;
        });

        $index = 0;
        foreach ($sets_with_groups as $item) {
            if ($item['sort'] != $index) {
                if (!isset($item['is_set'])) {
                    $set_group_model->updateByField('id', $item['id'], ['sort' => $index]);
                } else {
                    $set_model->updateByField('id', $item['id'], ['sort' => $index]);
                }
            }
            $index++;
        }
    }
}
