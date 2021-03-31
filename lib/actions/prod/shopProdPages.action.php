<?php
/**
 * /products/<id>/pages/
 */
class shopProdPagesAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::param('id', '', 'int');
        $product = new shopProduct($product_id);
        if (!$product['id']) {
            throw new waException(_w("Unknown product"), 404);
        }

        list($frontend_urls, $total_storefronts_count, $url_template) = shopProdGeneralAction::getFrontendUrls($product);

        $formatted_product = self::formatProduct($product);
        $pages = $this->getPages($product_id);
        $empty_page = $this->getEmptyPage($product);

        $model = new shopProductPagesModel();

        $this->view->assign([
            'url_template'      => $url_template,
            'frontend_urls'     => $frontend_urls,
            'product'           => $product,
            'formatted_product' => $formatted_product,
            'empty_page'        => $empty_page,
            'pages'             => array_values($pages),
            'preview_hash'      => $model->getPreviewHash()
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
}
