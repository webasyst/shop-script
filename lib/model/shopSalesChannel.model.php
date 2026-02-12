<?php
class shopSalesChannelModel extends waModel
{
    protected $table = 'shop_sales_channel';

    public function getAll($key = null, $normalize = false)
    {
        $sql = "SELECT *
                FROM {$this->table}
                ORDER BY sort";
        return $this->query($sql)->fetchAll($key, $normalize);
    }

    public function getByField($field, $value = null, $all = false, $limit = false)
    {
        $result = parent::getByField($field, $value, $all, $limit);
        if (is_array($field) ? $value : $all) {
            uasort($result, function($a, $b) {
                return $a['sort'] <=> $b['sort'];
            });
        }
        return $result;
    }

    private function getParams($channels)
    {
        if (!empty($channels)) {
            $sales_channel_params_model = new shopSalesChannelParamsModel();

            $channel_ids = array_column($channels, 'id');
            $channels = array_combine($channel_ids, $channels);
            $params = $sales_channel_params_model->getByField('channel_id', $channel_ids, true);

            $channels = array_map(function ($c) {
                return $c + ['params' => []];
            }, $channels);


            while ($param = array_shift($params)) {
                if (isset($channels[$param['channel_id']])) {
                    if ($param['name'] == 'payment_id_'.$param['value']) {
                        $channels[$param['channel_id']]['params']['payment_ids'][] = $param['value'];
                    } elseif (rtrim($param['name'], '/') == 'pickup_storefront_'.rtrim($param['value'], '/')) {
                        $channels[$param['channel_id']]['params']['pickup_storefronts'][] = rtrim($param['value'], '/');
                    } else {
                        $channels[$param['channel_id']]['params'][$param['name']] = $param['value'];
                    }
                }
            }
        }

        return $channels;
    }

    public function getAllWithParams()
    {
        $channels = $this->getAll();

        return $this->getParams($channels);
    }

    public function getById($value)
    {
        $channel = parent::getById($value);
        if (is_array($value)) {
            return $this->getParams($channel);
        }

        if (!$channel) {
            return null;
        }
        return reset(ref($this->getParams([$channel])));
    }
}