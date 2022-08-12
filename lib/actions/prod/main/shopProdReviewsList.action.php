<?php
class shopProdReviewsListAction extends waViewAction
{
    public function execute()
    {
        $this->setTemplate('templates/actions/prod/main/ReviewsList.html');
        $this->setLayout(new shopBackendProductsListSectionLayout());
    }
}
