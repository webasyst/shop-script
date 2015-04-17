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
        if (is_array($field)) {
            $items = $this->getByField($field, $this->id);
            $ids = array_keys($items);
        } elseif ($field == $this->id) {
            $ids = $value;
        } else {
            $items = $this->getByField($field, $value, $this->id);
            $ids = array_keys($items);
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

    public function getPlugin($id, $type)
    {
        return $this->getByField(array($this->id => $id, $this->context => $type));
    }
}
