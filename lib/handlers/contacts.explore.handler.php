<?php

class shopContactsExploreHandler extends waEventHandler
{
    public function execute(&$params = null)
    {
        if (!$params) {
            return array(
                'customers' => 'Интернет-магазин',
            );
        }
    }
}