<?php
/*
 * Formatter for fontend API. Prepares data into strict API format.
 */
abstract class shopFrontApiFormatter
{
    public $options;

    public function __construct(array $options=[])
    {
        $this->options = $options;
    }

    public static function formatPriceField(array $arr, $key, ?string $currency=null)
    {
        if (is_array($key)) {
            foreach ($key as $k) {
                $arr = self::formatPriceField($arr, $k, $currency);
            }
            return $arr;
        }
        if (!isset($arr[$key])) {
            return $arr;
        }
        if ($currency && $currency != '%') {
            $arr[$key.'_exact'] = shop_currency($arr[$key], $currency, $currency, false);
        } else {
            $arr[$key.'_exact'] = (string) $arr[$key];
        }
        $arr[$key] = floatval($arr[$key]);
        if ($currency) {
            if ($currency === '%') {
                $arr[$key.'_str'] = $arr[$key.'_html'] = $arr[$key]."%";
            } else {
                $arr[$key.'_str'] = shop_currency($arr[$key], $currency, $currency);
                $arr[$key.'_html'] = shop_currency_html($arr[$key], $currency, $currency);
            }
        }
        return $arr;
    }

    public static function formatFieldsToType(array $arr, array $schema) 
    {
        foreach ($schema as $k => $type) {
            if (isset($arr[$k])) {
                if (is_array($type)) {
                    $arr[$k] = (array) $arr[$k];
                    if (!empty($type['_multiple'])) {
                        unset($type['_multiple']);
                        $type = ifset($type, '_type', $type);
                        foreach ($arr[$k] as $i => $row) {
                            if (is_array($type)) {
                                $arr[$k][$i] = self::formatFieldsToType((array) $row, $type);
                            } else {
                                $arr[$k][$i] = self::formatFieldToType($row, $type);
                            }
                        }
                    } else {
                        $arr[$k] = self::formatFieldsToType($arr[$k], $type);
                    }
                    continue;
                }
                $arr[$k] = self::formatFieldToType($arr[$k], $type);
            }
        }
        return $arr;
    }

    public static function formatFieldToType($value, string $type)
    {
        switch ($type) {
            case 'integer':
                return intval($value);
            case 'number':
                return floatval($value);
            case 'string':
                return strval($value);
            case 'boolean':
                return boolval($value);
            case 'array':
                return array_values((array) $value);
            case 'object':
                return (array) $value;
            default:
                throw new waException('Unknown type '.wa_dump_helper($type));
        }
    }

    public static function urlToAbsolute($url)
    {
        static $domain_url = null;
        if ($domain_url === null) {
            $domain_url = wa()->getConfig()->getHostUrl();
        }
        if ($url && !preg_match('~^https?://~', $url)) {
            return $domain_url.$url;
        }
        return $url;
    }
}
