<?php
/**
 * @since 11.0.0
 */
class shopFiscalization
{
    public $order_params;
    public $order_id;
    protected static $params_model;

    public function __construct(int $order_id)
    {
        if (empty(self::$params_model)) {
            self::$params_model = new shopOrderParamsModel();
        }
        $this->order_id = $order_id;
        $this->order_params = self::$params_model->get($order_id);
    }

    public function isFiscalized()
    {
        return !empty($this->order_params['fiscalization_datetime']);
    }

    public function declareFiscalization($plugin_type, $plugin_id, array $custom_data=null)
    {
        if ($this->isFiscalized()) {
            throw new waException('Fiscalization already applied');
        }
        if (!in_array($plugin_type, ['payment', 'shipping', 'shop_plugin', 'app', 'app_plugin'])) {
            throw new waException('Unknown plugin type');
        }

        $data = [
            'fiscalization_datetime' => date('Y-m-d H:i:s'),
            'fiscalization_plugin_type' => $plugin_type,
            'fiscalization_plugin_id' => $plugin_id,
        ];
        if (isset($custom_data)) {
            foreach($custom_data as $k => $v) {
                $data['fiscalization_data_'.$k] = (string) $v;
            }
        }

        self::$params_model->set($this->order_id, $data, false);
        $this->order_params = array_merge($this->order_params, $data);
    }

    public function cancelFiscalization()
    {
        $data = [
            'fiscalization_datetime' => null,
            'fiscalization_plugin_type' => null,
            'fiscalization_plugin_id' => null,
        ];
        foreach($this->order_params as $k => $v) {
            if (substr($k, 0, 19) == 'fiscalization_data_') {
                $data[$k] = null;
            }
        }
        self::$params_model->set($this->order_id, $data, false);
        $this->order_params = array_diff_key($this->order_params, $data);
    }
}
