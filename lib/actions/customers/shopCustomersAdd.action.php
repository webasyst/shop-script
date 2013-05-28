<?php

/**
 * Form to add new customer, and submit controller for that form.
 */
class shopCustomersAddAction extends waViewAction
{
    public function execute()
    {
        $form = shopHelper::getCustomerForm();

        if ($form->post()) {
            $customer_validation_disabled = wa()->getSetting('disable_backend_customer_form_validation');
            if ($customer_validation_disabled || $form->isValid()) {
                $c = new waContact();

                if ($customer_validation_disabled) {
                    $errors = array();
                    $c->save($form->post());
                } else {
                    $errors = $c->save($form->post(), true);
                }

                if (!$errors) {
                    $scm = new shopCustomerModel();
                    $scm->createFromContact($c->getId());
                    echo '<script>$.customers.reloadSidebar(); window.location.hash = "#/id/'.$c->getId().'"</script>';
                    exit;
                }

                // Show errors that waContact returned, e.g. email must be unique.
                foreach ($errors as $fld => $list) {
                    foreach($list as $err) {
                        $form->errors($fld, $err);
                    }
                }
            }
        }

        $this->view->assign('form', $form);
        $this->view->assign('customer_validation_disabled', wa()->getSetting('disable_backend_customer_form_validation'));
    }
}

