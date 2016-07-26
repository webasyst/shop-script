<?php
class shopPushClientModel extends waModel
{
    protected $table = 'shop_push_client';
    protected $id = 'client_id';

    public function getAllMobileClients()
    {
        return $this->getByField(array('type' => array('', 'mobile')), true);
    }
}
