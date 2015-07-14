<?php

class shopSitemapConfig extends waSitemapConfig
{
    protected $app_id;
    protected $limit = 10000;
    public function execute($n = 1)
    {
        $routes = $this->getRoutes();

        $this->app_id = wa()->getApp();

        $category_model = new shopCategoryModel();
        $product_model = new shopProductModel();
        $page_model = new shopPageModel();

        $count = 0;

        $real_domain = $this->routing->getDomain(null, true, false);

        foreach ($routes as $route) {
            $this->routing->setRoute($route);
            $domain = $this->routing->getDomain(null, true);
            $route_url = $domain.'/'.$this->routing->getRoute('url');

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

                $main_url = $this->getUrl('');
                // pages
                $sql = "SELECT full_url, url, create_datetime, update_datetime FROM ".$page_model->getTableName().'
                        WHERE status = 1 AND domain = s:domain AND route = s:route';
                $pages = $page_model->query($sql, array('domain' => $domain, 'route' => $route['url']))->fetchAll();
                foreach ($pages as $p) {
                    $this->addUrl($main_url.$p['full_url'], $p['update_datetime'] ? $p['update_datetime'] : $p['create_datetime'], self::CHANGE_MONTHLY, 0.6);
                }

                /**
                 * @event sitemap
                 * @param array $route
                 * @return array $urls
                 */
                $plugin_urls = wa()->event(array($this->app_id, 'sitemap'), $route);
                if ($plugin_urls) {
                    foreach ($plugin_urls as $urls) {
                        foreach ($urls as $url) {
                            $this->addUrl($url['loc'], ifset($url['lastmod'], time()), ifset($url['changefreq']), ifset($url['priority']));
                        }
                    }
                }

                // main page
                $this->addUrl($main_url, time(), self::CHANGE_DAILY, 1);
            }

            // products
            $c = $this->countProductsByRoute($route);

            if ($count + $c <= ($n - 1) * $this->limit) {
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

            $sql = "SELECT p.id, p.url, p.create_datetime, p.edit_datetime";
            if (isset($route['url_type']) && $route['url_type'] == 2) {
                $sql .= ', c.full_url category_url';
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

            // products pages
            try {
                $sql = "SELECT p.id FROM " . $product_model->getTableName() . ' p WHERE p.status = 1';
                if (!empty($route['type_id'])) {
                    $sql .= ' AND p.type_id IN (i:type_id)';
                }
                $sql .= ' LIMIT ' . $offset . ',' . $limit;
                $sql = 'SELECT pp.product_id, pp.url, pp.create_datetime, pp.update_datetime
                        FROM shop_product_pages pp JOIN (' . $sql . ') as t ON pp.product_id = t.id
                        WHERE pp.status = 1';
                $rows = $product_model->query($sql, $route);
                $products_pages = array();
                foreach ($rows as $row) {
                    $products_pages[$row['product_id']][] = $row;
                }
            } catch (waDbException $e) {
                $products_pages = array();
            }

            $product_url = $this->routing->getUrl($this->app_id.'/frontend/product', array(
                'product_url' => '%PRODUCT_URL%',
                'category_url' => '%CATEGORY_URL%'
            ), true, $real_domain);

            foreach ($products as $p) {
                if (!empty($p['category_url'])) {
                    $url = str_replace(array('%PRODUCT_URL%', '%CATEGORY_URL%'), array($p['url'], $p['category_url']), $product_url);
                } else {
                    $url = str_replace(array('%PRODUCT_URL%', '/%CATEGORY_URL%'), array($p['url'], ''), $product_url);
                }
                $this->addUrl($url, $p['edit_datetime'] ? $p['edit_datetime'] : $p['create_datetime'], self::CHANGE_MONTHLY, 0.8);

                if (isset($products_pages[$p['id']])) {
                    foreach ($products_pages[$p['id']] as $pp) {
                        $this->addUrl($url.$pp['url'].'/', $pp['update_datetime'] ? $pp['update_datetime'] : $pp['create_datetime'], self::CHANGE_MONTHLY, 0.4);
                    }
                }
            }

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
