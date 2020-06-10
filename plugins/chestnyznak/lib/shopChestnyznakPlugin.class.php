<?php

class shopChestnyznakPlugin extends shopPlugin
{
    public function orderActionForm(&$params)
    {
        $model = new shopChestnyznakPluginModel();
        $code_id = $model->getProductCodeId();

        // In case user deleted the code, create it again
        if (empty($code_id)) {
            $images = $this->getPluginImages('logo.png');
            $model->setupProductCode($images);
            $code_id = $model->getProductCodeId();
            if (empty($code_id)) {
                return; // should never happen
            }
        }

        // this takes localization from shop.po
        $validation_error_message = htmlspecialchars(_w('Values must be unique'));

        $codes = $this->getOrderItemsCodes($params['order_id'], $code_id);

        $options = [
            'product_code_id' => $code_id,
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
        $hasCode = $model->getProductCodeId();
        if (empty($hasCode)) {
            $images = $this->getPluginImages('logo.png');
            $model->setupProductCode($images);
        }
    }

    protected function getPluginImages($image_name)
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
                $parsed = shopChestnyznakPlugin::parseProductUID($uid);

                $res = [
                    'parsed' => $parsed,
                    'validation' => []
                ];

                // validate GTIN's matching
                if ($parsed && !empty($order_items_gtin_values[$order_item_id])) {
                    if ($parsed['gtin'] != $order_items_gtin_values[$order_item_id]) {
                        $res['validation']['not_match'] = 'GTIN в коде маркировки «Честный ЗНАК» не совпадает со значением GTIN товара.';
                    }
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
     * Парсим уникальный идентификатор товара - УИД
     *
     * Из документации, УИД выглядит так
     *  01+XXXXXXXXXXXXXX+21+XXXXXXXXXXXXX+240+XXXX
     *  Где
     *      - Первая группа (идет после идентификатора применения 01) это GTIN (14 символов)
     *      - Вторая группа (идет после идентификатора применения 21) это серийный номер товара (13 символов)
     *      - дальше идет символ \x1d (символ с ASCII кодом 29, так назыаемый Group Separator)
     *          В доке говорится, что этот символ необходимо исползовать :)
     *      - Третья группа (идет после идентификатора применения 240) это ТН ВЭД ЕАЭС (4 символа)
     *
     * @param string $uid
     *
     * @return array|false $parsed - если УИД не соответствует вышеприведенному формату, то FALSE (при этом \x1d после второй группы может быть опущен)
     *      string $parsed['gtin'] - GTIN (14 символов)
     *      string $parsed['serial'] - серийный номер товара (13 символов)
     *      string $parsed['tnved'] - код ТН ВЭД ЕАЭС (4 символа)
     *
     */
    public static function parseProductUID($uid)
    {
        if (!is_string($uid)) {
            return false;
        }

        // group separator\x1d (29 ascii code) must not be at all or be on place 31
        $pos = strpos($uid, "\x1d");
        if ($pos !== false && $pos != 31) {
            return false;
        }

        // normalize uid, insert group separator
        if ($pos === false) {
            $uid = substr($uid, 0, 31) . "\x1d" . substr($uid, 31);
        }

        // len of uid with group separator must be 39
        $len = strlen($uid);
        if ($len != 39) {
            return false;
        }

        // aid is "application identifier" - идентификатор применения

        $aid = substr($uid, 0, 2);
        if ($aid != "01") {
            return false;
        }

        $gtin = substr($uid, 2, 14);

        $aid = substr($uid, 16, 2);
        if ($aid != "21") {
            return false;
        }

        $serial = substr($uid, 18, 13);

        $aid = substr($uid, 32, 3);
        if ($aid != "240") {
            return false;
        }

        $tnved = substr($uid, 35);

        if (!self::isAllDigits($gtin)) {
            return false;
        }

        if (!self::isAllDigits($tnved)) {
            return false;
        }

        return [
            'gtin' => $gtin,
            'serial' => $serial,
            'tnved' => $tnved
        ];
    }

    private static function isAllDigits($str)
    {
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $val = ord($str[$i]) - ord('0');
            if ($val < 0 || $val > 9) {
                return false;
            }
        }
        return true;
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
