<?php

class shopBackendCustomerFormController extends waViewController
{
    /**
     * @var shopBackendCustomerForm
     */
    protected $form;

    public function execute()
    {
        $this->form = $this->getCustomerForm();
    }

    public function display()
    {
        if ($this->form) {
            echo $this->form->html(null, false);
        } else {
            echo '';
        }
        exit;
    }

    /**
     * @return shopBackendCustomerForm
     * @throws waException
     */
    protected function getCustomerForm()
    {
        $id = wa()->getRequest()->request('id');
        $id = is_scalar($id) ? (int)$id : 0;

        $type = wa()->getRequest()->request('type');
        $type = is_scalar($type) ? trim($type) : null;

        $storefront = wa()->getRequest()->request('storefront');
        $storefront = is_scalar($storefront) ? trim($storefront) : null;
        $storefront = strlen($storefront) > 0 ? $storefront : null;

        $form = new shopBackendCustomerForm();
        $form->setAddressDisplayType('first'); // Use only first contact address
        
        $form->setContactType($type);

        if ($id > 0) {
            $form->setContact($id);
        }

        if ($storefront) {
            $form->setStorefront($storefront);
        }

        $ns = $form->getNamespace();
        $data = wa()->getRequest()->post($ns);
        if ($data && is_array($data)) {
            $form->setValue($data);
        }

        return $form;
    }
}
