<?php

/**
 * Class shopProdPageMoveController
 *
 * Контроллер сортировки Подстраниц в обновленном редакторе товаров
 */
class shopProdPageMoveController extends waJsonController
{
    /**
     * @throws waException
     */
    public function execute()
    {
        /** @var array $pages_sort ID подстраниц после сортировки */
        $pages_sort = waRequest::post('pages', null, waRequest::TYPE_ARRAY_INT);
        $product_id = waRequest::post('product_id', null, waRequest::TYPE_INT);

        if (empty($product_id) || empty($pages_sort)) {
            throw new waException(_w('Unknown page'));
        }

        $product_model = new shopProductModel();
        $product = $product_model->getById($product_id);
        if (!$product) {
            $this->errors[] = [
                'id' => 'not_found',
                'text' => _w('Product not found.'),
            ];
            return;
        }
        if (!$product_model->checkRights($product)) {
            /** check rights */
            throw new waException(_w('Access denied'));
        }

        $result = true;
        $product_page_model = new shopProductPagesModel();
        foreach ($pages_sort as $sort => $page_id) {
            /** обновление всех полей сортировки текущего товара */
            $result = $result && $product_page_model->update($page_id, ['sort' => $sort]);
        }

        $this->response = ['move' => $result];;
    }
}
