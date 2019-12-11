<?php

class shopWebPushNotifications
{
    /**
     * @var shopPushClientModel
     */
    protected $push_clients_model;

    public function __construct()
    {
        $this->push_clients_model = new shopPushClientModel();
    }

    public function send($data)
    {
        $notification_text = _w('New order').' '.shopHelper::encodeOrderId($data['order']['id']);

        /**
         * Send push notification from shop
         *
         * @param array $data
         * @param string $notification_text
         *
         * @event web_push_send
         */
        $event_params = [
            'data'              => &$data,
            'notification_text' => &$notification_text,
        ];
        wa('shop')->event('web_push_send', $event_params);

        $success = true;

        try {
            $push = wa()->getPush();
            if (!$push->isEnabled()) {
                return false;
            }

            $shop_orders_app_url = wa()->getRootUrl(true) . wa()->getConfig()->getBackendUrl() .'/shop?action=orders';

            $data = array(
                'title'   => $notification_text,
                'message' => wa_currency($data['order']['total'], $data['order']['currency']),
                'url'     => $shop_orders_app_url.'#/orders/state_id=new|processing|auth|paid&id='.$data['order']['id'].'/',
            );

            $contact_rights_model = new waContactRightsModel();
            $shop_user_ids = $contact_rights_model->getUsers('shop');

            $push->sendByContact($shop_user_ids, $data);
        } catch (Exception $ex) {
            if (wa()->getConfig()->isDebug()) {
                $result = $ex->getMessage();
                waLog::log('Unable to send PUSH notifications: '.$result, 'shop/webpush.log');
            }
            $success = false;
        }

        return $success;
    }
}
