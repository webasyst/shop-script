<?php

class shopSetGroupModel extends waModel
{
    protected $table = 'shop_set_group';

    public function getAll($key = null, $normalize = false)
    {
        return $this->query("SELECT * FROM {$this->table} ORDER BY sort")->fetchAll($key, $normalize);
    }

    /**
     * @param array $data
     * @return bool|int|resource
     * @throws waDbException
     */
    public function add($data)
    {
        if (!empty($data) && is_array($data)) {
            $set_model = new shopSetModel();
            $updated = $set_model->query("UPDATE {$set_model->getTableName()} SET sort = sort + 1");
            if ($updated && $this->query("UPDATE {$this->table} SET sort = sort + 1")) {
                unset($data['id']);
                $new_group_id = $this->insert($data);
                if ($new_group_id) {
                    return $new_group_id;
                }
            }
        }

        return false;
    }

    /**
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        $item = $this->getById($id);
        if (!$item) {
            return false;
        }

        $this->deleteById($id);

        // delete related info
        $set_model = new shopSetModel();
        $set_model->updateByField('group_id', $id, ['group_id' => null]);

        return true;
    }
}
