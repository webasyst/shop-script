<?php

class shopSitemapConfig extends waSitemapConfig
{
    const URL_FILE_LIMIT = 10000;

    protected $app_id;
    protected $url_file_limit;

    public function __construct()
    {
        parent::__construct();
        $sitemap_limit = wa('shop')->getConfig()->getOption('sitemap_limit');
        $this->url_file_limit = ifset($sitemap_limit, self::URL_FILE_LIMIT);
    }

    public function execute($n = 1)
    {
        $routes = $this->getRoutes();

        $this->app_id = wa()->getApp();

        $category_routes_model = new shopCategoryRoutesModel();
        $product_model = new shopProductModel();

        $count_adding = 0;
        $urls_count = $this->getCountUrl();
        $category_routes = array();
        $real_domain = $this->routing->getDomain(null, true, false);

        if ($urls_count < ($n - 1) * $this->url_file_limit) {
            return null;
        }

        foreach ($routes as $route) {
            $this->routing->setRoute($route);

            // First page of sitemap contains categories and pages
            if ($n == 1) {
                // categories
                $categories   = $this->getCategories($route);
                $category_url = $this->routing->getUrl(
                    $this->app_id.'/frontend/category',
                    array('category_url' => '%CATEGORY_URL%'),
                    true,
                    $real_domain
                );

                foreach ($categories as $c_id => $c) {
                    if ($c['parent_id'] && !isset($categories[$c['parent_id']])) {
                        unset($categories[$c_id]);
                        continue;
                    }
                    if (isset($route['url_type']) && $route['url_type'] == 1) {
                        $url = $c['url'];
                    } else {
                        $url = $c['full_url'];
                    }
                    $this->addUrl(
                        str_replace('%CATEGORY_URL%', $url, $category_url),
                        $c['edit_datetime'] ? $c['edit_datetime'] : $c['create_datetime'],
                        self::CHANGE_WEEKLY,
                        0.6
                    );
                    $count_adding++;
                }

                // shop pages
                $pages = $this->getShopPages($route);
                foreach ($pages as $page) {
                    if (empty($page['url'])) {
                        continue;
                    }
                    $this->addUrl(
                        $page['url'],
                        ifset($page['lastmod'], time()),
                        ifset($page['changefreq'], self::CHANGE_DAILY),
                        ifset($page['priority'], 1)
                    );
                    $count_adding++;
                }
            } else {
                $count_adding += $this->getCategories($route, true) + $this->getShopPages($route, true);
            }
        }

        foreach ($routes as $route) {
            $this->routing->setRoute($route);
            $domain = $this->routing->getDomain(null, true);
            $route_url = $domain.'/'.$this->routing->getRoute('url');
            $count_products = $this->countProductsByRoute($route);

            if ($count_adding + $count_products <= ($n - 1) * $this->url_file_limit) {
                // Skip routes until start of page $n reached
                $count_adding += $count_products;
                continue;
            } else {
                if ($count_adding >= ($n - 1) * $this->url_file_limit) {
                    $offset = 0;
                } else {
                    $offset = ($n - 1) * $this->url_file_limit - $count_adding;
                }
                $count_adding += $offset;
                if ($n * $this->url_file_limit < $count_adding) {
                    $limit = $this->url_file_limit;
                } else {
                    $limit = $n * $this->url_file_limit - $count_adding;
                }
            }

            // SQL for products info
            $sql = "SELECT p.id, p.url, p.create_datetime, p.edit_datetime";
            if (isset($route['url_type']) && $route['url_type'] == 2) {
                $sql .= ', c.full_url category_url, p.category_id';
            }
            $sql .= " FROM ".$product_model->getTableName().' p';
            if (isset($route['url_type']) && $route['url_type'] == 2) {
                $sql .= " LEFT JOIN shop_category c ON p.category_id = c.id";
            }
            $sql .= ' WHERE p.status = 1';
            if (!empty($route['type_id'])) {
                $sql .= ' AND p.type_id IN (i:type_id)';
            }
            $sql .= ' LIMIT '.$offset.','.$limit;
            $products = $product_model->query($sql, $route);

            // Product URL template
            $product_url = $this->routing->getUrl($this->app_id.'/frontend/product', array(
                'product_url' => '%PRODUCT_URL%',
                'category_url' => '%CATEGORY_URL%'
            ), true, $real_domain);

            // Output products, fetching product pages in batch
            $batch_size = 120;
            $iterator = $products->getIterator();
            while($iterator->valid()) {
                // Fetch next $batch_size products
                $ps = array();
                $new_category_ids = array();
                for ($i = 0; $i < $batch_size && $iterator->valid(); $i++) {
                    $p = $iterator->current();
                    $p['pages'] = array();
                    $ps[$p['id']] = $p;
                    $iterator->next();
                    if (!empty($p['category_id'])) {
                        $new_category_ids[$p['category_id']] = $p['category_id'];
                    }
                }

                // Fetch product pages of current batch
                $sql = 'SELECT pp.product_id, pp.url, pp.create_datetime, pp.update_datetime
                        FROM shop_product_pages pp
                        WHERE pp.product_id IN ('.join(',', array_keys($ps)).')
                            AND pp.status = 1';
                $rows = $product_model->query($sql, $route);
                foreach ($rows as $row) {
                    $ps[$row['product_id']]['pages'][] = $row;
                }

                // Fetch info about which categories are enabled for current storefront
                $category_disabled = array();
                $category_routes += $category_routes_model->getRoutes(array_values(array_diff_key($new_category_ids, $category_routes)), false);
                $category_routes += array_fill_keys(array_keys($new_category_ids), null);
                foreach($new_category_ids as $category_id) {
                    if (!empty($category_routes[$category_id])) {
                        if (!in_array($route_url, $category_routes[$category_id])) {
                            $category_disabled[$category_id] = true;
                        }
                    }
                }

                // Add urls to sitemap
                foreach($ps as $p) {
                    if (empty($p['category_id']) || !empty($category_disabled[$p['category_id']])) {
                        $p['category_url'] = null;
                    }
                    if (!empty($p['category_url'])) {
                        $url = str_replace(array('%PRODUCT_URL%', '%CATEGORY_URL%'), array($p['url'], $p['category_url']), $product_url);
                    } else {
                        $url = str_replace(array('%PRODUCT_URL%', '/%CATEGORY_URL%'), array($p['url'], ''), $product_url);
                    }
                    $this->addUrl(
                        $url,
                        $p['edit_datetime'] ? $p['edit_datetime'] : $p['create_datetime'],
                        self::CHANGE_MONTHLY,
                        0.8
                    );
                    $count_adding++;
                    foreach ($p['pages'] as $pp) {
                        $this->addUrl(
                            $url.$pp['url'].'/',
                            $pp['update_datetime'] ? $pp['update_datetime'] : $pp['create_datetime'],
                            self::CHANGE_MONTHLY,
                            0.4
                        );
                        $count_adding++;
                    }
                }
            }

            // Check if end of current sitemap page is reached
            if ($count_adding >= $n * $this->url_file_limit) {
                break;
            }
        }

        foreach ($routes as $route) {
            if ($count_adding < $n * $this->url_file_limit) {
                $plugins_sitemap = $this->getPluginsSitemap($route);

                if ($count_adding + count($plugins_sitemap) <= ($n - 1) * $this->url_file_limit) {
                    $count_adding += count($plugins_sitemap);
                    continue;
                } else {
                    $offset = ($count_adding >= ($n - 1) * $this->url_file_limit ? 0 : ($n - 1) * $this->url_file_limit - $count_adding);
                    $count_adding += $offset;
                    $limit = min($this->url_file_limit, $n * $this->url_file_limit - $count_adding);
                }

                $plugins_sitemap = array_slice(
                    $plugins_sitemap,
                    $offset,
                    min($this->url_file_limit, $limit)
                );

                foreach ($plugins_sitemap as $plugin_sitemap) {
                    if (empty($plugin_sitemap['url'])) {
                        continue;
                    }
                    $this->addUrl(
                        $plugin_sitemap['url'],
                        ifset($plugin_sitemap['lastmod'], time()),
                        ifset($plugin_sitemap['changefreq'], self::CHANGE_DAILY),
                        ifset($plugin_sitemap['priority'], 1)
                    );
                    $count_adding++;
                }
            }

            // Check if end of current sitemap page is reached
            if ($count_adding >= $n * $this->url_file_limit) {
                break;
            }
        }
    }

