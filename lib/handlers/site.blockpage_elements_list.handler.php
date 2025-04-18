<?php

class shopSiteBlockpage_elements_listHandler extends waEventHandler
{
    public function execute(&$params)
    {
        return [
            'title' => _w('Online store'),
            'app_icon' => 'wa-apps/shop/img/shop.svg',
            'icon' => 'code',
            'tags' => ['shop'],
        ];
    }
}