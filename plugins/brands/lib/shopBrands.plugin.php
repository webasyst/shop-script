<?php


class shopBrandsPlugin extends shopPlugin
{
    protected function getByTypes($feature_id, $types)
    {
        $product_features_model = new shopProductFeaturesModel();
        $sql = "SELECT DISTINCT pf.feature_value_id FROM ".$product_features_model->getTableName()." pf";
        if ($types) {
            $sql .= " JOIN shop_product p ON pf.product_id = p.id";
        }
        $sql .= " WHERE pf.feature_id = i:0 AND pf.sku_id IS NULL";
        if ($types) {
            $sql .= " AND p.type_id IN (i:1)";
        }
        return $product_features_model->query($sql, $feature_id, $types)->fetchAll(null, true);
    }

    public function frontendNav()
    {
        $feature_id = $this->getSettings('feature_id');
        $feature_model = new shopFeatureModel();
        $feature = $feature_model->getById($feature_id);
        if (!$feature) {
            return;
        }
        $values = $feature_model->getFeatureValues($feature);

        if (waRequest::param('type_id') && is_array(waRequest::param('type_id'))) {
            $types = waRequest::param('type_id');
        } else {
            $types = array();
        }

        $existed = $this->getByTypes($feature['id'], $types);

        $html = '<ul class="menu-v brands">';
        foreach ($values as $v_id => $v) {
            if (in_array($v_id, $existed)) {
                $url = wa()->getRouteUrl('shop/frontend/brand', array('brand' => str_replace('%2F', '/', urlencode($v))));
                $html .= '<li'.($v == waRequest::param('brand') ? ' class="selected"' : '').'><a href="'.$url.'">'.htmlspecialchars($v).'</a></li>';
            }
        }
        $html .= '</ul>';
        return $html;
    }

    public function sitemap($route)
    {
        $feature_id = $this->getSettings('feature_id');
        $feature_model = new shopFeatureModel();
        $feature = $feature_model->getById($feature_id);
        if (!$feature) {
            return;
        }
        $values = $feature_model->getFeatureValues($feature);

        if (!empty($route['type_id']) && is_array($route['type_id'])) {
            $types = $route['type_id'];
        } else {
            $types = array();
        }

        $existed = $this->getByTypes($feature['id'], $types);

        $urls = array();
        $brand_url = wa()->getRouteUrl('shop/frontend/brand', array('brand' => '%BRAND%'), true);
        foreach ($values as $v_id => $v) {
            if (in_array($v_id, $existed)) {
                $urls[] = array(
                    'loc' => str_replace('%BRAND%', str_replace('%2F', '/', urlencode($v)), $brand_url),
                    'changefreq' => waSitemapConfig::CHANGE_MONTHLY,
                    'priority' => 0.2
                );
            }
        }
        if ($urls) {
            return $urls;
        }
    }
}