    /**
     * @param $page_model
     * @param $route
     * @param false $calculate
     * @return int|array
     */
    private function getShopPages($route, $calculate = false)
    {
        $shop_pages = [];
        $page_model = new shopPageModel();
        $pages = $this->getPages($page_model, $route);
        $url   = $this->getUrlByRoute($route);
        foreach ($pages as $p) {
            if (!empty($p['priority']) && $p['priority'] >= 0 && $p['priority'] <= 100) {
                $priority = (int) $p['priority'] / 100.0;
            } else {
                $priority = false;
            }
            if (!$p['url']) {
                if ($priority === false) {
                    $priority = 1;
                }
                $change = self::CHANGE_WEEKLY;
            } else {
                if ($priority === false) {
                    $priority = $p['parent_id'] ? 0.2 : 0.6;
                }
                $change = self::CHANGE_MONTHLY;
            }
            $p['url'] = $url.$p['url'];
            if (strpos($p['url'], '<') === false) {
                $shop_pages[] = [
                    'url'        => $p['url'],
                    'lastmod'    => $p['update_datetime'],
                    'changefreq' => $change,
                    'priority'   => $priority
                ];
            }
        }
        array_unshift($shop_pages, ['url' => $this->getUrl('')]);

        return ($calculate ? count($shop_pages) : $shop_pages);
    }

