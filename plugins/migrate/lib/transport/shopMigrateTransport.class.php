<?php

abstract class shopMigrateTransport implements Serializable
{

    const LOG_DEBUG = 5;
    const LOG_INFO = 4;
    const LOG_NOTICE = 3;
    const LOG_WARNING = 2;
    const LOG_ERROR = 1;

    private $temp_path;
    /**
     *
     * @var shopConfig
     */
    private $wa;

    private $options = array();
    protected $map = array();
    protected $offset = array();

    /**
     * @var waModel
     */
    protected $dest;

    /**
     * Get migrate transport instance
     * @param string $id transport id
     * @param array $options
     * @throws waException
     * @return shopMigrateTransport
     */
    public static function getTransport($id, $options = array())
    {
        $class = 'shopMigrate'.ucfirst($id).'Transport';
        if ($id && class_exists($class)) {

            if (isset($options['transport'])) {
                unset($options['transport']);
            }
            /**
             * @var shopMigrateTransport $transport
             */
            $transport = new $class($options);
        } else {
            throw new waException('Transport not found');
        }
        if (!($transport instanceof self)) {
            throw new waException('Invalid transport');
        }
        return $transport;
    }

    protected function __construct($options = array())
    {
        $this->initOptions();
        foreach ($options as $k => $v) {
            $this->setOption($k, $v);
        }
        $this->dest = new waModel();
    }

    public function __wakeup()
    {
        $this->dest = new waModel();
    }

    public function validate($result, &$errors)
    {
        return true && $result;
    }

    public function init()
    {

    }

    abstract public function step(&$current, &$count, &$processed, $stage, &$error);

    /**
     * @return string[string]
     */
    abstract public function count();
    abstract public function getStageName($stage);
    abstract public function getStageReport($stage, $data);

    private static function getLogLevelName($level)
    {
        $name = '';
        switch ($level) {
            case self::LOG_DEBUG:
                $name = 'Debug';
                break;
            case self::LOG_INFO:
                $name = 'Info';
                break;
            case self::LOG_NOTICE:
                $name = 'Notice';
                break;
            case self::LOG_WARNING:
                $name = 'Warning';
                break;
            case self::LOG_ERROR:
                $name = 'Error';
                break;
        }
        return $name;
    }

    protected function log($message, $level = self::LOG_WARNING, $data = null)
    {
        if ($level <= $this->getOption('debug', self::LOG_WARNING)) {
            if (!is_string($message)) {
                $message = var_export($message, true);
            }
            if ($data) {
                $message .= "\n".var_export($data, true);
            }
            waLog::log($this->getOption('processId').': '.$this->getLogLevelName($level).': '.$message, 'migrate.log');
        }
    }

    /**
     *
     * @param string $file_prefix
     * @return string
     */
    protected function getTempPath($file_prefix = null)
    {
        if (!$this->temp_path) {
            $this->temp_path = wa()->getTempPath('wa-apps/shop/plugins/migrate/'.$this->getOption('processId'));
            waFiles::create($this->temp_path);
        }
        return ($file_prefix === null) ? $this->temp_path : tempnam($this->temp_path, $file_prefix);
    }

    /**
     *
     * @return shopConfig
     */
    protected function getConfig()
    {
        if (!$this->wa) {
            $this->wa = wa()->getConfig();
        }
        return $this->wa;
    }

    public function restore()
    {
    }

    public function finish()
    {
        waFiles::delete($this->getTempPath(), true);
    }

    protected function initOptions()
    {
        if ($this->getConfig()->isDebug()) {
            $debug_levels = array(
                self::LOG_WARNING => _wp('Errors only'),
                self::LOG_DEBUG => _wp('Debug (detailed log)'),
            );
            $option = array(
                'value'        => self::LOG_WARNING,
                'control_type' => waHtmlControl::SELECT,
                'title'        => _wp('Log level'),
                'options'      => array(),
            );
            $option['options'] = $debug_levels;
            $this->addOption('debug', $option);
        }
    }

    protected function getOption($name, $default = null)
    {
        if (isset($this->options[$name]['value'])) {
            $value = $this->options[$name]['value'];
        } else {
            $value = $default;
        }
        return $value;
    }

    protected function setOption($name, $value)
    {
        if (!isset($this->options[$name])) {
            $this->options[$name] = array(
                'control_type' => waHtmlControl::HIDDEN,
            );
        }
        $this->options[$name]['value'] = $value;
    }

    protected function addOption($name, $option)
    {
        if ($option) {
            if (!isset($this->options[$name])) {
                $this->options[$name] = array_merge(array(
                    'control_type' => waHtmlControl::HIDDEN,
                    'value'        => null,
                ), $option);
            } else {
                $this->options[$name] = array_merge($this->options[$name], $option);
            }
        } elseif (isset($this->options[$name])) {
            unset($this->options[$name]);
        }
    }

    public function getControls($errors = array())
    {
        $controls = array();

        $params = array();
        $params['title_wrapper'] = '<div class="name">%s</div>';
        $params['description_wrapper'] = '<br><span class="hint">%s</span>';
        $params['control_separator'] = '</div><br><div class="value no-shift">';

        $params['control_wrapper'] = '
<div class="field">
%s
<div class="value no-shift">%s%s</div>
</div>';
        foreach ($this->options as $field => $properties) {
            if (!empty($properties['control_type'])) {
                if (!empty($errors[$field])) {
                    if (!isset($properties['class'])) {
                        $properties['class'] = array();
                    }
                    $properties['class'] = array_merge((array) $properties['class'], array('error'));
                    if (!isset($properties['description'])) {
                        $properties['description'] = '';
                    } else {
                        $properties['description'] .= "\n";
                    }
                    $properties['description'] .= $errors[$field];
                }
                $controls[$field] = waHtmlControl::getControl($properties['control_type'], $field, array_merge($params, $properties));
            }
        }
        return $controls;
    }

    public function serialize()
    {
        $data = array();
        $data['map'] = $this->map;
        $data['offset'] = $this->offset;
        $data['options'] = array();
        foreach ($this->options as $name => & $option) {
            if (isset($option['value'])) {
                $data['options'][$name] = $option['value'];
            }

        }
        return serialize($data);
    }

    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        if (!empty($data['options'])) {
            foreach ($data['options'] as $name => $value) {
                $this->setOption($name, $value);
            }
        }
        if (!empty($data['map'])) {
            $this->map = $data['map'];
        }
        if (!empty($data['offset'])) {
            $this->offset = $data['offset'];
        }
        return $this;
    }

}
