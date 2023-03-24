<?php

/**
 * Class shopFrontendAction
 * @method shopConfig getConfig()
 */
class shopFrontendAction extends waViewAction
{
    public function __construct($params = null)
    {
        parent::__construct($params);

        if (!waRequest::isXMLHttpRequest()) {
            $this->setLayout(new shopFrontendLayout());
        }
    }

    /**
     * @deprecated
     */
    public function addCanonical()
    {

    }

    public function getStoreName()
    {
        $title = waRequest::param('title');
        if (!$title) {
            $title = $this->getConfig()->getGeneralSettings('name');
        }
        if (!$title) {
            $app = wa()->getAppInfo();
            $title = $app['name'];
        }
        return htmlspecialchars($title);
    }

    /**
     * @param shopProductsCollection $collection
     * @throws waException
     */
    protected function setCollection(shopProductsCollection $collection, $additional_filters = [])
    {
        $collection_before_filters = clone $collection;
        list($stock_units_ids, $base_units_ids, $all_base_unit_ids) = $collection_before_filters->getAllUnitIds();
        $this->setCollectionParams($collection, true, $additional_filters);

        $limit = (int)waRequest::cookie('products_per_page');
        if (!$limit || $limit < 0 || $limit > 500) {
            $limit = (int)waRequest::param('products_per_page');
            if (!$limit || $limit < 0 || $limit > 500) {
                $limit = $this->getConfig()->getOption('products_per_page');
            }
        }

        $page = waRequest::get('page', 1, 'int');
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $limit;

        $skus_field = 'skus_filtered';
        if (wa()->getConfig()->getOption('frontend_collection_all_skus') === false) {
            $skus_field = 'sku_filtered';
        }
        $products = $collection->getProducts('*,skus_image,' . $skus_field, $offset, $limit);
        $this->assignUnits($stock_units_ids, $base_units_ids, $all_base_unit_ids);

        $count = $collection->count();

        $pages_count = ceil((float)$count / $limit);
        $this->view->assign('pages_count', $pages_count);

        $this->view->assign('products', $products);
        $this->view->assign('products_count', $count);
    }

    protected function setCollectionParams(shopProductsCollection $collection, $with_sort_unit = true, $additional_filters = [])
    {
        $filters = array_merge(waRequest::get(), $additional_filters);
        if (isset($filters['sort_unit']) && $with_sort_unit) {
            $sort = ifset($filters, 'sort', '');
            if ($sort == 'price' && !isset($filters['stock_unit_id'])) {
                $filters['stock_unit_id'] = $filters['sort_unit'];
            } elseif ($sort == 'base_price' && !isset($filters['base_unit_id'])) {
                $filters['base_unit_id'] = $filters['sort_unit'];
            }
            unset($filters['sort_unit']);
        }

        $collection->filters($filters);
        $collection->setOptions([
            'overwrite_product_prices' => true,
        ]);
    }

    public function execute()
    {
        if (strlen(wa()->getRouting()->getCurrentUrl())) {
            throw new waException(_ws('Page not found'), 404);
        }
        $title = waRequest::param('title');
        if (!$title) {
            $app = wa()->getAppInfo();
            $title = $app['name'];
        }
        $this->getResponse()->setTitle($title);
        $this->getResponse()->setMeta('keywords', waRequest::param('meta_keywords'));
        $this->getResponse()->setMeta('description', waRequest::param('meta_description'));


        // Open Graph
        $og_url = null;
        foreach (array('title', 'image', 'video', 'description', 'type', 'url') as $k) {
            if (waRequest::param('og_'.$k)) {
                if (($k == 'url') && strlen(waRequest::param('og_'.$k))) {
                    $og_url = false;
                } elseif ($og_url === null) {
                    $og_url = true;
                }
                $this->getResponse()->setOGMeta('og:'.$k, waRequest::param('og_'.$k));
            }
        }
        if ($og_url) {
            $og_url = wa()->getConfig()->getHostUrl().wa()->getConfig()->getRequestUrl(false, true);
            $this->getResponse()->setOGMeta('og:url', $og_url);
        }

        /**
         * @event frontend_homepage
         * @return array[string]string $return[%plugin_id%] html output for head section
         */
        $this->view->assign('frontend_homepage', wa()->event('frontend_homepage'));

        $units = shopHelper::getUnits();
        $this->view->assign('units', $units);
        $this->view->assign('formatted_units', shopFrontendProductAction::formatUnits($units));
        $this->view->assign('fractional_config', shopFrac::getFractionalConfig());
        unset($units);

        $this->getResponse()->setCanonical();

        $this->setThemeTemplate('home.html');

    }

    public function display($clear_assign = true)
    {
        /**
         * @event frontend_nav
         * @return array[string]string $return[%plugin_id%] html output for navigation section
         */
        $this->view->assign('frontend_nav', wa()->event('frontend_nav'));

        /**
         * @event frontend_nav_aux
         * @return array[string]string $return[%plugin_id%] html output for navigation section
         */
        $this->view->assign('frontend_nav_aux', wa()->event('frontend_nav_aux'));

        // set globals
        $params = waRequest::param();
        foreach ($params as $k => $v) {
            if (in_array($k, array('url', 'module', 'action', 'meta_keywords', 'meta_description', 'private',
                'url_type', 'type_id', 'payment_id', 'shipping_id', 'currency', 'stock_id', 'public_stocks'))) {
                unset($params[$k]);
            }
        }
        $this->view->getHelper()->globals($params);

        try {
            return parent::display(false);
        } catch (waException $e) {
            if ($e->getCode() == 404) {
                $url = $this->getConfig()->getRequestUrl(false, true);
                if (substr($url, -1) !== '/' && strpos(substr($url, -5), '.') === false) {
                    wa()->getResponse()->redirect($url.'/', 301);
                }
            }
            /**
             * @event frontend_error
             */
            wa()->event('frontend_error', $e);
            $this->view->assign('error_message', $e->getMessage());
            $code = $e->getCode();
            $this->view->assign('error_code', $code);
            $this->getResponse()->setStatus($code ? $code : 500);
            $this->setThemeTemplate('error.html');
            return $this->view->fetch($this->getTemplate());
        }
    }

    protected function assignUnits($stock_units_ids, $base_units_ids, $all_base_unit_ids)
    {
        $unique_units_ids = $stock_units_ids + $all_base_unit_ids;
        $shop_units = shopHelper::getUnits(true);
        $units = array_intersect_key($shop_units, $unique_units_ids);
        $stock_units = array_intersect_key($shop_units, $stock_units_ids);
        $base_units = array_intersect_key($shop_units, $base_units_ids);
        $all_base_units = array_intersect_key($shop_units, $all_base_unit_ids);

        $filter_unit = null;
        $filter_unit_id = waRequest::get("unit", null, "int");
        if ($filter_unit_id && isset($units[$filter_unit_id])) {
            $filter_unit = $units[$filter_unit_id];
        } elseif (count($stock_units) === 1) {
            $filter_unit = reset($stock_units);
        }

        $this->view->assign('filter_unit', $filter_unit);
        $this->view->assign('filter_units', $units);
        $this->view->assign('formatted_filter_units', shopFrontendProductAction::formatUnits($units));
        $this->view->assign('filter_base_units', $base_units);
        $this->view->assign('filter_stock_units', $stock_units);
        $this->view->assign('filter_all_base_units', $all_base_units);
    }
}