    /**
     * @param $route
     * @param false $calculate
     * @return int|array
     * @throws waDbException
     */
    protected function getCategories($route, $calculate = false)
    {
        $route_url = $this->routing->getDomain(null, true).'/'.$route['url'];
        $model = new waModel();
        $result = $model->query(
            "SELECT c.id,c.parent_id,c.left_key,c.url,c.full_url,c.create_datetime,c.edit_datetime
                FROM shop_category c
                LEFT JOIN shop_category_routes cr ON c.id = cr.category_id
                WHERE c.status = 1 AND (cr.route IS NULL OR cr.route = '".$model->escape($route_url)."')
                ORDER BY c.left_key"
        );

        return ($calculate ? $result->count() : $result->fetchAll('id'));
    }

    /**
     * @param $route
     * @param false $calculate
     * @return array|int
     * @throws waException
     */
    protected function getPluginsSitemap($route, $calculate = false)
    {
        $result = [];
        /**
         * @event sitemap
         * @param array $route
         * @return array $urls
         */
        $plugin_urls = wa()->event(array('shop', 'sitemap'), $route);
        if ($plugin_urls) {
            foreach ($plugin_urls as $urls) {
                if (!is_array($urls)) {
                    continue;
                }
                foreach ($urls as $url) {
                    $result[] = [
                        'url'        => $url['loc'],
                        'lastmod'    => ifset($url['lastmod']),
                        'changefreq' => ifset($url['changefreq']),
                        'priority'   => ifset($url['priority'])
                    ];
                }
            }
        }

        return ($calculate ? count($result) : $result);
    }

    /**
     * @return int
     * @throws waDbException
     * @throws waException
     */
    protected function getCountUrl()
    {
        wa('shop');
        $cnt = 0;
        $routes = $this->getRoutes('shop');
        foreach ($routes as $r) {
            /** считаем страницы товаров */
            $cnt += $this->countProductsByRoute($r);

            /** считаем страницы категорий */
            $cnt += $this->getCategories($r, true);

            /** считаем дргуие страницы витрины  */
            $cnt += $this->getShopPages($r, true);

            /** считаем страницы от плагинов */
            $cnt += $this->getPluginsSitemap($r, true);
        }

        return (int) $cnt;
    }

    protected function countProductsByRoute($route)
    {
        $model = new waModel();
        $sql = "SELECT COUNT(*) FROM shop_product WHERE status = 1";
        if (!empty($route['type_id'])) {
            $sql .= ' AND type_id IN (i:type_id)';
        }
        return $model->query($sql, $route)->fetchField();
    }

    public function count()
    {
        return ceil($this->getCountUrl() / $this->url_file_limit);
    }

    private function getUrl($path, $params = array())
    {
        return $this->routing->getUrl($this->app_id.'/frontend'.($path ? '/'.$path : ''), $params, true,
            $this->routing->getDomain(null, true, false));
    }
}
