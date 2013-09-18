<?php

class shopSitemapConfig extends waSitemapConfig
{
    protected $app_id;
    public function execute()
    {
        $routes = $this->getRoutes();
        $this->app_id = wa()->getApp();

        $category_model = new shopCategoryModel();
        $product_model = new shopProductModel();
        $page_model = new shopPageModel();

        foreach ($routes as $route) {
            $this->routing->setRoute($route);
            $domain = $this->routing->getDomain(null, true);
            $route_url = $domain.'/'.$this->routing->getRoute('url');
            // categories
            $sql = "SELECT c.id,c.parent_id,c.left_key,c.url,c.full_url,c.create_datetime,c.edit_datetime
                    FROM shop_category c
                    LEFT JOIN shop_category_routes cr ON c.id = cr.category_id
                    WHERE c.status = 1 AND (cr.route IS NULL OR cr.route = '".$category_model->escape($route_url)."')
                    ORDER BY c.left_key";
            $categories = $category_model->query($sql)->fetchAll('id');
            $category_url = $this->routing->getUrl($this->app_id.'/frontend/category', array('category_url' => '%CATEGORY_URL%'), true);
            foreach ($categories as $c_id => $c) {
                if ($c['parent_id'] && !isset($categories[$c_id])) {
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

            // products
            $sql = "SELECT p.url, p.create_datetime, p.edit_datetime";
            if (isset($route['url_type']) && $route['url_type'] == 2) {
                $sql .= ', c.full_url category_url';
            }
            $sql .= " FROM ".$product_model->getTableName().' p';
            if (isset($route['url_type']) && $route['url_type'] == 2) {
                $sql .= " LEFT JOIN ".$category_model->getTableName()." c ON p.category_id = c.id";
            }
            $sql .= ' WHERE p.status = 1';
            if (isset($route['type_id']) && $route['type_id']) {
                $sql .= ' AND p.type_id IN (';
                $first = true;
                foreach ((array)$route['type_id'] as $t) {
                    $sql .= ($first ? '' : ',').(int)$t;
                }
                $sql .= ')';
            }
            $products = $product_model->query($sql);
            $product_url = $this->routing->getUrl($this->app_id.'/frontend/product', array(
                'product_url' => '%PRODUCT_URL%',
                'category_url' => '%CATEGORY_URL%'
            ), true);
            foreach ($products as $p) {
                $url = str_replace(array('%PRODUCT_URL%', '%CATEGORY_URL%'), array($p['url'], isset($p['category_url']) ? $p['category_url'] : ''), $product_url);
                $this->addUrl($url, $p['edit_datetime'] ? $p['edit_datetime'] : $p['create_datetime'], self::CHANGE_MONTHLY, 0.8);
            }

            $main_url = $this->getUrl('');
            // pages
            $sql = "SELECT full_url, url, create_datetime, update_datetime FROM ".$page_model->getTableName().'
                    WHERE status = 1 AND domain = s:domain AND route = s:route';
            $pages = $page_model->query($sql, array('domain' => $domain, 'route' => $route['url']))->fetchAll();
            foreach ($pages as $p) {
                $this->addUrl($main_url.$p['full_url'], $p['update_datetime'] ? $p['update_datetime'] : $p['create_datetime'], self::CHANGE_MONTHLY, 0.6);
            }

            // main page
            $this->addUrl($main_url, time(), self::CHANGE_DAILY, 1);
        }
    }

    private function getUrl($path, $params = array())
    {
        return $this->routing->getUrl($this->app_id.'/frontend'.($path ? '/'.$path : ''), $params, true);
    }
}
