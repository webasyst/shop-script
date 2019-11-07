<?php

class shopSetSaveController extends waJsonController
{
    /**
     * @var shopSetModel object
     */
    protected $model = null;

    /**
     * @throws waException
     * @throws waRightsException
     */
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'setscategories')) {
            throw new waRightsException(_w('Access denied'));
        }

        $this->model = new shopSetModel();

        $set_id = waRequest::get('set_id', null, waRequest::TYPE_STRING_TRIM);

        if ($this->saveName($set_id)) {
            return;
        }

        $data = $this->getData();
        $id = $this->saveSetSettings($set_id, $data);

        if ($id) {

            $this->response = $this->model->getById($id);
            $this->response['name'] = htmlspecialchars($this->response['name'], ENT_NOQUOTES);

            // when use iframe-transport unescaped content bring errors when parseJSON
            if (!empty($this->response['description'])) {
                $this->response['description'] = htmlspecialchars($this->response['description'], ENT_NOQUOTES);
            }
        }
    }

    /**
     * @param $set_id
     * @return bool
     */
    protected function saveName($set_id)
    {
        $edit = waRequest::get('edit', null, waRequest::TYPE_STRING_TRIM);
        $name = waRequest::post('name', '', waRequest::TYPE_STRING_TRIM);

        if ($edit === 'name') {
            $this->model->updateById($set_id, array(
                'name' => $name
            ));
            $this->response = array(
                'id'   => $set_id,
                'name' => htmlspecialchars($name)
            );
            return true;
        }

        return false;
    }

    /**
     * @param $id
     * @param $data
     * @return bool|string
     * @throws waException
     */
    private function saveSetSettings($id, &$data)
    {
        if (empty($data['count']) || $data['count'] < 0) {
            $data['count'] = 0;
        }

        if (!$id) {
            if (empty($data['id'])) {
                $id = shopHelper::transliterate($data['name']);
                $data['id'] = $this->model->suggestUniqueId($id);
            } else {
                $data['id'] = $this->model->suggestUniqueId($data['id']);
            }
            if (!$this->setSettingsValidate($data, null)) {
                return false;
            }
            if (empty($data['name'])) {
                $data['name'] = _w('(no-name)');
            }
            $id = $this->model->add($data);
        } else {
            $set = $this->model->getById($id);
            if (!$this->setSettingsValidate($data,$set)) {
                return false;
            }
            if (empty($data['name'])) {
                $data['name'] = $set['name'];
            }
            if (!empty($data['id'])) {
                $id = $data['id'];
            } else {
                $id = shopHelper::transliterate($data['name']);
                if ($id != $set['id']) {
                    $id = $this->model->suggestUniqueId($id);
                }
                $data['id'] = $id;
            }
            $data['edit_datetime'] = date('Y-m-d H:i:s');
            $this->model->update($set['id'], $data);
        }
        if ($id) {
            $data['id'] = $id;
            /**
             * @event set_save
             * @param array $set
             * @return void
             */
            wa()->event('set_save', $data);
        }
        return $id;
    }

    /**
     * @param null $set
     * @param $data
     * @return bool
     */
    private function setSettingsValidate($data, $set = null)
    {
        if (!preg_match("/^[a-z0-9\._-]+$/i", $data['id'])) {
            $this->errors['id'] = _w('Only Latin characters, numbers, underscore and hyphen symbols are allowed');
        }
        if ($set) {
            if (!empty($data['id']) && $set['id'] != $data['id']) {
                if ($this->model->idExists($data['id'])) {
                    $this->errors['id'] = _w('ID is in use');
                }
            }
        }
        return empty($this->errors);
    }

    /**
     * @return array
     * @throws waException
     */
    private function getData()
    {
        $type = waRequest::post('type', 0, waRequest::TYPE_INT);

        $data = array(
            'name' => waRequest::post('name', '', waRequest::TYPE_STRING_TRIM),
            'type' => $type,
            'id'   => waRequest::post('id', null, waRequest::TYPE_STRING_TRIM),
        );

        if ($type == shopSetModel::TYPE_DYNAMIC) {
            $rule = waRequest::post('rule', null, waRequest::TYPE_STRING);
            $data['rule'] = !empty($rule) ? $rule : null;
            $data['count'] = waRequest::post('count', 100, waRequest::TYPE_INT);
            $data['json_params'] = $this->getJsonParams();
        }

        return $data;
    }

    /**
     * @return false|string
     * @throws waException
     */
    protected function getJsonParams()
    {
        $date_start = $this->getDateStart();
        $params = [];
        if ($date_start) {
            $params['date_start'] = $this->parseDayFormat($date_start);
        }

        $date_end = $this->getDateEnd();
        if ($date_end) {
            $params['date_end'] = $this->parseDayFormat($date_end);
        }

        return json_encode($params);
    }

    /**
     * @return mixed
     */
    protected function getDateStart()
    {
        return waRequest::post('date_start', '', waRequest::TYPE_STRING);
    }

    /**
     * @return mixed
     */
    protected function getDateEnd()
    {
        return waRequest::post('date_end', '', waRequest::TYPE_STRING);
    }

    /**
     * @param $date
     * @return string
     * @throws waException
     */
    protected function parseDayFormat($date)
    {
        $date = trim($date);
        $new_date = waDateTime::parse('date', $date, null, 'ru_RU');

        if (!$new_date) {
            $new_date = waDateTime::parse('date', $date, null, 'en_US');

            if (!$new_date) {
                throw new waException(_w('Invalid date'));
            }
        }

        return $new_date;
    }
}
