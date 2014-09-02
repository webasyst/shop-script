<?php

class shopPluginsController extends waViewController
{
    public function execute()
    {
        if (waRequest::get('id')) {
            $this->executeAction(new shopPluginsSettingsAction());
        } else {
            $this->executeAction(new shopPluginsAction());
        }
    }
}
