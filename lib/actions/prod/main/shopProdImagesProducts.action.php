<?php

class shopProdImagesProductsAction extends waViewAction
{
    public function execute()
    {
        $this->view->assign(array(
            'types' => $this->getTypes(),
            'currency' => $this->getConfig()->getCurrency(),
            'filter_type' => waRequest::get('filter_type'), // categories | sets | types
            'filter_id' => waRequest::get('filter_id'),
            'filter_label' => waRequest::get('filter_label'),
        ));

        $this->setTemplate('templates/actions/prod/main/bulk-add/ImagesProducts.html');
    }

    public function getTypes() {
        $model = new shopTypeModel();
        return $model->getAll('id');
    }
}
