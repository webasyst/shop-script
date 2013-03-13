<?php
class shopPluginModel extends shopSortableModel
{
    const TYPE_SHIPPING = 'shipping';
    const TYPE_PAYMENT = 'payment';
    protected $table = 'shop_plugin';
    protected $context = 'type';

    public function listPlugins($type, $options = array())
    {
        $plugins = $this->getByField('type', $type, $this->id);

        return $plugins;
    }

    public function deleteByField($field, $value = null)
    {
        if (is_array($field)) {
            $items = $this->getByField($field, $this->id);
            $ids = array_keys($items);
        } else
            if ($field == $this->id) {
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
}
