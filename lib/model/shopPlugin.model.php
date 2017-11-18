<?php

class shopPluginModel extends shopSortableModel
{
    const TYPE_SHIPPING = 'shipping';
    const TYPE_PAYMENT = 'payment';
    protected $table = 'shop_plugin';
    protected $context = 'type';

    /**
     *
     * List available plugins of specified type
     * @param string $type plugin type
     * @param array $options
     * @return array[]
     */
    public function listPlugins($type, $options = array())
    {
        $fields = array(
            'type' => $type,
        );

        if (empty($options['all'])) {
            $fields['status'] = 1;
        }

        if (!empty($options['id'])) {
            $fields['id'] = $options['id'];
        }

        $plugins = $this->getByField($fields, $this->id);
        $complementary = ($type == self::TYPE_PAYMENT) ? self::TYPE_SHIPPING : self::TYPE_PAYMENT;
        $non_available = array();
        if (!empty($options[$complementary])) {
            $non_available = shopHelper::getDisabledMethods($type, $options[$complementary]);
        }
        foreach ($plugins as & $plugin) {
            $plugin['available'] = !in_array($plugin['id'], $non_available);
        }
        unset($plugin);
        return $plugins;
    }

    public function deleteByField($field, $value = null)
    {
        if ($field == $this->id) {
            $ids = $value;
        } else {
            if (is_array($field)) {
                $where = $this->getWhereByField($field);
            } else {
                $where = $this->getWhereByField($field, $value);
            }
            $ids = $this->select($this->id)->where($where)->fetchField($this->id);
        }
        $res = false;
        if ($ids) {
            if ($res = parent::deleteByField($this->id, $ids)) {
                $model = new shopPluginSettingsModel();
                $model->deleteByField('id', $ids);
            }
        }
        return $res;
    }

    public function insert($data, $type = 0)
    {
        $this->encodeOptions($data);
        return parent::insert($data, $type);
    }

    public function updateByField($field, $value, $data = null, $options = null, $return_object = false)
    {
        if (is_array($field)) {
            $plugin = &$value;
        } else {
            $plugin = &$data;
        }
        if (isset($plugin['options'])) {
            $this->encodeOptions($plugin);
        }
        unset($plugin);
        return parent::updateByField($field, $value, $data, $options, $return_object);
    }

    public function getByField($field, $value = null, $all = false, $limit = false)
    {
        $result = parent::getByField($field, $value, $all, $limit);
        if ($result) {
            $as_array = is_array($field) ? $value : $all;
            if ($as_array) {
                $plugins =& $result;
            } else {
                $plugins = array(
                    &$result,
                );
            }
            foreach ($plugins as &$plugin) {
                $this->decodeOptions($plugin);
                unset($plugin);
            }
            unset($plugins);
        }
        return $result;
    }

    public function getPlugin($id, $type)
    {
        return $this->getByField(array($this->id => $id, $this->context => $type));
    }

    protected function decodeOptions(&$plugin)
    {
        if (!empty($plugin['options'])) {
            $plugin['options'] = @json_decode($plugin['options'], true);
        }
        if (empty($plugin['options'])) {
            $plugin['options'] = array();
        }
    }

    protected function encodeOptions(&$plugin)
    {
        if (empty($plugin['options'])) {
            $plugin['options'] = array();
        }
        $plugin['options'] = @json_encode($plugin['options']);
    }
}
