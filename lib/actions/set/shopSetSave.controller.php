<?php

class shopSetSaveController extends waJsonController
{
    protected $model = null;

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
            if (!$this->setSettingsValidate(null, $data)) {
                return false;
            }
            if (empty($data['name'])) {
                $data['name'] = _w('(no-name)');
            }
            $id = $this->model->add($data);
        } else {
            $set = $this->model->getById($id);
            if (!$this->setSettingsValidate($set, $data)) {
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

    private function setSettingsValidate($set = null, $data)
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
        }

        return $data;
    }
}
