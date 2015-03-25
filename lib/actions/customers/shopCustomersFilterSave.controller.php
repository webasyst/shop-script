<?php


class shopCustomersFilterSaveController extends waJsonController
{
    public function execute()
    {
        $cfm = new shopCustomersFilterModel();
        $filter = $this->getFilter();
        if (!($res = $cfm->save($filter))) {
            $this->errors[] = _w("Filter couldn't be saved");
        }
        $filter['id'] = $filter['id'] ? $filter['id'] : (int) $res;
        $this->response = array(
            'filter' => $filter,
            'filter_id' => $this->getFilterId()
        );
    }

    public function getFilterId()
    {
        return $this->getRequest()->get('id');
    }

    public function getFilter()
    {
        $filter = $this->getRequest()->post('filter');
        $filter['id'] = $this->getFilterId();
        return $filter;
    }
}

