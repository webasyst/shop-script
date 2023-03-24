<?php

class shopOrderEditorConfig implements ArrayAccess
{
    const FIELDS_TYPE_PERSON = 'person';
    const FIELDS_TYPE_COMPANY = 'company';
    const FIELDS_TYPE_ADDRESS = 'address';

    const NAME_FORMAT_FULL = 'full';
    const NAME_FORMAT_ONE_FIELD = 'one_field';

    /**
     * @var array
     */
    protected $config;

    protected $field_blocks = [self::FIELDS_TYPE_PERSON, self::FIELDS_TYPE_COMPANY, self::FIELDS_TYPE_ADDRESS];

    protected $name_format_full_fields = ['firstname', 'middlename', 'lastname'];
    protected $name_format_one_fields = ['name'];

    public function __construct()
    {
        $this->loadConfig();
        $this->prepareConfig();
    }

    public function getFieldList($fields_type)
    {
        $fields = !empty($this->config['fields'][$fields_type]) ? $this->config['fields'][$fields_type] : [];
        foreach ($fields as $field_id => $field_params) {
            if (empty($field_params['used'])) {
                unset($fields[$field_id]);
            }
        }
        return $fields;
    }

    public function setData($data)
    {
        if (!is_array($data)) {
            $data = [];
        }
        $this->config = $data;
        $this->prepareConfig();
    }

    public function commit()
    {
        $this->prepareConfig();

        $path = $this->getConfigPath();
        return waUtils::varExportToFile($this->config, $path);
    }

    public function getNameFormatVariants()
    {
        return [
            self::NAME_FORMAT_FULL => [
                'name' => _w('3 separate fields to enter name parts'),
            ],
            self::NAME_FORMAT_ONE_FIELD => [
                'name' => _w('1 common field to enter a full name'),
            ],
        ];
    }

    protected function loadConfig()
    {
        if ($this->config) {
            return;
        }

        $path = $this->getConfigPath();
        if (file_exists($path)) {
            $config = include($path);
        }

        $config = (!empty($config) && is_array($config)) ? $config : [];

        $this->config = $config;
    }

    protected function prepareConfig()
    {
        $config = [];

        // Prepare root setting
        $config['use_custom_config'] = (bool)ifempty($this->config, 'use_custom_config', false);

        if (!$config['use_custom_config']) {
            $this->config = $config;
            return;
        }

        // Prepare name format
        $config['name_format'] = ifempty($this->config, 'name_format', self::NAME_FORMAT_FULL);
        if ($config['name_format'] !== self::NAME_FORMAT_ONE_FIELD) {
            $config['name_format'] = self::NAME_FORMAT_FULL;
        }

        // Prepare source
        $config['source'] = !empty($this->config['source']) && is_scalar($this->config['source']) ? $this->config['source'] : null;

        // Prepare fixed_delivery_area
        $config['fixed_delivery_area'] = !empty($this->config['fixed_delivery_area']) && is_array($this->config['fixed_delivery_area']) ? $this->config['fixed_delivery_area'] : [];

        // Prepare fields
        foreach ($this->field_blocks as $field_block) {

            $config['fields'][$field_block] = isset($this->config['fields'][$field_block]) && is_array($this->config['fields'][$field_block]) ? $this->config['fields'][$field_block] : [];

            foreach ($config['fields'][$field_block] as $field_id => $params) {
                $config['fields'][$field_block][$field_id] = [
                    'used'     => (bool)ifset($params, 'used', false),
                    'required' => (bool)ifset($params, 'required', false),
                ];

                if (!$config['fields'][$field_block][$field_id]['used']) {
                    $config['fields'][$field_block][$field_id]['required'] = false;
                }
            }
        }

        // Full name format
        if ($this->config['name_format'] == self::NAME_FORMAT_FULL) {
            $all_name_format_fields_not_used = true;
            foreach ($this->name_format_full_fields as $field_id) {
                if (!empty($config['fields'][self::FIELDS_TYPE_PERSON][$field_id]['used'])) {
                    $all_name_format_fields_not_used = false;
                }
            }

            // Use all fields for the full name if they are all off
            if ($all_name_format_fields_not_used) {
                foreach ($this->name_format_full_fields as $field_id) {
                    $config['fields'][self::FIELDS_TYPE_PERSON][$field_id]['used'] = true;
                }
            }

            // Never use the common name field
            foreach ($this->name_format_one_fields as $field_id) {
                unset($config['fields'][self::FIELDS_TYPE_PERSON][$field_id]);
            }
        }

        // One field name format
        if ($this->config['name_format'] == self::NAME_FORMAT_ONE_FIELD) {
            // The name field is always used
            foreach ($this->name_format_one_fields as $field_id) {
                $config['fields'][self::FIELDS_TYPE_PERSON][$field_id]['used'] = true;
            }

            // Never use individual name fields
            foreach ($this->name_format_full_fields as $field_id) {
                unset($config['fields'][self::FIELDS_TYPE_PERSON][$field_id]);
            }
        }

        // Prepare billing address
        $config['billing_address']['person'] = !empty($this->config['billing_address']['person']);
        $config['billing_address']['company'] = !empty($this->config['billing_address']['company']);

        $this->config = $config;
    }

    protected function getConfigPath()
    {
        return wa()->getConfig()->getConfigPath('order_editor.php', true, 'shop');
    }

    /**
     * https://www.php.net/manual/ru/migration81.incompatible.php#migration81.incompatible.core.type-compatibility-internal
     *
     * @param $offset
     * @param $value
     * @return void
     */
    #[ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->config[] = $value;
        } else {
            $this->config[$offset] = $value;
        }
    }

    #[ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->config[$offset]);
    }

    #[ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->config[$offset]);
    }

    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return isset($this->config[$offset]) ? $this->config[$offset] : null;
    }
}