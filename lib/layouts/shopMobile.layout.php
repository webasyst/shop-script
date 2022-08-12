<?php

class shopMobileLayout extends waLayout
{
    public function execute()
    {
        $user = $this->getUser();
        $app = $this->getApp();
        $this->assign('rights', array(
            'admin' => $user->getRights($app, 'backend'),
        ));
    }
}
