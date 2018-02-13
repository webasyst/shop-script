<?php

class shopSitemapConfig extends waSitemapConfig
{
    protected $app_id;
    protected $limit = 10000;
    public function execute($n = 1)
    {
        $routes = $this->getRoutes();

        $this->app_id = wa()->getApp();

        $category_routes_model = new shopCategoryRoutesModel();
        $category_model = new shopCategoryModel();
        $product_model = new shopProductModel();
        $page_model = new shopPageModel();

        $count = 0;

        $category_routes = array();
        $real_domain = $this->routing->getDomain(null, true, false);

        foreach ($routes as $route) {
            $this->routing->setRoute($route);
            $domain = $this->routing->getDomain(null, true);
            $route_url = $domain.'/'.$this->routing->getRoute('url');

            // First page of sitemap contains categories and pages
            if ($n == 1) {
                // categories
                $sql = "SELECT c.id,c.parent_id,c.left_key,c.url,c.full_url,c.create_datetime,c.edit_datetime
                        FROM shop_category c
                        LEFT JOIN shop_category_routes cr ON c.id = cr.category_id
                        WHERE c.status = 1 AND (cr.route IS NULL OR cr.route = '".$category_model->escape($route_url)."')
                        ORDER BY c.left_key";
                $categories = $category_model->query($sql)->fetchAll('id');
                $category_url = $this->routing->getUrl($this->app_id.'/frontend/category',
                    array('category_url' => '%CATEGORY_URL%'), true, $real_domain);
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
                    $this->addUrl(str_replace('%CATEGORY_URL%', $url, $category_url),
                        $c['edit_datetime'] ? $c['edit_datetime'] : $c['create_datetime'], self::CHANGE_WEEKLY, 0.6);
                }

                // pages
                $this->addPages($page_model,$route);

                /**
                 * @event sitemap
                 * @param array $route
                 * @return array $urls
                 */
                $plugin_urls = wa()->event(array($this->app_id, 'sitemap'), $route);
                if ($plugin_urls) {
                    foreach ($plugin_urls as $urls) {
                        if (!is_array($urls)) {
                            continue;
                        }
                        foreach ($urls as $url) {
                            $this->addUrl($url['loc'], ifset($url['lastmod'], time()), ifset($url['changefreq']), ifset($url['priority']));
                        }
                    }
                }

                // main page
                $this->addUrl($this->getUrl(''), time(), self::CHANGE_DAILY, 1);
            }

            // count products for pagination
            $c = $this->countProductsByRoute($route);

            if ($count + $c <= ($n - 1) * $this->limit) {
                // Skip routes until start of page $n reached
                $count += $c;
                continue;
            } else {
                if ($count >= ($n - 1) * $this->limit) {
                    $offset = 0;
                } else {
                    $offset = ($n - 1) * $this->limit - $count;
                }
                $count += $offset;
                $limit = min($this->limit, $n * $this->limit - $count);
            }

            // SQL for products info
            $sql = "SELECT p.id, p.url, p.create_datetime, p.edit_datetime";
            if (isset($route['url_type']) && $route['url_type'] == 2) {
                $sql .= ', c.full_url category_url, p.category_id';
            }
            $sql .= " FROM ".$product_model->getTableName().' p';
            if (isset($route['url_type']) && $route['url_type'] == 2) {
                $sql .= " LEFT JOIN ".$category_model->getTableName()." c ON p.category_id = c.id";
            }
            $sql .= ' WHERE p.status = 1';
            if (!empty($route['type_id'])) {
                $sql .= ' AND p.type_id IN (i:type_id)';
            }
            $sql .= ' LIMIT '.$offset.','.$limit;
            $products = $product_model->query($sql, $route);
            $count += $products->count();

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
                    $this->addUrl($url, $p['edit_datetime'] ? $p['edit_datetime'] : $p['create_datetime'], self::CHANGE_MONTHLY, 0.8);
                    foreach ($p['pages'] as $pp) {
                        $this->addUrl($url.$pp['url'].'/', $pp['update_datetime'] ? $pp['update_datetime'] : $pp['create_datetime'], self::CHANGE_MONTHLY, 0.4);
                    }
                }
            }

            // Check if end of current sitemap page is reached
            if ($count >= $n * $this->limit) {
                break;
            }
        }
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
        $routes = $this->getRoutes('shop');
        $c = 0;
        foreach ($routes as $r) {
            $c += $this->countProductsByRoute($r);
        }
        return ceil($c / $this->limit);
    }

    private function getUrl($path, $params = array())
    {
        return $this->routing->getUrl($this->app_id.'/frontend'.($path ? '/'.$path : ''), $params, true,
            $this->routing->getDomain(null, true, false));
    }
}
