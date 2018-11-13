<?php

class shopCustomersFilterDeleteController extends waJsonController
{
    public function execute()
    {
        $cfm = new shopCustomersFilterModel();
        $filter_id = $this->getFilterId();
        if (!$cfm->delete($filter_id)) {
            $this->errors[] = _w("Filter cannot be deleted.");
        }
    }

    public function getFilterId()
    {
        return $this->getRequest()->post('id');
    }
}

