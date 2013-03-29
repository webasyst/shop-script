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
            foreach ($categories as $c) {
                if (isset($route['url_type']) && $route['url_type'] == 1) {
                    $url = $c['url'];
                } else {
                    $url = $c['full_url'];
                }
                $this->addUrl($this->getUrl('category', array('category_url' => $url)),
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
            foreach ($products as $p) {
                $category_url = isset($p['category_url']) ? $p['category_url'] : '';
                $this->addUrl($this->getUrl('product', array('product_url' => $p['url'], 'category_url' => $category_url)),
                    $p['edit_datetime'] ? $p['edit_datetime'] : $p['create_datetime'], self::CHANGE_MONTHLY, 0.8);
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
