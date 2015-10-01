<?php

class shopBackendPluginsController extends waController
{
    public function execute()
    {
        $c = new shopPluginsActions();
        $c->run();
    }
}