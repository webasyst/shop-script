<?php

class shopCustomersFilterModel extends waModel
{
    protected $table = 'shop_customers_filter';

    public function add($filter)
    {
        $filter['create_datetime'] = ifempty($filter['create_datetime'], date('Y-m-d H:i:s'));
        $filter['name'] = ifempty($filter['name'], _w('No name filter'));
        $filter['contact_id'] = ifset($filter['contact_id'], wa()->getUser()->getId());
        if (empty($filter['hash'])) {
            $filter['hash'] = null;
        }
        return $this->insert($filter);
    }

    public function update($filter)
    {
        if (!($item = $this->getById(ifset($filter['id'], 0)))) {
            return false;
        }
        return $this->updateById($item['id'], $filter);
    }

    public function save($filter)
    {
        return !empty($filter['id']) ? $this->update($filter) : $this->add($filter);
    }

    public function delete($filter_id)
    {
        if (!($filter = $this->getById($filter_id))) {
            return false;
        }
        return $this->deleteById($filter_id);
    }

    public function getFilters()
    {
        $user = wa()->getUser();
        if ($user->isAdmin()) {
            return $this->select('*')->order('id')->fetchAll('id');
        } else {
            $m = new waUserGroupsModel();
            $contact_ids = array();
            foreach ($m->getGroupIds($user->getId()) as $group_id) {
                $contact_ids[] = -$group_id;
            }
            $contact_ids[] = $user->getId();
            $contact_ids[] = 0;
            return $this->select('*')->where('contact_id IN(i:contact_id)', array('contact_id' => $contact_ids))->fetchAll();
        }
    }

    public function getEmptyRow() {
        $row = parent::getEmptyRow();
        $row['contact_id'] = wa()->getUser()->getId();
        return $row;
    }

}

