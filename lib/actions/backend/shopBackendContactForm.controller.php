<?php

class shopBackendContactFormController extends waJsonController
{
    protected $form;
    public function execute()
    {
        $id = (int)waRequest::get('id');
        if ($id) {
            $this->form = shopHelper::getCustomerForm($id);
        }
    }

    public function display()
    {
        if ($this->form) {
            echo $this->form->html();
        } else {
            echo '';
        }
        exit;
    }
}