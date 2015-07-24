<?php

class shopBackendCategoryExpandAction extends waViewAction
{
    /**
     * @var waContactSettingsModel
     */
    protected $settings_model;

    public function __construct($params = null) {
        $this->settings_model = new waContactSettingsModel();
        parent::__construct($params);
    }

    public function execute()
    {
        $id = waRequest::get('id', 0, waRequest::TYPE_INT);

        $collapsed = waRequest::get('collapsed', 0, waRequest::TYPE_INT);
        if ($collapsed) {
            shopCategories::setCollapsed($id, !!waRequest::get('recurse'));
            return;
        }

        shopCategories::setExpanded($id, !!waRequest::get('recurse'));

        if (waRequest::get('tree')) {
            $categories = new shopCategories($id);
            $this->view->assign('categories', $categories->getList());
        }
    }
}