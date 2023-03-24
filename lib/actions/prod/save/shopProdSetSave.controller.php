<?php

class shopProdSetSaveController extends waJsonController
{
    /**
     * @var shopSetModel
     */
    protected $model = null;

    protected $is_update = false;

    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            throw new waRightsException(_w('Access denied'));
        }

        $set_id = waRequest::post('set_id', null, waRequest::TYPE_STRING_TRIM);
        $this->is_update = mb_strlen($set_id) > 0;
        $this->model = new shopSetModel();

        $data = $this->getData();

        $this->validateData($set_id, $data);

        if (!$this->errors) {
            if ($this->is_update) {
                $data['edit_datetime'] = date('Y-m-d H:i:s');
                $this->model->update($set_id, $data);
                $id = $data['id'];
            } else {
                $id = $this->model->add($data);
            }

            if (isset($id)) {
                $saved_set = $this->model->getById($id);
                $saved_set['name'] = htmlspecialchars($saved_set['name'], ENT_NOQUOTES);
                $saved_set['json_params'] = json_decode($saved_set['json_params'], true);
                if (!is_array($saved_set['json_params'])) {
                    $saved_set['json_params'] = [];
                }
                $saved_set['is_group'] = false;
                $saved_set['set_id'] = $saved_set["id"];
                $this->recount($id, $saved_set);
                $this->response = $saved_set;

                /**
                 * @event set_save
                 * @param array $set
                 * @return void
                 */
                wa()->event('set_save', $data);
            }
        }
    }

    /**
     * @return array
     */
    protected function getData()
    {
        $type = waRequest::post('type', shopSetModel::TYPE_STATIC, waRequest::TYPE_INT);

        $data = array(
            'id' => waRequest::post('id', '', waRequest::TYPE_STRING_TRIM),
            'name' => waRequest::post('name', '', waRequest::TYPE_STRING_TRIM),
            'sort_products' => waRequest::post('sort_products', null, waRequest::TYPE_STRING_TRIM),
        );
        if (!$this->is_update) {
            $data['type'] = $type;
        }

        if ($type == shopSetModel::TYPE_DYNAMIC || $this->is_update) {
            $data['rule'] = waRequest::post('rule', null, waRequest::TYPE_STRING_TRIM);
            $data['count'] = waRequest::post('count', 100, waRequest::TYPE_INT);
            $data['json_params'] = $this->getJsonParams();
        }

        return $data;
    }

    /**
     * @param string $set_id
     * @param array $data
     * @return void
     */
    protected function validateData($set_id, &$data)
    {
        foreach ($data as $key => &$value) {
            switch ($key) {
                case 'type':
                    if ($value != shopSetModel::TYPE_STATIC && $value != shopSetModel::TYPE_DYNAMIC) {
                        $this->errors[] = [
                            'id' => 'incorrect_type',
                            'text' => _w('Missing set type.')
                        ];
                    }
                    break;
                case 'id':
                    $length_id = mb_strlen($value);
                    if (empty($length_id) || $length_id > 64) {
                        $this->errors[] = [
                            'id' => 'incorrect_length_id',
                            'text' => _w('The set ID cannot be empty or longer than 64 characters.')
                        ];
                    }
                    if (!preg_match("/^[a-z0-9\._-]+$/i", $value)) {
                        $this->errors[] = [
                            'id' => 'incorrect_id',
                            'text' => _w('Only Latin characters, numbers, underscore and hyphen symbols are allowed')
                        ];
                    }
                    if (!$this->errors) {
                        if ($this->is_update) {
                            $set = $this->model->getById($set_id);
                            if (!$set) {
                                $this->errors[] = [
                                    'id' => 'set_not_found',
                                    'text' => _w('No set found to update.')
                                ];
                            }
                        }
                        if ($set_id != $data['id']) {
                            if ($this->model->idExists($data['id'])) {
                                $this->errors[] = [
                                    'id' => 'id_in_use',
                                    'text' => _w('ID is in use')
                                ];
                            }
                        }
                    }
                    break;
                case 'name':
                    $value = trim(preg_replace('#\s+#', ' ', $value));
                    if (mb_strlen($value) == 0 || mb_strlen($value) > 255) {
                        $this->errors[] = [
                            'id' => 'incorrect_length_name',
                            'text' => _w('A name cannot be empty or longer than 64 characters.')
                        ];
                    }
                    break;
                case 'rule':
                    if (!in_array($value, array_column(shopSetModel::getRuleOptions(), 'value'))) {
                        $this->errors[] = [
                            'id' => 'incorrect_rule',
                            'text' => _w('The filtering rule does not exist.')
                        ];
                    }
                    break;
                case 'sort_products':
                    if (isset($value) && !in_array($value, array_column(shopSetModel::getSortProductsOptions(), 'value'))) {
                        $this->errors[] = [
                            'id' => 'incorrect_sort_products',
                            'text' => _w('The sorting rule does not exist.')
                        ];
                    }
                    break;
                case 'count':
                    if (empty($value) || $value < 0) {
                        $this->errors[] = [
                            'id' => 'incorrect_count',
                            'text' => _w('A quantity cannot be empty or negative.')
                        ];
                    }
                    break;
            }
        }
    }

    /**
     * @return false|string
     */
    protected function getJsonParams()
    {
        $date_start = waRequest::post('date_start', '', waRequest::TYPE_STRING);
        $params = [];
        if ($date_start) {
            $date_start_formatted = $this->parseDayFormat($date_start);
            if ($date_start_formatted) {
                $params['date_start'] = $date_start_formatted;
            } else {
                $this->errors[] = [
                    'id' => 'invalid_date_start',
                    'text' => _w('Invalid start date.')
                ];
            }
        }

        $date_end = waRequest::post('date_end', '', waRequest::TYPE_STRING);
        if ($date_end) {
            $date_end_formatted = $this->parseDayFormat($date_end);
            if ($date_end_formatted) {
                $params['date_end'] = $date_end_formatted;
            } else {
                $this->errors[] = [
                    'id' => 'invalid_date_end',
                    'text' => _w('Invalid date end.')
                ];
            }
        }

        return json_encode($params);
    }

    /**
     * @param $date
     * @return string|false
     */
    protected function parseDayFormat($date)
    {
        $date = trim($date);
        $date_object = DateTime::createFromFormat('Y-m-d', $date);
        return $date_object !== false ? $date_object->format('Y-m-d') : false;
    }

    protected function recount($set_id, &$set)
    {
        if ($set['type'] == shopSetModel::TYPE_DYNAMIC) {
            $product_collection = new shopProductsCollection("set/$set_id");
            $set_right_count = $product_collection->count();
            $set['count'] = $set_right_count;
        }
    }
}
