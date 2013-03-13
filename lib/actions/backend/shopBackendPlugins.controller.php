<?php

class shopBackendPluginsController extends waViewController
{
    public function execute()
    {
        $this->executeAction(new shopPluginsAction());
    }
}