<?php

/**
 * Class shopColorValue
 * @property-read int[] $rgb RGB color code
 * @property-read int[] $hsv HSV color code
 * @property-read int[] $cmyk CMYK color code
 * @property-read string $hex HEX color code
 * @property-read string $html Color icon and name
 * @property-read string $icon HTML colorized icon
 * @property-read string $style CSS style for element
 *
 * @property-read array row request rows
 * @property string $value Color name
 * @property int $code
 */
#[\AllowDynamicProperties]
class shopColorValue implements ArrayAccess
{
    const RGB = 'rgb';
    const HEX = 'hex';
    const CMYK = 'cmyk';
    const HSV = 'hsv';

    private $id;
    private $row;
    private $code;
    private $value;
    private $selected;
    private $internal_data;

    public function __construct($row)
    {
        $this->row = $row;
        $this->selected = false;
        foreach ($row as $field => $value) {
            $this->{$field} = $value;
        }
    }

    public function __set($field, $value)
    {
        $this->$field = $value;
        return $value;
    }

    public function __get($field)
    {
        switch ($field) {
            case self::HSV:
            case self::RGB:
            case self::CMYK:
                if (!isset($this->internal_data[$field])) {
                    $this->internal_data[$field] = $this->convert($field, $this->code);
                }
                return $this->internal_data[$field];
            case 'style':
                $style = "";
                if ($this->code !== null) {
                    $d = !!(0xFF & $this->code < 0x7F) + !!(0xFF & ($this->code >> 8) < 0x7F) + 2 * !!(0xFF & ($this->code >> 16) < 0x7F);
                    if ($d > 2) {
                        $color = 0xFFFFFF;
                    } else {
                        $color = 0x000000;
                    }

                    $style .= "color:{$this->convert(self::HEX, $color)};";
                    $style .= "background-color:{$this->hex};";
                }

                return $style;
                break;
            case 'html':
                $name = htmlentities(ifempty($this->value, $this->hex), ENT_QUOTES, 'utf-8');
                $html = '<span style="white-space: nowrap;">%s%s</span>';
                return sprintf($html, $this->icon, $name);
                break;
            case 'icon':
                if ($this->code === null) {
                    return null;
                } else {
                    $html = '<i class="icon16 color" style="background:%s;"></i>';
                    return sprintf($html, $this->hex);
                }
                break;
            case 'compare':
                return trim($this->value);
                break;
            default:
                return isset($this->$field) ? $this->$field : $this->convert($field);
                break;
        }
    }

    /**
     * Returns rows from the constructor
     *
     * @return array
     */
    public function getRaw()
    {
        $raws = array();

        if (!empty($this->row)) {
            foreach ($this->row as $row => $data) {
                $raws[$row] = $this->{$row};
            }
        }

        return $raws;
    }

    private static function getColors($locale = null)
    {
        static $color_spaces = array();
        if ($locale === null) {
            $locale = wa()->getLocale();
        }
        if (!isset($color_spaces[$locale])) {
            $path = dirname(__FILE__).'/../config/data/color.'.$locale.'.php';
            if (file_exists($path)) {
                $color_spaces[$locale] = include($path);
            } elseif ($locale != 'en_US') {
                $color_spaces[$locale] = self::getColors('en_US');
            }
            if (!is_array($color_spaces[$locale])) {
                $color_spaces[$locale] = array();
            }
        }
        return ifset($color_spaces[$locale], reset($color_spaces));
    }

    /**
     * Returns color name written in human language (English or Russian depending on current locale).
     *
     * @param int $code
     * @return string
     */
    public static function getName($code)
    {
        $name = '#'.$code;
        $base = array(
            0xFF & ($code >> 16),
            0xFF & ($code >> 8),
            0xFF & $code,
        );
        $d = 128;
        foreach (self::getColors() as $code => $code_name) {
            $code = array(
                0xFF & ($code >> 16),
                0xFF & ($code >> 8),
                0xFF & $code,
            );
            $d_ = sqrt(pow($base[0] - $code[0], 2) + pow($base[1] - $code[1], 2) + pow($base[2] - $code[2], 2));
            if ($d_ < $d) {
                $d = $d_;
                if (is_array($code_name)) {
                    $name = reset($code_name);
                } else {
                    $name = $code_name;
                }
            }
        }
        return $name;

    }

