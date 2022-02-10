<?php

class shopUnits
{
    public static function isEnabled()
    {
        return self::baseUnitsEnabled() || self::stockUnitsEnabled();
    }

    public static function baseUnitsEnabled()
    {
        return wa()->getSetting('base_units_enabled', '', 'shop');
    }

    public static function stockUnitsEnabled()
    {
        return wa()->getSetting('stock_units_enabled', '', 'shop');
    }

    public static function itemsHaveCustomStockUnits($items)
    {
        foreach($items as $item) {
            if (isset($item['item']['stock_unit_id'])) {
                // paranoid mode: shopOrder['extended_items']
                $stock_unit_id = $item['item']['stock_unit_id'];
            } else if (isset($item['stock_unit_id'])) {
                // shopOrder['items']
                $stock_unit_id = $item['stock_unit_id'];
            } else if (isset($item['product']['stock_unit_id'])) {
                // items during frontend checkout
                $stock_unit_id = $item['product']['stock_unit_id'];
            } else {
                $stock_unit_id = 0;
            }
            if ($stock_unit_id != 0) {
                return true;
            }
        }
        return false;
    }
}
