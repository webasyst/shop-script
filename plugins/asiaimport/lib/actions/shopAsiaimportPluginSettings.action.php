<?php

class shopAsiaimportPluginSettingsAction extends waViewAction
{
    // Список импортируемых категорий
    protected function get_import_categories($url)
    {
        $url = parse_url($url);
        $url = $url['scheme'] . '://' . $url['host'];
        if( $f = @file_get_contents($url) )
        {
            preg_match('~<ul id="mainmenu-nav">(.+,?)<\/ul>~is', $f, $matches);
            preg_match_all('~cat_([0-9]+).html">(.+?) \([0-9]+\)<\/a>~is', array_shift($matches), $matches, PREG_PATTERN_ORDER);
            return array_combine((array)$matches[1], (array)$matches[2]);
        }
    }
    
    public function execute()
    {
        $app = wa()->getApp();
        $settings = new waAppSettingsModel();
        
        // Сохраненные настройки
        if ($selected = $settings->get(array($app, 'asiaimport'), 'selected'))
        {
            $selected = json_decode($selected, true);
        }
        else
        {
            $selected = array(
                'categories'  => array(),
                'currency'    => $this->getConfig()->getCurrency()->code,
                'url'         => 'http://asia-fashion-wholesale.com/welcome/women-dress-new-update/all-selling.php',
                'images'      => 'http://asia-fashion-wholesale.com/welcome/bigPhotos.php?productId=',
                'images_path' => 'http://asia-fashion-wholesale.com/welcome/images/uploads/',
                'markup'      => '200',
            );
            $settings->set(array($app, 'asiaimport'), 'selected', json_encode($selected));
        }
        $this->view->assign('selected', $selected);
        
        // Категории
        $m = new shopCategoryModel();
        $this->view->assign('categories', $m->getFullTree('id, depth, name', true));
        
        // Импортируемые категории
        if ($cache_time = $settings->get(array($app, 'asiaimport'), 'cache_time')) 
        {   
            $cache = new waSerializeCache('asiaimport', $cache_time, $app);
            if ( $cache->isCached() )
            {
                $import_categories = $cache->get();
            }
            if ( empty($import_categories) )
            {
                $import_categories = $this->get_import_categories($selected['url']);
                $cache->set($import_categories);
            }
        }
        else
        {
            $settings->set(array($app, 'asiaimport'), 'cache_time', 80000);
            $import_categories = $this->get_import_categories($selected['url']);
        }
        $this->view->assign('import_categories', $import_categories);
        
        // Валюта
        $m = new shopCurrencyModel();
        $this->view->assign('currencies', $m->getCurrencies());
    }
}
