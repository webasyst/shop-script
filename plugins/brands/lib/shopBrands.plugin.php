<?php


class shopBrandsPlugin extends shopPlugin
{
    public function frontendNav()
    {
        $feature_id = $this->getSettings('feature_id');
        $feature_model = new shopFeatureModel();
        $feature = $feature_model->getById($feature_id);
        if (!$feature) {
            return;
        }
        $values = $feature_model->getFeatureValues($feature);
        if ($values) {
            $values = $values[$feature_id];
        }
        $html = '<ul class="menu-v brands">';
        foreach ($values as $v) {
            $url = wa()->getRouteUrl('shop/frontend/brand', array('brand'=>urlencode($v)));
            $html .= '<li'.($v == waRequest::param('brand') ? ' class="selected"' : '').'><a href="'.$url.'">'.htmlspecialchars($v).'</a></li>';
        }
        $html .= '</ul>';
        return $html;
    }
}

