<?php

class shopBackendSettingsController extends waViewController
{
    public function execute()
    {
        $this->executeAction(new shopBackendSettingsAction());

        $this->setLayout(new shopBackendLayout());

        $this->layout->assign('no_level2', true);
        $this->getResponse()->addJs('js/settings/settings.js', true);
    }
}