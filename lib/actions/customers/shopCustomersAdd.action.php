<?php

/**
 * Form to add new customer, and submit controller for that form.
 */
class shopCustomersAddAction extends waViewAction
{
    public function execute()
    {
        $form = shopHelper::getCustomerForm();

        if ($form->post() && $form->isValid()) {
            $c = new waContact();
            $errors = $c->save($form->post());

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

        $this->view->assign('form', $form);
    }
}

