<?php

class shopRepairActions extends waActions
{

    public function __construct()
    {
        if (!$this->getUser()->isAdmin('shop')) {
            throw new waRightsException(_ws('Access denied'));
        }
    }

    public function categoriesAction()
    {
        $model = new shopCategoryModel();
        $model->repair();
        echo "OK";
    }
}