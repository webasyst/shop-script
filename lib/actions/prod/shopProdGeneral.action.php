<?php
/**
 * /products/<id>/general/
 * Product editor, general tab.
 */
class shopProdGeneralAction extends waViewAction
{
    public function execute()
    {
        $product_id = waRequest::param('id', '', waRequest::TYPE_STRING);
        $product = new shopProduct($product_id);
        if (!$product['id'] && $product_id != 'new') {
            throw new waException(_w("Unknown product"), 404);
        }
        $product_model = new shopProductModel();
        if (!$product_model->checkRights($product_id)) {
            throw new waException(_w('Access denied'));
        }

        list($frontend_urls, $total_storefronts_count, $url_template) = $this->getFrontendUrls($product, false);

        $category_model = new shopCategoryModel();
        $categories = $category_model->getFullTree('id, name, parent_id', true);
        $categories_tree = $category_model->buildNestedTree($categories);

        $set_model = new shopSetModel();
        $sets = $set_model->getAll();

        $type_model = new shopTypeModel();

        // magic loading of skus
        $product['skus'];
        if ($product_id == 'new') {
            $product->setData('name', _w('Product name'));
            $product->setData('currency', wa('shop')->getConfig()->getCurrency());
            $product->setData('status', 1);
            $product->setData('type_id', self::getFirstType());
            $product_skus_model = new shopProductSkusModel();
            $empty_sku = $product_skus_model->getEmptyRow();
            $empty_sku["id"] = "-1";
            foreach (['price', 'primary_price', 'purchase_price', 'compare_price'] as $field) {
                $empty_sku[$field] = 0.0;
            }
            $product->setData('skus', [-1 => $empty_sku]);
        }
        $backend_prod_content_event = $this->throwEvent($product);
        shopHelper::setDefaultNewEditor();

        $this->view->assign([
            'url_template' => $url_template,
            'frontend_urls' => $frontend_urls,
            'product_types' => $type_model->getTypes(),
            'total_storefronts_count' => $total_storefronts_count,
            'categories' => $categories,
            'categories_tree' => $categories_tree,
            'taxes' => (new shopTaxModel())->getAll('id'),
            'sets' => $sets,
            'product' => $product,

            'stocks'            => shopProdSkuAction::getStocks(),
            'formatted_product' => shopProdSkuAction::formatProduct($product),
            'currencies'        => shopProdSkuAction::getCurrencies(),
            'backend_prod_content_event' => $backend_prod_content_event,
            'show_sku_warning' => shopProdSkuAction::isSkuCorrect($product['id'], $product['sku_type']),
        ]);

        $this->setLayout(new shopBackendProductsEditSectionLayout([
            'product' => $product,
            'content_id' => 'general',
        ]));
    }

    public static function createEmptyProduct(&$product_id)
    {
        if ($product_id == 'new') {
            $product = new shopProduct();
            $data = [
                'name' => '',
                'currency' => wa('shop')->getConfig()->getCurrency(),
                'type_id' => self::getFirstType(),
                'status' => 1,
                'skus' => [
                    -1 => [
                        'name' => ''
                    ]
                ]
            ];
            $product->save($data);
            $product_id = $product->getId();
        }
    }

    protected static function getFirstType()
    {
        $type_model = new shopTypeModel();
        return $type_model->select('id')->order('id')->limit('1')->fetchField('id');
    }

    /**
     * @param $product
     * @param $urls_count_limit false is get all urls
     * @return array
     * @throws waException
     */
    public static function getFrontendUrls($product, $urls_count_limit = 10)
    {
        $frontend_urls = [];
        $url_template = null;
        $total_storefronts_count = 0;

        if ($product->id) {

            $worse_frontend_urls = [];

            $canonical_category = null;

            $routing = wa()->getRouting();
            $domain_routes = $routing->getByApp('shop');

            foreach ($domain_routes as $domain => $routes) {
                foreach ($routes as $r) {
                    if (!empty($r['private'])) {
                        continue; // do not advertise links to private storefronts
                    }
                    if (!empty($r['type_id']) && !in_array($product->type_id, (array)$r['type_id'])) {
                        continue; // ignore storefronts that disable current product type
                    }

                    $total_storefronts_count++;
                    if ($urls_count_limit !== false && count($frontend_urls) >= $urls_count_limit) {
                        continue;
                    }

                    $url_params = array(
                        'product_url' => $product->url,
                    );

                    $good_url = true;
                    if (ifempty($r, 'url_type', 0) == 2) {

                        // Attempting to build some URLs require to load url of this product's main category
                        if ($canonical_category === null) {
                            $category_routes = [];
                            $canonical_category = ifset(ref($product->categories), $product->category_id, false);
                            if (!empty($canonical_category['id'])) {
                                $category_routes_model = new shopCategoryRoutesModel();
                                $category_routes = $category_routes_model->getRoutes([$canonical_category['id']]);
                                $category_routes = ifset($category_routes, $canonical_category['id'], []);
                            }
                        }

                        // When category is hidden on a storefront, we can still build proper product URL and it will work,
                        // but we will show the URL at the bottom of the list.
                        $category_available = empty($category_routes) || in_array($domain.'/'.$r['url'], $category_routes);
                        if ($canonical_category && $category_available) {
                            $url_params['category_url'] = $canonical_category['full_url'];
                        } else {
                            $good_url = false;
                        }
                    }

                    if (!$good_url && $urls_count_limit !== false && count($worse_frontend_urls) >= $urls_count_limit) {
                        continue;
                    }

                    // Generate URL to current storefront
                    $routing->setRoute($r, $domain);
                    $frontend_url = $routing->getUrl('/frontend/product', $url_params, true);
                    if (false !== strpos($frontend_url, 'xn--')) {
                        $frontend_url = waIdna::dec($frontend_url);
                    }

                    if ($good_url) {
                        // Proper URLs: either contain category, or no category is required
                        $frontend_urls[] = array(
                            'url' => $frontend_url,
                            'proper_url' => true,
                        );
                        if (!$url_template && empty($url_params['category_url'])) {
                            $url_template = wa()->getRouteUrl('/frontend/product', array('product_url' => '%product_url%'), true);
                        }
                    } else {
                        // Cheater URLs: category is required, but disabled on the storefront.
                        // We will place them at the end of the list.
                        $worse_frontend_urls[] = array(
                            'url' => $frontend_url,
                            'proper_url' => false,
                        );
                    }
                }
            }
            $frontend_urls = array_merge($frontend_urls, $worse_frontend_urls);
            if ($urls_count_limit !== false) {
                $frontend_urls = array_slice($frontend_urls, 0, $urls_count_limit);
            }
        }

        if (!$url_template) {
            $url_template = wa()->getRouteUrl('/frontend/product', array('product_url' => '%product_url%'), true);
        }
        if (false !== strpos($url_template, 'xn--')) {
            $url_template = waIdna::dec($url_template);
        }
        $url_template_base = explode('%product_url%', $url_template, 2)[0];

        return [$frontend_urls, $total_storefronts_count, [
            'template' => $url_template,
            'base' => $url_template_base,
        ]];
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
         * @since 8.18.0
         *
         * @param shopProduct $product
         * @param string $content_id
         *       Which page (tab) is shown
         */
        $params = [
            'product' => $product,
            'content_id' => 'general',
        ];
        return wa('shop')->event('backend_prod_content', $params);
    }
}
