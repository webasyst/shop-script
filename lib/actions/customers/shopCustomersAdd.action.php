<?php

/**
 * Form to add new customer, and submit controller for that form.
 */
class shopCustomersAddAction extends waViewAction
{
    public function execute()
    {
        $form = $this->getForm();
        $post_data = $form->post();

        if ($post_data) {
            $customer_validation_disabled = wa()->getSetting('disable_backend_customer_form_validation');

            if ($customer_validation_disabled || $form->isValid()) {

                $c = new waContact();
                $c['is_company'] = $post_data['contact_type'] === shopCustomer::TYPE_COMPANY;

                if ($customer_validation_disabled) {
                    $errors = array();
                    $c->save($post_data);
                } else {
                    $errors = $c->save($post_data, true);
                }

                if (!$errors) {
                    $scm = new shopCustomerModel();
                    $scm->createFromContact($c->getId());
                    $this->applyConfirmationMarks($c);
                    echo '<script>$.customers.reloadSidebar(); window.location.hash = "#/id/'.$c->getId().'"</script>';
                    exit;
                }

                // Show errors that waContact returned, e.g. email must be unique.
                foreach ($errors as $fld => $list) {
                    $list = is_scalar($list) ? (array)$list : $list;
                    if (is_array($list)) {
                        foreach ($list as $err) {
                            $form->errors($fld, $err);
                        }
                    }
                }
            }
        }

        $this->view->assign('form', $form);
        $this->view->assign('customer_validation_disabled', wa()->getSetting('disable_backend_customer_form_validation'));
    }

    /**
     * @param waContact $contact
     * @throws waException
     */
    protected function applyConfirmationMarks($contact)
    {
        $customer = $contact instanceof shopCustomer ? $contact : new shopCustomer($contact->getId());
        $post = $this->getRequest()->post('customer');
        $post = is_array($post) ? $post : array();
        if (isset($post['email'])) {
            $customer->markMainEmailAsConfirmed(ifset($post['email_confirmed']), $post['email']);
        }
        if (isset($post['phone'])) {
            $customer->markMainPhoneAsConfirmed(ifset($post['phone_confirmed']), $post['phone']);
        }
    }

    /**
     * @return shopBackendCustomerForm
     */
    protected function getForm()
    {
        $form = new shopBackendCustomerForm();
        $data = $this->getRequest()->post($form->getNamespace());
        $data = is_array($data) ? $data : array();
        return $form->setContactType(ifset($data['contact_type']));
    }
}

