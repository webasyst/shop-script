<?php

class shopFrac
{
    const PLUGIN_MODE_FRAC = 'frac_mode';
    const PLUGIN_MODE_UNITS = 'units_mode';

    const PLUGIN_TRANSFER_DISABLED = 'disabled';
    const PLUGIN_TRANSFER_CONVERT = 'convert';

    public static function isEnabled()
    {
        return wa()->getSetting('frac_enabled', '', 'shop');
    }

    /**
     * @param $quantity
     * @param $denominator
     */
    public static function formatQuantity($quantity, $denominator)
    {
        $denominator = self::correctCountDenominator($denominator);
        // paranoid check
        if ($quantity < 0) {
            $quantity = 1;
        }
        if ($denominator == 1) {
            return (int)round($quantity);
        } else {
            $quantity = round($quantity * $denominator) / $denominator;
            $format = '%.' . abs((int)log10($denominator)) . 'f';
            return sprintf($format, $quantity);
        }
    }

    /**
     * @since 9.0.2
     */
    public static function defracCount($count, $product)
    {
        if ($count === null || $count === '') {
            return null;
        }
        if (!self::isEnabled()) {
            $denominator = 1;
        } else if (!isset($product['count_denominator'])) {
            return $count;
        } else {
            $denominator = $product['count_denominator'];
        }
        return self::formatQuantity($count, $denominator);
    }

    public static function discardZeros($count)
    {
        if ($count === null || $count === '') {
            return null;
        }
        if (!self::isEnabled()) {
            return (int)$count;
        } else {
            return strpos($count, '.') !== false ? rtrim(rtrim($count, '0'), '.') : (int)$count;
        }
    }

    public static function correctCountDenominator($number)
    {
        $is_correct = false;
        foreach (array(1, 10, 100, 1000) as $correct_number) {
            if ($number == $correct_number) {
                $is_correct = true;
            }
        }
        return $is_correct ? (int)$number : 1;
    }

    public static function calculateCountDenominator($order_multiplicity_factor)
    {
        $count_denominator = 1;
        if (is_numeric($order_multiplicity_factor)) {
            while (round($order_multiplicity_factor) != $order_multiplicity_factor) {
                $order_multiplicity_factor *= 10;
                $count_denominator *= 10;
                if ($count_denominator >= 1000) {
                    break;
                }
            }
        }

        return $count_denominator;
    }

    public static function inheritSkuFieldsFromProduct($product, $sku=null)
    {
        if (func_num_args() == 1) {
            if (!empty($product['skus']) && is_array($product['skus'])) {
                $skus = $product['skus'];
                foreach($skus as &$s) {
                    $s = shopFrac::inheritSkuFieldsFromProduct($product, $s);
                }
                unset($s);
                $product['skus'] = $skus;
            }
            return $product;
        } else {
            if (!empty($sku) && is_array($sku)) {
                foreach (['stock_base_ratio', 'order_count_min', 'order_count_step'] as $field) {
                    if (isset($product[$field]) && empty($sku[$field])) {
                        $sku[$field] = $product[$field];
                    }
                }
            }
            return $sku;
        }
    }

    public static function getFractionalConfig()
    {
        return [
            'frac_enabled' =>  wa()->getSetting('frac_enabled', '', 'shop'),
            'stock_units_enabled' => wa()->getSetting('stock_units_enabled'),
            'base_units_enabled' => wa()->getSetting('base_units_enabled'),
        ];
    }

    public static function itemsHaveFractionalQuantity($items)
    {
        foreach ($items as $item) {
            $value = $item['quantity'];
            if (((float)$value) != ((int)$value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $id
     * @param $mode
     * @param $type
     * @return array|int[]|mixed|string|null
     */
    public static function getPluginFractionalMode($id, $mode = self::PLUGIN_MODE_FRAC, $type = shopPluginModel::TYPE_PAYMENT)
    {
        $settings_model = new waAppSettingsModel();

        $plugin_type = $type == shopPluginModel::TYPE_PAYMENT ? shopPluginModel::TYPE_PAYMENT : shopPluginModel::TYPE_SHIPPING;
        $plugin_mode = $mode == self::PLUGIN_MODE_FRAC ? self::PLUGIN_MODE_FRAC : self::PLUGIN_MODE_UNITS;

        $field = sprintf('%s.%s.%s', $plugin_type, $id, $plugin_mode);
        $compatibility = $settings_model->get('shop', $field);
        if ($compatibility == self::PLUGIN_TRANSFER_DISABLED || $compatibility == self::PLUGIN_TRANSFER_CONVERT) {
            return $compatibility;
        }
        return null;
    }

    /**
     * @param $quantity
     * @param $multiplicity
     */
    public static function formatQuantityWithMultiplicity($quantity, $multiplicity)
    {
        return intval(round($quantity / $multiplicity, 8)) * $multiplicity;
    }
}
