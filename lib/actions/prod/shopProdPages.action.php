<?php
/**
 * /products/<id>/pages/
 */
class shopProdPagesAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::param('id', '', waRequest::TYPE_STRING);
        shopProdGeneralAction::createEmptyProduct($product_id);
        $product = new shopProduct($product_id);
        if (!$product['id']) {
            $this->setTemplate('templates/actions/prod/includes/deleted_product.html');
            $this->setLayout(new shopBackendProductsEditSectionLayout([
                'content_id' => 'pages',
            ]));
            return;
        }
        $product_model = new shopProductModel();
        if (!$product_model->checkRights($product_id)) {
            throw new waException(_w('Access denied'));
        }

        list($frontend_urls, $total_storefronts_count, $url_template) = shopProdGeneralAction::getFrontendUrls($product);

        $formatted_product = self::formatProduct($product);
        $pages = $this->getPages($product_id);
        $empty_page = $this->getEmptyPage($product);

        $model = new shopProductPagesModel();
        $backend_prod_content_event = $this->throwEvent($product);

        $this->view->assign([
            'url_template'      => $url_template,
            'frontend_urls'     => $frontend_urls,
            'product'           => $product,
            'formatted_product' => $formatted_product,
            'empty_page'        => $empty_page,
            'pages'             => array_values($pages),
            'preview_hash'      => $model->getPreviewHash(),
            'backend_prod_content_event' => $backend_prod_content_event,
            'show_sku_warning' => shopProdSkuAction::isSkuCorrect($product['id'], $product['sku_type']),
        ]);

        $this->setLayout(new shopBackendProductsEditSectionLayout([
            'product' => $product,
            'content_id' => 'pages',
        ]));
    }

    protected function formatProduct($product)
    {
        return [
            "id"               => $product["id"],
            "name"             => $product["name"],
            "summary"          => $product["summary"],
            "description"      => $product["description"]
        ];
    }

    protected function getPages($product_id)
    {
        $model = new shopProductPagesModel();
        return $model->getPages($product_id);
    }

    protected function getEmptyPage($product)
    {
        return [
            'id'          => null,
            'product_id'  => $product["id"],
            'name'        => "",
            'title'       => "",
            'url'         => "",
            'content'     => "",
            'status'      => false,
            'keywords'    => "",
            'description' => "",

//            'create_datetime' => null,
//            'update_datetime' => null,
//            'create_contact_id' => null,
//            'sort' => '0',
        ];
    }

    /**
     * Throw 'backend_prod_content' event
     * @param shopProduct $product
     * @return array
     * @throws waException
     */
    protected function throwEvent($product)
    {
        /**
         * @event backend_prod_content
         * @since 8.19.0
         *
         * @param shopProduct $product
         * @param string $content_id
         *       Which page (tab) is shown
         */
        $params = [
            'product' => $product,
            'content_id' => 'pages',
        ];

        return wa('shop')->event('backend_prod_content', $params);
    }
}
