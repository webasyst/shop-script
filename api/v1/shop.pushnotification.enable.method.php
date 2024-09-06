<?php
class shopPushnotificationEnableMethod extends shopApiMethod
{
    protected $method = 'POST';
    protected $courier_allowed = true;

    public function execute()
    {
        $this->response = 'ok';

        $client_id = waRequest::post('client_id', '', 'string');
        if (!strlen($client_id)) {
            throw new waAPIException('invalid_param', sprintf_wp('Invalid value of parameter “%s”.', 'client_id'), 400);
        }
        $shop_url = waRequest::post('shop_url', wa()->getRootUrl(true), 'string');

        $push_client_model = new shopPushClientModel();

        $force = waRequest::post('force', null, 'int');
        if (!$force && $force !== null) {
            $row = $push_client_model->getById($client_id);
            if ($row && $row['shop_url'] != $shop_url) {
                throw new waAPIException('already_subscribed', 'client_id subscribed via different URL', 412, array(
                    'shop_url' => $row['shop_url'],
                ));
            }
        }

        $push_client_model->insert(array(
            'contact_id'      => wa()->getUser()->getId(),
            'create_datetime' => date('Y-m-d H:i:s'),
            'api_token'       => waRequest::request('access_token', '', 'string'),
            'client_id'       => $client_id,
            'shop_url'        => $shop_url,
        ), 1);
    }
}
