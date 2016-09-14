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
        wa()->event('customer_signup', $contact);
    }

    protected function confirmEmail($confirmation_hash, &$errors = array())
    {
        $contact = parent::confirmEmail($confirmation_hash, $errors);
        /**
         * @var shopConfig $shop_config
         */
        $shop_config = wa('shop')->getConfig();
        $auth_config = wa()->getAuthConfig();
        if ($contact && !empty($auth_config['params']['confirm_email']) && $shop_config->getGeneralSettings('guest_checkout') == 'merge_email') {
            // merge
            $contact_emails_model = new waContactEmailsModel();
            $email = $contact->get('email', 'default');
            if ($email) {
                $sql = "SELECT contact_id FROM " . $contact_emails_model->getTableName(). "
                        WHERE email LIKE '" . $contact_emails_model->escape($email, 'like') . "' AND sort = 0 AND contact_id = i:0";
                $contact_ids = $contact_emails_model->query($sql, $contact->getId())->fetchAll(null, true);
                if ($contact_ids && wa()->appExists('contacts')) {
                    wa('contacts');
                    if (class_exists('contactsContactsMergeController')) {
                        contactsContactsMergeController::merge($contact_ids, $contact->getId());
                    }
                }
            }
        }
        return $contact;
    }
}