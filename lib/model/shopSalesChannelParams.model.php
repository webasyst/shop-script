<?php
class shopSalesChannelParamsModel extends waModel
{
    protected $table = 'shop_sales_channel_params';

    public function get(int $id): array
    {
        $params = [];
        foreach ($this->getByField('channel_id', $id, true) as $p) {
            $params[$p['name']] = $p['value'];
        }
        return $params;
    }

    public function set(int $id, array $params)
    {
        // candidate to delete
        $delete_params = $this->get($id);

        $add_params = [];
        foreach ($params as $name => $value) {
            unset($delete_params[$name]);
            $add_params[] = [
                'channel_id' => $id,
                'name' => $name,
                'value' => $value,
            ];
        }
        if ($add_params) {
            $this->multipleInsert($add_params, ['value=VALUES(value)']);
        }
        if ($delete_params) {
            $this->deleteByField([
                'channel_id' => $id,
                'name' => array_keys($delete_params),
            ]);
        }
    }

    public function update($id, $params)
    {
        $add_params = [];
        foreach ($params as $name => $value) {
            $add_params[] = [
                'channel_id' => $id,
                'name' => $name,
                'value' => $value,
            ];
        }
        if ($add_params) {
            $this->multipleInsert($add_params, ['value=VALUES(value)']);
        }
    }

    public function clear($id)
    {
        $this->deleteByField('channel_id', $id);
    }

    public function load(array &$channels)
    {
        $params = [];
        $rows = $this->getByField('channel_id', array_column($channels, 'id'), true);
        foreach ($rows as $p) {
            $params[$p['channel_id']][$p['name']] = $p['value'];
        }
        foreach ($channels as &$channel) {
            $channel['params'] = ifset($params, $channel['id'], []);
        }
        unset($channel);
        return $params;
    }
}
