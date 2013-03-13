<?php

/** Loaded with layout. Shows sidebar and initializes JS, then loads other actions depending on URL hash. */
class shopCustomersAction extends waViewAction
{
    public function execute()
    {
        $this->getResponse()->setTitle(_w('Customers'));
    }
}

