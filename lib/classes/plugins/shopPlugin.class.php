<?php

abstract class shopPlugin extends waPlugin
{
    /**
     * @var waAppSettingsModel
     */
    protected static $app_settings_model;

    /**
     * @var mixed[string]
     */
    protected $settings;
    /**
     * @var mixed[string]
     */
    private $settings_config;

    protected $common_settings_config = array();

    /**
     * @param array $params Control items params (see waHtmlControl::getControl for details)
     * @return string[string] Html code of control
     */
    public function getControls($params = array())
    {
        $controls = array();
        $settings_config = $this->getSettingsConfig();
        foreach ($settings_config as $name => $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!empty($params['subject']) && !empty($row['subject']) && !in_array($row['subject'], (array)$params['subject'])) {
                continue;
            }
            $row = array_merge($row, $params);
            $row['value'] = $this->getSettings($name);
            if (!empty($row['control_type'])) {
                $controls[$name] = waHtmlControl::getControl($row['control_type'], $name, $row);
            }
        }
        return $controls;
    }

    /**
     * @param null $name
     * @return array|mixed|null|string
     */
    public function getSettings($name = null)
    {
        if ($this->settings === null) {
            $this->settings = self::getSettingsModel()->get($this->getSettingsKey());
            foreach ($this->settings as $key => $value) {
                #decode non string values
                $json = json_decode($value, true);
                if (is_array($json)) {
                    $this->settings[$key] = $json;
                }
            }
            #merge user settings from database with raw default settings
            if ($settings_config = $this->getSettingsConfig()) {
                foreach ($settings_config as $key => $row) {
                    if (!isset($this->settings[$key])) {
                        $this->settings[$key] = is_array($row) ? (isset($row['value']) ? $row['value'] : null) : $row;
                    }
                }
            }
        }
        if ($name === null) {
            return $this->settings;
        } else {
            return isset($this->settings[$name]) ? $this->settings[$name] : null;
        }
    }

    /**
     * Get raw settings config
     * @return array
     */
    protected function getSettingsConfig()
    {
        if (is_null($this->settings_config)) {
            $path = $this->path.'/lib/config/settings.php';
            if (file_exists($path)) {
                $settings_config = include($path);
                if (!is_array($settings_config)) {
                    $settings_config = array();
                }
            } else {
                $settings_config = array();
            }
            $this->settings_config = array_merge($this->common_settings_config, $settings_config);
        }
        return $this->settings_config;
    }

    /**
     * @param mixed [string] $settings Array of settings key=>value
     */
    public function saveSettings($settings = array())
    {
        $settings_config = $this->getSettingsConfig();
        foreach ($settings_config as $name => $row) {
            if (!isset($settings[$name])) {
                if ((ifset($row['control_type']) == waHtmlControl::CHECKBOX) && !empty($row['value'])) {
                    $settings[$name] = false;
                } elseif ((ifset($row['control_type']) == waHtmlControl::GROUPBOX) && !empty($row['value'])) {
                    $settings[$name] = array();
                } elseif (!empty($row['control_type']) || isset($row['value'])) {
                    $this->settings[$name] = isset($row['value']) ? $row['value'] : null;
                    self::getSettingsModel()->del($this->getSettingsKey(), $name);
                }
            }
        }
        foreach ($settings as $name => $value) {
            $this->settings[$name] = $value;
            // save to db
            self::getSettingsModel()->set($this->getSettingsKey(), $name, is_array($value) ? json_encode($value) : $value);
        }
    }

    /**
     * @return waAppSettingsModel
     */
    protected static function getSettingsModel()
    {
        if (!self::$app_settings_model) {
            self::$app_settings_model = new waAppSettingsModel();
        }
        return self::$app_settings_model;
    }

    protected function getSettingsKey()
    {
        return array($this->app_id, $this->id);
    }
}
