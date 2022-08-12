<?php
class shopProdTagsAction extends waViewAction
{
    public function execute()
    {
        $this->setTemplate('templates/actions/prod/main/Tags.html');
        $this->setLayout(new shopBackendProductsListSectionLayout());
    }
}
