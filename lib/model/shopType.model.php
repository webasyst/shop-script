<?php

class shopTypeModel extends shopSortableModel
{
    protected $table = 'shop_type';

    public function getByName($name)
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `name` LIKE s:0";
        return $this->query($sql, $name)->fetch();
    }

    /**
     * Take into account rights
     *
     * @param boolean $get_all In case user has rights to all types return this types or just return true
     * @return boolean|array
     */
    public function getTypes($get_all = true)
    {
        if (wa('shop')->getUser()->getRights('shop', 'type.all')) {
            if ($get_all) {
                return $this->getAll('id');
            } else {
                return true;
            }
        } else {
            $types = array();
            $all_types = $this->getAll('id');
            foreach (self::extractAllowed(array_keys($all_types)) as $id) {
                if (isset($all_types[$id])) {
                    $types[$id] = $all_types[$id];
                }
            }
            return $types;
        }
    }

    public static function extractAllowed($type_ids)
    {
        if (!$type_ids) {
            return array();
        }
        $contact = wa('shop')->getUser();
        $allowed_ids = array();
        foreach ($type_ids as $id) {
            if ($contact->getRights('shop', sprintf("type.%d", $id))) {
                $allowed_ids[] = $id;
            }
        }
        return $allowed_ids;
    }

    public function getNames()
    {
        return $this->select('id,name')->order('sort')->fetchAll('id', true);
    }

    /**
     * @param array $data Array format: id=>increment_value (e.g. '+1', '+2', '-1', '-5')
     */
    public function incCounters($data = array())
    {
        if (!$data) {
            return;
        }
        foreach ($data as $id => $inc) {
            if ($id = intval($id)) {
                $inc = $this->escape(trim($inc));
                if ($inc{0} != '+' && $inc{0} != '-') {
                    $inc = '+'.$inc;
                }
                $this->exec("UPDATE `{$this->table}` SET `count`=`count`{$inc} WHERE `id` = {$id}");
            }
        }
    }

    public function deleteByField($field, $value = null)
    {
        if (is_array($field)) {
            $items = $this->getByField($field, $this->id);
            $ids = array_keys($items);
        } else {
            if ($field == $this->id) {
                $ids = $value;
            } else {
                $items = $this->getByField($field, $value, $this->id);
                $ids = array_keys($items);
            }
        }
        $res = false;
        if ($ids) {
            if ($res = parent::deleteByField($this->id, $ids)) {
                $product_model = new shopProductModel();
                $data = array('type_id' => null);
                $product_model->updateByField('type_id', $ids, $data);

                $type_features_model = new shopTypeFeaturesModel();
                $type_features_model->deleteByField('type_id', $ids);
            }
        }
        return $res;
    }

    public function insert($data, $type = 0)
    {
        $id = parent::insert($data, $type);
        if ($id) {
            $model = new shopTypeFeaturesModel();
            $model->addType($id);
        }
        return $id;
    }

    /**
     * @param int|array|null $type_id
     * @return boolean
     */
    public function recount($type_id = null)
    {
        $cond = "
            GROUP BY t.id
            HAVING t.count != cnt
        ";
        if ($type_id !== null) {
            $type_ids = array();
            foreach ((array)$type_id as $id) {
                $type_ids[] = (int)$id;
            }
            if (!$type_ids) {
                return;
            }
            $cond = "
                WHERE t.id IN (".implode(',', $type_ids).")
                GROUP BY t.id
            ";
        }
        $sql = "
            UPDATE `{$this->table}` t JOIN (
                SELECT t.id, t.count, count(p.type_id) cnt
                FROM `{$this->table}` t
                LEFT JOIN `shop_product` p ON p.type_id = t.id
                $cond
            ) r ON t.id = r.id
            SET t.count = r.cnt";

        return $this->exec($sql);
    }


    public function getSales($start_date = null, $end_date = null, $options=array())
    {
        $storefront_join = '';
        $storefront_where = '';
        if (!empty($options['storefront'])) {
            $storefront_join = "JOIN shop_order_params AS op2
                                    ON op2.order_id=o.id
                                        AND op2.name='storefront'";
            $storefront_where = "AND op2.value='".$this->escape($options['storefront'])."'";
        }

        // !!! With 15k orders this query takes ~2 seconds
        $paid_date_sql = shopOrderModel::getDateSql('o.paid_date', $start_date, $end_date);
        $sql = "SELECT
                    t.*,
                    SUM(ps.price*pcur.rate*oi.quantity) AS sales
                FROM shop_order AS o
                    JOIN shop_order_items AS oi
                        ON oi.order_id=o.id
                    JOIN shop_product AS p
                        ON oi.product_id=p.id
                    JOIN shop_product_skus AS ps
                        ON oi.sku_id=ps.id
                    JOIN shop_currency AS pcur
                        ON pcur.code=p.currency
                    JOIN shop_type AS t
                        ON t.id=p.type_id
                    {$storefront_join}
                WHERE $paid_date_sql
                    AND oi.type = 'product'
                    {$storefront_where}
                GROUP BY t.id";
        return $this->query($sql)->fetchAll('id');
    }

    private static $translate = array();

    /**
     * @return array
     */
    public static function getTemplates()
    {
        static $types = null;
        if ($types === null) {
            $types = array();
            $path = wa('shop')->getConfig()->getConfigPath('data/welcome/', false);
            if (file_exists($path)) {
                $files = waFiles::listdir($path, false);
                foreach ($files as $file) {
                    if (preg_match('/^type_([a-z]\w+)\.php$/', $file, $matches)) {
                        $types[$matches[1]] = include($path.$file);
                    }
                }
            }
            $locale_path = $path.'locale/'.wa()->getUser()->getLocale().'.php';
            if (file_exists($locale_path)) {
                self::$translate = include($locale_path);
                if (!is_array(self::$translate)) {
                    self::$translate = array();
                }
            }

            if (!empty($types)) {
                foreach ($types as $id => &$type) {
                    $name = ifempty($type['name'], $id);
                    $type['name'] = ifempty(self::$translate[$name], $name);
                    $type += array(
                        'icon'        => '',
                        'description' => '',
                    );
                }
            }
        }
        return $types;
    }

    /**
     * @param $template_id
     * @param bool $extend
     * @return array|null
     * @throws waException
     */
    public function insertTemplate($template_id, $extend = false)
    {
        $types = self::getTemplates();
        $feature_model = new shopFeatureModel();
        $type_features_model = new shopTypeFeaturesModel();
        $type = null;
        if (!empty($types[$template_id])) {
            $type = $types[$template_id];
            $type['sort'] = $this->select('MAX(sort)+1 as max_sort')->fetchField('max_sort');
            $type['id'] = $this->insert($type);
            if ($type['id'] && !empty($type['features'])) {
                foreach ($type['features'] as $code => &$feature) {
                    $feature += array(
                        'type'       => 'varchar',
                        'selectable' => false,
                        'multiple'   => false,
                    );
                    $feature['types'] = array($type['id']);
                    $feature['name'] = ifempty(self::$translate[$feature['name']], $feature['name']);
                    $feature['code'] = $code;
                    $id = null;

                    if ($data = $feature_model->getByField('code', $code)) {
                        if (($feature['type'] == $data['type']) && ($feature['selectable'] == $data['selectable']) && ($feature['multiple'] == $data['multiple'])) {
                            $id = $data['id'];
                        }
                    }
                    $feature['id'] = $feature_model->save($feature, $id);
                    if ($feature['id']) {
                        if (!empty($feature['selectable']) && !empty($feature['values'])) {
                            foreach ($feature['values'] as & $value) {
                                if (is_string($value)) {
                                    $value = ifempty(self::$translate[$value], $value);
                                } elseif (isset($value['value'])) {
                                    $value['value'] = ifempty(self::$translate[$value['value']], $value['value']);
                                }
                            }
                            unset($value);
                            $feature['values'] = $feature_model->setValues($feature, $feature['values'], false, true);
                        }

                        $feature['types'] = $type_features_model->updateByFeature($feature['id'], $feature['types'], false);
                        if ($id && $extend) {
                            //TODO get exists feature values
                            //$feature_model->getFeatureValues($feature);
                            $feature['types'] = array_keys($type_features_model->getByField('feature_id', $feature['id'], 'type_id'));
                        }
                    }
                    unset($feature);
                }
                if ($extend) {
                    shopFeatureModel::appendTypeNames($type['features']);
                }
            }
        }
        return $type;
    }
}
