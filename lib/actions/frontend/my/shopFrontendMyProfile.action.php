<?php
/**
 * User profile form in customer account, and submit controller for it.
 */
class shopFrontendMyProfileAction extends waMyProfileAction
{
    public function execute()
    {
        parent::execute();

        $this->view->assign('my_nav_selected', 'profile');

        // Set up layout and template from theme
        $this->setThemeTemplate('my.profile.html');
        if (!waRequest::isXMLHttpRequest()) {
            $this->setLayout(new shopFrontendLayout());
            $this->getResponse()->setTitle(_w('Account'));
            $this->view->assign('breadcrumbs', self::getBreadcrumbs());
            $this->layout->assign('nofollow', true);
        }
    }

    protected function getForm()
    {
        $domain = wa()->getRouting()->getDomain();
        $domain_config_path = wa()->getConfig()->getConfigPath('domains/'.$domain.'.php', true, 'site');
        if (file_exists($domain_config_path)) {
            $domain_config = include($domain_config_path);
        } else {
            $domain_config = array();
        }
        if (!empty($domain_config['personal_fields'])) {
            return parent::getForm();
        }
        return shopHelper::getCustomerForm(null, true);
    }

    protected function getContact()
    {
        // Create new temporary waContact object
        $contact = new waContact(wa()->getUser()->getId());

        // Assign address with the right extension, if no extension is set
        if ($this->form->fields('address.shipping') && !$contact->get('address.shipping') && $addresses = $contact->get('address')) {
            $contact->set('address.shipping', $addresses[0]);
        }
        if ($this->form->fields('address.billing') && !$contact->get('address.billing') && $addresses = $contact->get('address.shipping')) {
            $contact->set('address.billing', $addresses[0]);
        }

        return $contact;
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

