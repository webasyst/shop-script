<?php
class shopPushnotificationDisableMethod extends shopApiMethod
{
    protected $method = 'POST';
    protected $courier_allowed = true;

    public function execute()
    {
        $client_id = @(string) $this->post('client_id', true);
        $push_client_model = new shopPushClientModel();
        $push_client_model->deleteById($client_id);
        $this->response = 'ok';
    }
}
