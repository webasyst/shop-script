<?php

/**
 * User profile form in customer account, and submit controller for it.
 */
class shopFrontendMyProfileAction extends shopFrontendAction
{
    public function execute()
    {
        $form = shopHelper::getCustomerForm(null, true);
        $this->setValues($form);

        $saved = $this->saveFromPost($form, wa()->getUser());

        $this->view->assign('form', $form);
        $this->view->assign('saved', $saved);

        // Set up layout and template from theme
        $this->setThemeTemplate('my.profile.html');
        if (!waRequest::isXMLHttpRequest()) {
            $this->setLayout(new shopFrontendLayout());
            $this->getResponse()->setTitle(_w('Account'));
            $this->layout->assign('breadcrumbs', self::getBreadcrumbs());
            $this->layout->assign('nofollow', true);
        }
    }

    protected function setValues($form)
    {
        // Create new temporary waContact object
        $contact = new waContact(wa()->getUser()->getId());

        // Assign address with the right extension, if no extension is set
        if (!$contact->get('address.shipping') && $addresses = $contact->get('address')) {
            $contact->set('address.shipping', $addresses[0]);
        }
        if (!$contact->get('address.billing') && $addresses = $contact->get('address.shipping')) {
            $contact->set('address.billing', $addresses[0]);
        }

        $form->setValue($contact);
    }

    protected function saveFromPost($form, $contact)
    {
        if (!waRequest::post() || !$form->isValid($contact)) {
            return false;
        }

        $data = $form->post();
        if (!$data || !is_array($data)) {
            return false;
        }

        foreach ($data as $field => $value) {
            $contact->set($field, $value);
        }
        $contact->save();

        return true;
    }

    public static function getBreadcrumbs()
    {
        return array(
            array(
                'name' => _w('My account'),
                'url' => wa()->getRouteUrl('/frontend/my'),
            ),
        );
    }
}

