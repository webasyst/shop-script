<?php

/**
 * Class shopProdPageDeleteController
 *
 * Контроллер удаления Подстраниц в обновленном редакторе товаров
 */
class shopProdPageDeleteController extends waJsonController
{
    /**
     * @throws waException
     */
    public function execute()
    {
        $page_id    = waRequest::post('page_id', null, waRequest::TYPE_INT);
        $product_id = waRequest::post('product_id', null, waRequest::TYPE_INT);

        if (empty($page_id)) {
            throw new waException(_w('Unknown page'));
        }

        $product_pages_model = new shopProductPagesModel();
        $page = $product_pages_model->getById($page_id);
        if (empty($page) || empty($product_id)) {
            throw new waException(_w('Unknown page'));
        }

        $product_model = new shopProductModel();
        if (!$product_model->checkRights($product_id)) {
            /** check rights */
            throw new waException(_w('Access denied'));
        }

        $this->response = ['delete' => $product_pages_model->delete($page_id)];
    }
}