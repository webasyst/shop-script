<?php

class shopSettingsGeneralGetGravatarController extends waJsonController
{
    public function execute()
    {
        $email = waRequest::get('email');
        $default = waRequest::get('default');
        $this->response = shopHelper::getGravatar($email, 50, $default).'&f=y';
    }
}
