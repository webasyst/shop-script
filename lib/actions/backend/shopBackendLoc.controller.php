<?php

/**
 * A list of localized strings to use in JS.
 */
class shopBackendLocController extends waViewController
{
    public function execute()
    {
        $this->executeAction(new shopBackendLocAction());
    }

    public function preExecute()
    {
        // do not save this page as last visited
    }
}
