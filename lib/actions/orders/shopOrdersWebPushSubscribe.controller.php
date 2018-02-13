<?php

class shopOrdersWebPushSubscribeController extends waJsonController
{
    public function execute()
    {
        $web_push = new shopWebPushNotifications();
        if ($this->getStatus()) {
            $web_push->addClientIdToContact($this->getClientId());
        } else {
            $web_push->deleteClientIdFromContact($this->getClientId());
        }
    }

    public function getClientId()
    {
        return $this->getRequest()->post('client_id');
    }

    public function getStatus()
    {
        return !!$this->getRequest()->post('status');
    }
}
