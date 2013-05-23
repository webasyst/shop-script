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

        foreach ($routes as $route) {
            $this->routing->setRoute($route);
            // categories
            $categories = $category_model->getByField('status', 1, true);
            $category_url = $this->routing->getUrl($this->app_id.'/frontend/category', array('category_url' => '%CATEGORY_URL%'), true);
            foreach ($categories as $c) {
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

            // main page
            $this->addUrl($this->getUrl(''), time(), self::CHANGE_DAILY, 1);
        }
    }

    private function getUrl($path, $params = array())
    {
        return $this->routing->getUrl($this->app_id.'/frontend'.($path ? '/'.$path : ''), $params, true);
    }
}
