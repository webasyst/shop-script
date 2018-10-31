<?php
/**
 * "Countries and regions" settings page, and submit controller for it.
 */
class shopSettingsRegionsAction extends waViewAction
{
    public function execute()
    {
        $this->view->assign(array(
            'wa_settings' => $this->getUser()->getRights('webasyst', 'backend'),
        ));
    }
}

