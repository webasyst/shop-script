<?php
/**
 * Show emails in dialog to reset contact password
 */
class shopCustomersPrepareResetPasswordAction extends waViewAction
{
    public function execute()
    {
        $contact_id = waRequest::post('contact_id', null, 'int');
        $contact_model = new waContactModel();
        $contact = $contact_model->getById($contact_id);
        $contact_emails_model = new waContactEmailsModel();
        $contact_emails = $contact_id ? $contact_emails_model->select('id, email')->where('contact_id = ' . $contact_id)->fetchAll() : null;
        $this->view->assign(array(
            'id' => $contact_id,
            'contact_emails' => $contact_emails,
            'contact_has_account' => !empty($contact['password'])
        ));
    }
}
