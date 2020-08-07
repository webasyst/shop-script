<?php
/**
 * Resets the password and sends an email to the contact after confirmation dialog (see CustomersPrepareResetPassword)
 */
class shopCustomersResetPasswordController extends waJsonController
{
    public function execute()
    {
        if (empty(wa()->getUser()->getRights('shop', 'customers'))) {
            $this->errors = _w('Insufficient access rights');
            return;
        }

        $contact_id = waRequest::post('contact_id', null, 'int');
        $email_id = waRequest::post('email_id', null, 'int');

        // Make sure it is not a backend user
        $contact = new waContact($contact_id);
        if ($contact['is_user'] > 0) {
            $this->errors = _w('Cannot reset the password for a backend user.');
            return;
        }

        $email_model = new waContactEmailsModel();
        $email = $email_model->getById($email_id);
        $general_contact_email_id = $email_model->select('id')->where("contact_id = {$contact_id} AND sort = 0")->fetchField('id');

        if (empty($general_contact_email_id) || empty($email['id'])) {
            $this->errors = _w('Incorrect email addresses related data found for this contact.');
            return;
        }

        // Because the field sort is unique
        $email_model->updateById($general_contact_email_id, array('sort' => -100));
        $email_model->updateById($email['id'], array('sort' => 0));
        $email_model->updateById($general_contact_email_id, array('sort' => $email['sort']));

        $verification_channel = new waVerificationChannelEmail(waVerificationChannelModel::TYPE_EMAIL);
        $contact_model = new waContactModel();
        $password = waContact::generatePassword();
        $password_hash = waContact::getPasswordHash($password);
        $contact_model->updateByField('id', $contact_id, array('password' => $password_hash));

        $auth_config = waDomainAuthConfig::factory();
        $options = array(
            'site_url' => $auth_config->getSiteUrl(),
            'site_name' => $auth_config->getSiteName(),
            'login_url' => $auth_config->getLoginUrl(array(), true),
        );
        if ($verification_channel->sendPassword($email['email'], $password, $options)) {
            $this->response = _w('Message has been sent.');
        } else {
            $this->errors = _w('Message sending has failed.');
        }

    }
}
