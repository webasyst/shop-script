<?php

class shopChestnyznakPlugin extends shopPlugin
{
    public function orderActionForm(&$params)
    {
        $model = new shopChestnyznakPluginModel();
        $product_code = $model->getProductCode();

        // In case user deleted the code, create it again
        if (empty($product_code['id'])) {
            $images = $this->getPluginImages();
            $model->setupProductCode($images);
            $code = $model->getProductCode();
            if (empty($code['id'])) {
                return; // should never happen
            }
        } elseif ($product_code['name'] != shopChestnyznakPluginModel::PRODUCT_CODE_NAME) {
            $model->setName($product_code['id']);
        }

        // this takes localization from shop.po
        $validation_error_message = htmlspecialchars(_w('Values must be unique'));

        $codes = $this->getOrderItemsCodes($params['order_id'], $product_code['id']);

        $options = [
            'product_code_id' => $product_code['id'],
            'parsed_codes' => self::parseOrderItemsProductUIDs($codes),
            'messages' => [
                'unique' => $validation_error_message
            ],
            'app_url' => wa()->getAppUrl('shop')
        ];

        $options_json = json_encode($options);

        return <<<EOF
<script>$("#js-order-marking-dialog").data('shop_chestnyznak_plugin_options', $options_json);</script>
<script src="{$this->getPluginStaticUrl()}js/chestnyznak.js?v={$this->getVersion()}"></script>
<link href="{$this->getPluginStaticUrl()}css/chestnyznak.css?v={$this->getVersion()}" rel="stylesheet" type="text/css" />
EOF;
    }

    public function installSettingsPlugin()
    {
        $model = new shopChestnyznakPluginModel();
        $product_code = $model->getProductCode();
        if (empty($product_code['id'])) {
            $images = $this->getPluginImages();
            $model->setupProductCode($images);
        } elseif ($product_code['name'] != shopChestnyznakPluginModel::PRODUCT_CODE_NAME) {
            $model->setName($product_code['id']);
        }
    }

    protected function getPluginImages($image_name = 'logo.png')
    {
        $images['icon'] = $this->getImagePath();
        $plugin_id = $this->getId();
        $plugin_path = $this->getPluginStaticUrl();
        if (!empty($plugin_path)) {
            if (file_exists($this->getPath() . '/img/' . $plugin_id . '-' . $image_name)) {
                $images['logo'] = $plugin_path . 'img/' . $plugin_id . '-' . $image_name;
                if (strpos($images['logo'], '/') === 0) {
                    $images['logo'] = substr_replace($images['logo'], '', 0, 1);
                }
            } else {
                $images['logo'] = null;
            }
        }

        return $images;
    }

    protected function getOrderItemsCodes($order_id, $product_code_id)
    {
        $data = [];
        $oicm = new shopOrderItemCodesModel();
        $result = $oicm->getByField(['order_id' => $order_id, 'code_id' => $product_code_id], true);
        foreach ($result as $item) {
            $data[$item['order_item_id']][$item['sort']] = $item['value'];
        }
        return $data;
    }

    /**
     * @param array $data
     *      string $data[<order_item_id>][<sort>]
     * @return array $result
     *      array $result['validation']
     *      string $result['validation'][<error_code>]  - error message
     *
     *      string $result['converted'] - from cyrillic to latin symbols (if scanner in mode of keyboard emulation input could be cyrillic)
     *
     *      bool $result['parsed'] - ok parsed or not
     *
     *      array $result['warnings']
     *      string $result['warnings'][<code>] - warning message
     * @throws waException
     */
    public static function parseOrderItemsProductUIDs($data)
    {
        if (!$data) {
            return [];
        }

        $order_item_ids = array_keys($data);
        $order_items_gtin_values = self::getOrderItemsGTINValues($order_item_ids);

        $result = [];
        foreach ($data as $order_item_id => $codes) {
            foreach ($codes as $sort => $uid) {
                $parse_result = shopChestnyznakPluginCodeParser::parse($uid);

                // if uid has cyrillic symbols convert it and try parse again, here is result of converting
                $converted = '';
                if (!$parse_result['status'] && $parse_result['details']['error_code'] === 'contains_cyrillic') {
                    $converted = shopChestnyznakPluginCodeParser::convert($uid);
                    $parse_result = shopChestnyznakPluginCodeParser::parse($converted);
                }

                $res = [
                    'validation' => [],
                    'warnings' => [],
                    'converted' => $converted,
                    'parsed' => false       // here expected either FALSE or associative array-result of parsing
                ];

                if ($parse_result['status']) {
                    $res['parsed'] = $parse_result['details'];
                }

                // validate GTIN's matching
                if ($parse_result['status'] && !empty($order_items_gtin_values[$order_item_id])) {
                    if ($parse_result['details']['gtin'] != $order_items_gtin_values[$order_item_id]) {
                        $res['validation']['not_match'] = 'GTIN в коде маркировки «Честный ЗНАК» не совпадает со значением GTIN товара.';
                    }
                }

                if ($parse_result['status'] && $parse_result['details']['is_separator_missed']) {
                    $res['warnings']['separator_missed'] = 'Штрихкод GS1 DataMatrix сформирован неправильно: в нем отсутствуют разделители (group separator). Из-за этого серийный номер может быть неправильно определен и передан в онлайн-кассы. Убедитесь, что при вводе кода не были потеряны скрытые символы.';
                }

                $result[$order_item_id][$sort] = $res;
            }
        }

        return $result;
    }

    protected static function getOrderItemsGTINValues($order_item_ids) {
        if (!$order_item_ids) {
            return [];
        }

        $fm = new shopFeatureModel();
        $feature_id = $fm->select('id')->where('code = ?', 'gtin')->fetchAssoc();
        if (!$feature_id) {
            return [];
        }

        $sql = "SELECT oi.id, fv.value FROM `shop_product_features` pf
                    JOIN shop_order_items oi ON pf.product_id = oi.product_id AND pf.sku_id = oi.sku_id
                    JOIN shop_feature_values_varchar fv ON pf.feature_value_id = fv.id
                WHERE pf.feature_id = :feature_id AND oi.id IN(:order_item_ids) AND oi.type = 'product'";

        return $fm->query($sql, [
            'order_item_ids' => $order_item_ids,
            'feature_id' => $feature_id
        ])->fetchAll('id', true);
    }

    /**
     * Returns image path
     * @return string
     */
    public function getImagePath()
    {
        return $this->info['img'];
    }

    /**
     * Returns plugin path
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }
}
