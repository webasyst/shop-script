<?php

class shopFrontendPageAction extends waPageAction
{
    public function execute()
    {
        $this->setLayout(new shopFrontendLayout());
        parent::execute();
    }
}