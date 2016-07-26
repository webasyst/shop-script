<?php

class shopRightsMethod extends waAPIRightsMethod
{
    protected $app = 'shop';
    protected $courier = null;

    public function execute()
    {
        $courier_model = new shopApiCourierModel();
        $this->courier = $courier_model->getByToken(waRequest::request('access_token', '', 'string'));
        if ($this->courier) {
            waRequest::setParam('api_courier', $this->courier);
        }

        parent::execute();

        if ($this->courier) {
            $this->response = array_fill_keys(array_keys($this->response), 0);
            $this->response['orders'] = 1;
        }
    }
}