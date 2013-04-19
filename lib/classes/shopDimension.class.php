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
            $config->getPath('config').'/apps/'.$config->getApplication().'/dimension.php',
            $config->getAppPath().'/lib/config/data/dimension.php',
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
                    $units = array_keys($dimension['units']);
                    $units = array_combine(array_map('_w', $units), $units);
                    if (isset($units[$unit])) {
                        $unit = $units[$unit];
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

    public static function getUnits($type)
    {
        $units = array();
        $instance = self::getInstance();
        if ($type && ($d = $instance->getDimension($type))) {
            if (isset($d['units'])) {
                foreach ($d['units'] as $code => $unit) {
                    $units[] = array(
                        'value'       => $code,
                        'title'       => _w($unit['name']),
                        'description' => isset($d['class']) ? $d['class'] : null,
                    );
                }
            }
        }
        return $units;
    }

    public function convert($value, $type, $unit, $value_unit = null)
    {
        if ($dimension = $this->getDimension($type)) {
            if ($value_unit !== null) {
                if (isset($dimension['units'][$value_unit])) {
                    $value = $value * $dimension['units'][$value_unit]['multiplier'];
                }
            }

            if (($unit !== null) && ($unit != $dimension['unit'])) {
                if (isset($dimension['units'][$unit])) {
                    $value = $value / $dimension['units'][$unit]['multiplier'];
                }
            }
        }
        return $value;
    }
}
