<?php

class shopDimension
{
    /**
     *
     * @var shopDimension
     */
    private static $instance;
    private $units = array();

    private function __construct()
    {
        $config = wa('shop')->getConfig();
        $files = array(
            $config->getConfigPath('dimension.php'),
            $config->getConfigPath('data/dimension.php', false),

        );
        foreach ($files as $file_path) {
            if (file_exists($file_path)) {
                $config = include($file_path);
                if ($config && is_array($config)) {
                    $this->units = $config;
                    break;
                }
            }
        }
        waHtmlControl::registerControl(__CLASS__, array(__CLASS__, 'getControl'));
    }

    private function __clone()
    {
        ;
    }

    public function getList()
    {
        return $this->units;
    }

    /**
     *
     * @return shopDimension
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getDimension($type)
    {
        $type = preg_replace('/^[^\.]*\./', '', $type);
        $d = null;
        if (isset($this->units[$type])) {
            $d = $this->units[$type];
        }
        return $d;
    }

    public function getBaseUnitCode($type)
    {
        $unit = null;
        $type = preg_replace('/\..*$/', '', $type);
        if ($dimension = $this->getDimension($type)) {
            $unit = $type.'.'.$dimension['base_unit'];
        }
        return $unit;
    }

    public function fixUnit($type, $unit = null)
    {
        if ($unit && $type && ($dimension = $this->getDimension($type))) {
            if ($unit != $dimension['base_unit']) {
                if ($unit == _w($dimension['base_unit'])) {
                    $unit = $dimension['base_unit'];
                } elseif (!isset($dimension['units'][$unit])) {
                    $unit_map = array();
                    foreach ($dimension['units'] as $_unit => $_name) {
                        $unit_map[$_name['name']] = $_unit;
                    }
                    if (isset($unit_map[$unit])) {
                        $unit = $unit_map[$unit];
                    } else {
                        $unit = $dimension['base_unit'];
                    }
                }
            }
        }
        return $unit;
    }

    public static function getControl($name, $params = array())
    {

        $control = '';
        $instance = self::getInstance();
        $type = isset($params['type']) ? $params['type'] : false;
        if ($type && ($d = $instance->getDimension($type))) {
            $unit = '';
            if (!isset($params['value'])) {
                $params['value'] = array();
            }
            $params['value'] = array_merge(array('value' => '0', 'unit' => $unit), $params['value']);

            waHtmlControl::addNamespace($params, $name);
            $control .= waHtmlControl::getControl(waHtmlControl::INPUT, 'value', $r = array_merge($params, array('value' => $params['value']['value'])));

            if ($params['options'] = self::getUnits($type)) {
                unset($params['title']);
                $control .= waHtmlControl::getControl(waHtmlControl::SELECT, 'unit', $t = array_merge($params, array('value' => $params['value']['unit'])));
            }
        } else {
            $control .= '—';
        }
        return $control;

    }

    /**
     * @param $type
     * @return array
     */
    public static function getBaseUnit($type)
    {
        $instance = self::getInstance();
        if ($type && ($d = $instance->getDimension($type))) {
            $units = self::getUnits($type);
            if (isset($units[$d['base_unit']])) {
                return $units[$d['base_unit']];
            }
        }
        return array();
    }

    /**
     * @param $type
     * @param $unit
     * @return array
     */
    public static function getUnit($type, $unit)
    {
        $instance = self::getInstance();
        if ($type && ($d = $instance->getDimension($type))) {
            $units = self::getUnits($type);
            if (isset($units[$unit])) {
                return $units[$unit];
            }
        }
        return array();
    }

    public static function getUnits($type)
    {
        $units = array();
        $instance = self::getInstance();
        if ($type && ($d = $instance->getDimension($type))) {
            if (isset($d['units'])) {
                foreach ($d['units'] as $code => $unit) {
                    $units[$code] = array(
                        'value'       => $code,
                        'title'       => $unit['name'],
                        'description' => isset($d['class']) ? $d['class'] : null,
                    );
                }
            }
        }
        return $units;
    }

    public static function castUnit($type, $unit)
    {
        $units = self::getUnits($type);

        if (!isset($units[$unit])) {
            foreach ($units as $u) {
                if (strcasecmp($u['value'], $unit) === 0) {
                    $unit = $u['value'];
                    break;
                }
                if (strcasecmp($u['title'], $unit) === 0) {
                    $unit = $u['value'];
                    break;
                }
            }
        }
        return $unit;
    }

    /**
     *
     * Convert dimension values
     *
     * @param double $value
     * @param string $type dimension type
     * @param string $unit target dimension unit, default is base_unit
     * @param string $value_unit value dimension unit, default is base_unit
     *
     * @return double
     */
    public function convert($value, $type, $unit, $value_unit = null)
    {
        if ($dimension = $this->getDimension($type)) {
            if ($value_unit !== null) {
                if (isset($dimension['units'][$value_unit])) {
                    $value = $value * $dimension['units'][$value_unit]['multiplier'];
                }
            }

            if (($unit !== null) && ($unit != $dimension['base_unit'])) {
                if (isset($dimension['units'][$unit])) {
                    $value = $value / $dimension['units'][$unit]['multiplier'];
                }
            }
        }
        return $value;
    }
}
