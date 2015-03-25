<?php

class shopOAuthController extends waOAuthController
{
    public function afterAuth($data)
    {
        $params = $this->getStorage()->get('auth_params');
        if (isset($params['guest']) && $params['guest']) {
            $this->getStorage()->set('auth_user_data', $data);
        } else {
            $contact = parent::afterAuth($data);
            if ($contact && !$contact['is_user']) {
                $contact->addToCategory($this->getAppId());
            }
        }

        wa('webasyst');
        $this->executeAction(new webasystOAuthAction());
    }

    protected function createContact($data)
    {
        $contact = parent::createContact($data);
        if ($contact->getId()) {
            $shop_config = wa('shop')->getConfig();
            $auth_config = wa()->getAuthConfig();
            if ($contact && !empty($auth_config['params']['confirm_email']) && $shop_config->getGeneralSettings('guest_checkout') == 'merge_email') {
                // merge
                $contact_emails_model = new waContactEmailsModel();
                $email = $contact->get('email', 'default');
                if ($email) {
                    $sql = "SELECT contact_id FROM " . $contact_emails_model->getTableName(). "
                        WHERE email LIKE '" . $this->escape($email, 'like') . "' AND sort = 0 AND contact_id = i:0";
                    $contact_ids = $contact_emails_model->query($sql, $contact->getId())->fetchAll(null, true);
                    if ($contact_ids && wa()->appExists('contacts')) {
                        wa('contacts');
                        if (class_exists('contactsContactsMergeController')) {
                            contactsContactsMergeController::merge($contact_ids, $contact->getId());
                        }
                    }
                }
            }
        }
        return $contact;
    }
}