    /**
     * Returns numerical color code by its name.
     *
     * @param string $name Color name written in human language (English or Russian); e.g., 'blue', 'pink', 'yellow', etc.
     * @return int
     */
    public static function getCode($name)
    {
        $like = 0;
        $code = null;
        $name = mb_strtolower($name, 'utf-8');
        foreach (self::getColors() as $code_ => $code_name) {
            foreach ((array)$code_name as $name_) {
                $name_ = mb_strtolower($name_, 'utf-8');
                similar_text($name, $name_, $percent);
                if (($percent > 60.0) && ($percent > $like)) {
                    $like = $percent;
                    $code = $code_;
                }
            }
        }
        return $code;
    }


    public function __toString()
    {
        return (string)((wa()->getEnv() == 'frontend') ? $this->html : $this->value);
    }

    /**
     * Returns numerical color code in specified format.
     *
     * @param string $format Format string: 'rgb', 'hex', 'cmyk', 'hsv'.
     * @param int $value Color code stored in database table 'shop_feature_values_color'.
     * @param bool $raw Whether numerical color value parts must be returned as an array instead of a string; defaults to false
     * @return string|array
     */
    public function convert($format, $value = null, $raw = false)
    {
        if ($value === null) {
            $value = $this->code;
        }
        $pattern = null;
        if ($value !== null) {

            switch ($format) {
                case self::RGB:
                    $value = array(
                        0xFF & ($value >> 16),
                        0xFF & ($value >> 8),
                        0xFF & $value,
                    );
                    $pattern = 'rgb(%d,%d,%d)';
                    break;

                case self::HEX:
                    $value = sprintf('#%06X', $value);
                    break;

                case self::CMYK:
                    $r = 0xFF & ($value >> 16);
                    $g = 0xFF & ($value >> 8);
                    $b = 0xFF & $value;
                    $c_ = 1.0 - ($r / 255);
                    $m_ = 1.0 - ($g / 255);
                    $y_ = 1.0 - ($b / 255);

                    $black = min($c_, $m_, $y_);
                    $cyan = ($c_ - $black) / (1.0 - $black);
                    $magenta = ($m_ - $black) / (1.0 - $black);
                    $yellow = ($y_ - $black) / (1.0 - $black);
                    $pattern = 'cmyk(%0.2f,%0.2f,%0.2f,%0.2f)';
                    $value = array($cyan, $magenta, $yellow, $black);
                    break;

                case self::HSV:
                    $r = 0xFF & ($value >> 16);
                    $g = 0xFF & ($value >> 8);
                    $b = 0xFF & $value;
                    $rgb_max = max($r, $g, $b);

                    $hsv = array(
                        'hue' => 0,
                        'sat' => 0,
                        'val' => $rgb_max,
                    );

                    if ($hsv['val'] > 0) {
                        /* Normalize value to 1 */
                        $r /= $hsv['val'];
                        $g /= $hsv['val'];
                        $b /= $hsv['val'];

                        $rgb_min = min($r, $g, $b);
                        $rgb_max = max($r, $g, $b);


                        $hsv['sat'] = $rgb_max - $rgb_min;
                        if ($hsv['sat'] > 0) {
                            /* Normalize saturation to 1 */
                            $r = ($r - $rgb_min) / ($rgb_max - $rgb_min);
                            $g = ($g - $rgb_min) / ($rgb_max - $rgb_min);
                            $b = ($b - $rgb_min) / ($rgb_max - $rgb_min);
                            $rgb_max = max($r, $g, $b);

                            /* Compute hue */
                            if ($rgb_max == $r) {
                                $hsv['hue'] = 0.0 + 60.0 * ($g - $b);
                                if ($hsv['hue'] < 0.0) {
                                    $hsv['hue'] += 360.0;
                                }
                            } elseif ($rgb_max == $g) {
                                $hsv['hue'] = 120.0 + 60.0 * ($b - $r);
                            } else /* rgb_max == $b */ {
                                $hsv['hue'] = 240.0 + 60.0 * ($r - $g);
                            }
                        }
                    }
                    $pattern = 'hsv(%d,%d,%d)';
                    $value = array_values($hsv);
                    break;

                default:
                    break;
            }

            if (empty($raw) && !empty($pattern)) {
                $value = vsprintf($pattern, (array)$value);
            }
        }
        return $value;
    }

    /**
     * https://www.php.net/manual/ru/migration81.incompatible.php#migration81.incompatible.core.type-compatibility-internal
     *
     * @param $offset
     * @return array|int|int[]|mixed|string|null
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * @param $offset
     * @param $value
     * @return void
     */
    #[ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        return $this->__set($offset, $value);
    }

    /**
     * @param $offset
     * @return void
     */
    #[ReturnTypeWillChange]
    public function offsetUnset($offset)
    {

    }

    /**
     * @param $offset
     * @return bool
     */
    #[ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return true;
    }
}
