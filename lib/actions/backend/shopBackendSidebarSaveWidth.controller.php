<?php

class shopBackendSidebarSaveWidthController extends waJsonController
{    
    public function execute() {
        $this->getConfig()->setSidebarWidth((int) waRequest::post('width'));
    }
}