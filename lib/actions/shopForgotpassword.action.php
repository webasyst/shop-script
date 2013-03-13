<?php

class shopForgotpasswordAction extends waForgotPasswordAction
{
    public function execute()
    {
        $this->setLayout(new shopFrontendLayout());
        $this->setThemeTemplate('forgotpassword.html');
        parent::execute();
    }
}