<?php

class shopSetCreateAction extends waViewAction
{
    private $set_dynamic_default_count = 8;

    protected $template = 'DialogProductSet'; // NEW MODE

    public function execute()
    {
        $set_model = new shopSetModel();
        $settings = $set_model->getEmptyRow();

        $this->view->assign([
            'default_count' => $this->set_dynamic_default_count,
            'settings'      => $settings
        ]);
    }
}