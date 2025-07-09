<?php

class shopSiteBlockpage_blocksHandler extends waEventHandler
{
    public function execute(&$params)
    {
        return [
            [
                'image' => '',
                'icon' => 'heading',
                'title' => _w('Product info'),
                'data' => (new shopProductInfoBlockType())->getExampleBlockData(),
                'tags' => ['element', 'shop'],
            ],
            [
                'image' => '',
                'icon' => 'link',
                'title' => _w('Product link'),
                'data' => (new shopProductLinkBlockType())->getExampleBlockData(),
                'tags' => ['element', 'shop'],
            ],
            [
                'image' => '',
                'icon' => 'image',
                'title' => _w('Product picture'),
                'data' => (new shopProductPictureBlockType())->getExampleBlockData(),
                'tags' => ['element', 'shop'],
            ],
            [
                'image' => '',
                'icon' => 'cart-plus',
                'title' => _w('Widget with a shopping button'),
                'data' => (new shopProductSaleWidgetBlockType())->getExampleBlockData(),
                'tags' => ['element', 'shop'],
            ],
        ];
    }
}