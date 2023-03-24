<?php
class shopProdSetsAction extends waViewAction
{
    public function execute()
    {
        $set_model = new shopSetModel();

        $data = $set_model->getSetsWithGroups();

        /**
         * @event backend_prod_sets
         * @since 9.4.1
         */
        $backend_prod_sets = wa('shop')->event('backend_prod_sets', ref([
            'data' => &$data,
        ]));

        $this->view->assign([
            'model' => $data,
            'rule_options' => $set_model::getRuleOptions(),
            'sort_options' => $set_model::getSortProductsOptions(),
            'backend_prod_sets' => $backend_prod_sets,
        ]);

        $this->setTemplate('templates/actions/prod/main/Sets.html');
        $this->setLayout(new shopBackendProductsListSectionLayout());
    }
}
