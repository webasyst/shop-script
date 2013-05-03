<?php

class shopAsiaimportPluginSettingsAction extends waViewAction
{
    // Список импортируемых категорий
    protected function get_import_categories()
    {
        $f = file_get_contents('http://asia-fashion-wholesale.com/');
        $f = str_replace(array("\n"), array(''), $f);
        preg_match('~<ul id="mainmenu-nav">(.+)<\/ul>~imUu', $f, $matches);
        preg_match_all('~cat_([0-9]+).html">(.+) \([0-9]+\)<\/a>~imUu', array_shift($matches), $matches, PREG_PATTERN_ORDER);
        return array_combine($matches[1], $matches[2]);
    }
    
    public function execute()
    {
        $app = wa()->getApp();
        $settings = new waAppSettingsModel();
    
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
                $import_categories = $this->get_import_categories();
                $cache->set($import_categories);
            }
        }
        else
        {
            $settings->set(array($app, 'asiaimport'), 'cache_time', 80000);
            $import_categories = $this->get_import_categories();
        }
        $this->view->assign('import_categories', $import_categories);
        
        // Валюта
        $m = new shopCurrencyModel();
        $this->view->assign('currencies', $m->getCurrencies());
        // $primary = $this->getConfig()->getCurrency();
        
        // Сохраненнные настройки
        if ($selected = $settings->get(array($app, 'asiaimport'), 'selected'))
        {
            $selected = unserialize($selected);
        }
        else
        {
            $selected = array(
                'categories' => array(),
                'currency'   => $this->getConfig()->getCurrency()->code,
                'url'        => 'http://asia-fashion-wholesale.com/welcome/women-dress-new-update/all-selling.php',
                'markup'     => '200',
            );
            $settings->set(array($app, 'asiaimport'), 'selected', serialize($selected));
        }
        $this->view->assign('selected', $selected);
        
        /*
        http://asia-fashion-wholesale.com/welcome/fashion-dresses/prod_47091.html
        <strong>Product Information</strong>
            
            <br>
            Size:Only One Size
            <br>
            Flexibility:Yes
            <br>
            Other accessories:None
            </td>
            
        http://asia-fashion-wholesale.com/welcome/bigPhotos.php?productId=32045
        http://asia-fashion-wholesale.com/welcome/images/uploads/
        <img src="images/uploads/(.*)">
        */
        
    }
}
