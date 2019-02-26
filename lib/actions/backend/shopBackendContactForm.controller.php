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

    protected function getHtml()
    {
        if ($this->form) {
            return $this->form->html();
        } else {
            return '';
        }
    }

    protected function getInfo()
    {
        $id = (int)waRequest::get('id');
        $contact = new waContact($id);
        if (!$contact->exists()) {
            return null;
        }
        return array(
            'email' => $contact->get('email'),
            'phone' => $contact->get('phone')
        );
    }

    public function display()
    {
        if (!waRequest::get('json')) {
            die($this->getHtml());
        }

        $this->response['html'] = $this->getHtml();
        if (waRequest::get('get_info')) {
            $this->response['info'] = $this->getInfo();
        }
        parent::display();

    }
}
