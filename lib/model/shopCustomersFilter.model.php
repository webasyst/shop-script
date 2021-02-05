<?php

class shopCustomersFilterModel extends waModel
{
    protected $table = 'shop_customers_filter';

    public function add($filter)
    {
        $filter['create_datetime'] = ifempty($filter['create_datetime'], date('Y-m-d H:i:s'));
        $filter['name'] = ifempty($filter['name'], _w('No name filter'));
        $user_id = wa()->getUser()->getId();
        $filter['contact_id'] = ifset($filter['contact_id'], $user_id);
        $filter['mass_edit'] = ifset($filter['mass_edit'], $user_id);
        if (empty($filter['hash'])) {
            $filter['hash'] = null;
        }
        return $this->insert($filter);
    }

    public function update($filter)
    {
        $item = $this->getById(ifset($filter['id'], 0));
        if (!$item) {
            return false;
        }
        $user_id = wa()->getUser()->getId();
        if ($item['mass_edit'] == 0 || $item['mass_edit'] == $user_id) {
            $filter['contact_id'] = ifset($filter['contact_id'], $user_id);
            if ($filter['contact_id'] < 1) {
                $filter['mass_edit'] = ifset($filter['mass_edit'], $user_id);
            }
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
        $m = new waUserGroupsModel();
        $contact_ids = array();
        foreach ($m->getGroupIds($user->getId()) as $group_id) {
            $contact_ids[] = -$group_id;
        }
        $contact_ids[] = $user->getId();
        $contact_ids[] = 0;
        return $this->select('*')->where('contact_id IN(i:contact_id)', array('contact_id' => $contact_ids))->fetchAll();
    }

    public function getEmptyRow() {
        $row = parent::getEmptyRow();
        $row['contact_id'] = wa()->getUser()->getId();
        return $row;
    }

    public function addWelcomeCountryUsaFilters()
    {
        $this->add(array(
            'name' => _w('From New York'),
            'icon' => 'marker',
            'hash' => 'contact_info.address.country=usa&contact_info.address.region=usa:NY'
        ));
    }

    public function addWelcomeCountryCanFilters()
    {
        $this->add(array(
            'name' => _w('From British Columbia'),
            'icon' => 'marker',
            'hash' => 'contact_info.address.country=can&contact_info.address.region=can:BC'
        ));
    }

    public function addWelcomeCountryAusFilters()
    {
        $this->add(array(
            'name' => _w('From New Zealand'),
            'icon' => 'marker',
            'hash' => 'contact_info.address.country=nzl'
        ));
    }

    public function addWelcomeCountryRusFilters()
    {
        $this->add(array(
            'name' => 'Из Москвы',
            'icon' => 'marker',
            'hash' => 'contact_info.address.country=rus&contact_info.address.region=rus:77'
        ));
        $this->addWelcomeRefererVkFilter();
    }

    public function addWelcomeCountryUkrFilters()
    {
        $this->add(array(
            'name' => 'Из Киева',
            'icon' => 'marker',
            'hash' => 'contact_info.address.country=ukr&contact_info.address.region=ukr:77'
        ));
        $this->addWelcomeRefererVkFilter();
    }

    public function addWelcomeRefererFacebookFilter()
    {
        $this->add(array(
            'name' => _w('From Facebook'),
            'icon' => 'facebook',
            'hash' => 'app.referer=facebook.com'
        ));
    }

    public function addWelcomeRefererTwitterFilter()
    {
        $this->add(array(
            'name' => _w('From Twitter'),
            'icon' => 'twitter',
            'hash' => 'app.referer=twitter.com'
        ));
    }

    public function addWelcomeRefererVkFilter()
    {
        $this->add(array(
            'name' => 'Из Вконтакте',
            'icon' => 'vkontakte',
            'hash' => 'app.referer=vk.com'
        ));
    }

    public function addWelcomeLastOrderedMonthAgoFilter()
    {
        $this->add(array(
            'name' => _w('Last ordered over a month ago'),
            'icon' => 'clock',
            'hash' => 'app.last_order_datetime<=-30d'
        ));
    }

}

