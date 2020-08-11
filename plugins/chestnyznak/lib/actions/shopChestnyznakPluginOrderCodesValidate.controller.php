<?php

class shopChestnyznakPluginOrderCodesValidateController extends waJsonController
{
    protected $code_id;

    public function __construct()
    {
        $m = new shopChestnyznakPluginModel();
        $this->code_id = $m->getProductCode()['id'];
    }

    public function execute()
    {
        $codes = $this->getCodes();
        $parsed = $this->parse($codes);
        $this->response = [
            'parsed' => $parsed
        ];
    }

    protected function parse($data)
    {
        return shopChestnyznakPlugin::parseOrderItemsProductUIDs($data);
    }

    /**
     * @return array $data
     *      string $data[<order_item_id>][<sort>]
     */
    protected function getCodes()
    {
        $data = [];

        $codes_data = $this->getRequest()->post('code');
        $codes_data = is_array($codes_data) ? $codes_data : [];
        $codes_data = isset($codes_data[$this->code_id]) ? $codes_data[$this->code_id] : [];
        $codes_data = is_array($codes_data) ? $codes_data : [];

        foreach ($codes_data as $order_item_id => $codes) {
            if (wa_is_int($order_item_id) && is_array($codes)) {
                foreach ($codes as $sort => $code) {
                    if (wa_is_int($sort)) {
                        $code = is_scalar($code) ? trim(strval($code)) : '';
                        if (strlen($code) > 0) {
                            $data[$order_item_id][$sort] = $code;
                        }
                    }
                }
            }
        }

        return $data;
    }
}
