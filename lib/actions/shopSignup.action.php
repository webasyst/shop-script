<?php

class shopSignupAction extends waSignupAction
{
    public function execute()
    {
        $this->prefillForm();
        $this->setLayout(new shopFrontendLayout());
        $this->setThemeTemplate('signup.html');
        try {
            parent::execute();
        } catch (waException $e) {
            if ($e->getCode() == 404) {
                $this->view->assign('error_code', $e->getCode());
                $this->view->assign('error_message', $e->getMessage());
                $this->setThemeTemplate('error.html');
            } else {
                throw $e;
            }
        }
        wa()->getResponse()->setTitle(_w('Sign up'));
    }

    private function prefillForm()
    {
        $prefilling = $this->getRequest()->get('prefilling');
        if ($prefilling) {
            $contact_id = substr($prefilling, 16, -16);
            $contact = new waContact($contact_id);
            if ($contact->exists()) {
                $could = false;
                if (!isset($_POST['data'])) {
                    $_POST['data'] = array();
                    $could = true;
                } else if (is_array($_POST['data'])) {
                    $could = true;
                }
                if ($could) {
                    foreach (array('email', 'firstname', 'middlename', 'lastname') as $field_id) {
                        if (empty($_POST['data'][$field_id])) {
                            $contact_field_value = $contact->get($field_id, 'value');
                            if (is_array($contact_field_value)) {
                                $contact_field_value = (string) reset($contact_field_value);
                            }
                            $_POST['data'][$field_id] = $contact_field_value;
                        }
                    }
                }
            }
        }
    }

    protected function getFrom()
    {
        /**
         * @var shopConfig $config
         */
        $config = wa('shop')->getConfig();
        return array(
            $config->getGeneralSettings('email') => $config->getGeneralSettings('name')
        );
    }

    public function afterSignup(waContact $contact)
    {
        $contact->addToCategory($this->getAppId());
    }
}
