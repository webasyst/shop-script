<?php
class shopProdSetsAction extends waViewAction
{
    public function execute()
    {
        $set_model = new shopSetModel();

        shopHelper::setChapter('new_chapter');

        $this->view->assign([
            'model' => $set_model->getSetsWithGroups(),
            'rule_options' => $set_model::getRuleOptions(),
            'sort_options' => $set_model::getSortProductsOptions(),
        ]);

        $this->setTemplate('templates/actions/prod/main/Sets.html');
        $this->setLayout(new shopBackendProductsListSectionLayout());
    }
}
