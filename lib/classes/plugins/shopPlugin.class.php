<?php
abstract class shopPlugin extends waPlugin
{
    /**
     * @var waAppSettingsModel
     */
    protected static $app_settings_model;

    protected $settings;

    public function getControls($params = array())
    {
        $controls = array();
        $settings_config = $this->getSettingsConfig();
        foreach ($settings_config as $name => $row) {
            if (!empty($params['subject']) && !empty($row['subject']) && !in_array($row['subject'], (array) $params['subject'])) {
                continue;
            }
            $row = array_merge($row, $params);
            $row['value'] = $this->getSettings($name);
            if (isset($row['control_type'])) {
                $controls[$name] = waHtmlControl::getControl($row['control_type'], $name, $row);
            }
        }
        return $controls;
    }

    public function getSettings($name = null)
    {
        if ($this->settings === null) {
            $model = $this->getSettingsModel();
            $this->settings = $model->get(array($this->app_id, $this->id));
            foreach ($this->settings as $key => $value) {
                if (($json = json_decode($value,true)) && is_array($json)) {
                    $this->settings[$key] = $json;
                }
            }
            if ($settings_config = $this->getSettingsConfig()) {
                foreach ($settings_config as $key => $row) {
                    if (!isset($this->settings[$key])) {
                        $this->settings[$key] = isset($row['value']) ? $row['value'] : null;
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

    protected function getSettingsConfig()
    {
        $path = $this->path.'/lib/config/settings.php';
        if (file_exists($path)) {
            return include($path);
        } else {
            return array();
        }
    }

    public function saveSettings($settings = array())
    {
        $settings_config = $this->getSettingsConfig();
        foreach ($settings_config as $name => $row) {
            // remove
            if (!isset($settings[$name])) {
                $this->settings[$name] = isset($row['value']) ? $row['value'] : null;
                $this->getSettingsModel()->del(array($this->app_id, $this->id), $name);
            }
        }
        foreach ($settings as $name => $value) {
            $this->settings[$name] = $value;
            // save to db
            $this->getSettingsModel()->set(array($this->app_id, $this->id), $name, is_array($value) ? json_encode($value) : $value);
        }
    }

    /**
     * @return waAppSettingsModel
     */
    protected function getSettingsModel()
    {
        if (!self::$app_settings_model) {
            self::$app_settings_model = new waAppSettingsModel();
        }
        return self::$app_settings_model;
    }
}
