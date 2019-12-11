<?php

class shopPromoRulesModel extends waModel
{
    protected $table = 'shop_promo_rules';

    public function getAvailableShopTypes()
    {
        $shop_types = [
            'banner'       => [
                'type'      => 'banner',
                'name'      => _w('Banner'),
                'css_class' => 'image',
                'max_count' => 1,
            ],
            'custom_price' => [
                'type'      => 'custom_price',
                'name'      => _w('Products & prices'),
                'css_class' => 'dollar',
                'max_count' => 1,
            ],
            'utm'          => [
                'type'      => 'utm',
                'name'      => _w('UTM tags'),
                'css_class' => 'tags',
                'max_count' => 1,
            ],
            'coupon'       => [
                'type'      => 'coupon',
                'name'      => _w('Coupons'),
                'css_class' => 'ss coupon',
                'max_count' => 1,
            ],
        ];

        return $shop_types;
    }

    public function getAvailableTypes()
    {
        $types = $this->getAvailableShopTypes();

        /**
         * Custom promo rule types
         * @event promo_rule_types
         */
        $custom_types = wa('shop')->event('promo_rule_types');
        if (!empty($custom_types)) {
            foreach ($custom_types as $plugin_id => $plugin_custom_types) {
                foreach ($plugin_custom_types as $plugin_custom_type) {
                    if (empty($plugin_custom_type['type']) || empty($plugin_custom_type['name']) || empty($plugin_custom_type['css_class'])) {
                        continue 2;
                    }
                    if (isset($types[$plugin_custom_type['type']])) {
                        continue 2;
                    }

                    $types[$plugin_custom_type['type']] = $plugin_custom_type;
                }
            }
        }

        return $types;
    }

    public function getTypesByPromos($ids)
    {
        if (!$ids) {
            return [];
        }
        $result = array_fill_keys($ids, []);
        $sql = "SELECT DISTINCT promo_id AS id, rule_type AS type FROM {$this->table} WHERE promo_id IN (?)";
        foreach($this->query($sql, [$ids]) as $row) {
            $result[$row['id']][$row['type']] = $row['type'];
        }
        return $result;
    }

    public function insert($data, $type = 0)
    {
        if (!empty($data['rule_params']) && is_array($data['rule_params'])) {
            $data['rule_params'] = waUtils::jsonEncode($data['rule_params']);
        }
        return parent::insert($data, $type);
    }

    public function getByActivePromos($params = array())
    {
        $join = $cond = $vars = [];

        $join[] = "shop_promo AS p ON rl.promo_id = p.id";

        // rule type
        if (!empty($params['rule_type'])) {
            $rule_types = (array)$params['rule_type'];
            $vars['rule_types'] = $rule_types;
            $cond[] = "rl.rule_type IN (:rule_types)";
        }

        // promo storefront
        if (!empty($params['storefront']) && is_scalar($params['storefront'])) {
            $storefront = $params['storefront'];

            $storefronts = [
                shopPromoRoutesModel::FLAG_ALL,
                rtrim($storefront, '/') . '/',
                rtrim($storefront, '/'),
            ];

            $vars['storefronts'] = $storefronts;
            $join[] = 'shop_promo_routes AS rt ON rl.promo_id = rt.promo_id';
            $cond[] = "rt.storefront IN (:storefronts)";
        }

        $vars['datetime'] = date('Y-m-d H:i:s');
        $cond[] = "(p.start_datetime IS NULL OR p.start_datetime <= :datetime)";
        $cond[] = "(p.finish_datetime IS NULL OR p.finish_datetime >= :datetime)";

        if (!empty($params['ignore_paused'])) {
            $cond[] = "(p.enabled != 0)";
        }

        if ($join) {
            $join = 'JOIN '.join(" JOIN ", $join);
        } else {
            $join = '';
        }

        if ($cond) {
            $cond = 'WHERE '.join(' AND ', $cond);
        } else {
            $cond = '';
        }

        $sql = "SELECT rl.*
                FROM {$this->table} AS rl
                {$join}
                {$cond}
                ORDER BY rl.id";

        $rules = $this->query($sql, $vars)->fetchAll('id');
        foreach ($rules as &$rule) {
            if ($this->stringIsJson($rule['rule_params'])) {
                $rule['rule_params'] = waUtils::jsonDecode($rule['rule_params'], true);
            }
        }
        unset($rule);
        return $rules;
    }

    public function getByField($field, $value = null, $all = false, $limit = false)
    {
        $rules = parent::getByField($field, $value, $all, $limit);
        if (empty($rules)) {
            return $rules;
        }

        if (is_array($field)) {
            $all = $value;
        }

        if ($all) {
            foreach ($rules as &$rule) {
                if ($this->stringIsJson($rule['rule_params'])) {
                    $rule['rule_params'] = waUtils::jsonDecode($rule['rule_params'], true);
                }
                $this->prepareRule($rule);
            }
            unset($rule);
        } else {
            if ($this->stringIsJson($rules['rule_params'])) {
                $rules['rule_params'] = waUtils::jsonDecode($rules['rule_params'], true);
            }
            $this->prepareRule($rules);
        }

        return $rules;
    }

    public function updateByField($field, $value, $data = null, $options = null, $return_object = false)
    {
        if (is_array($field)) {
            if (!empty($value['rule_params']) && is_array($value['rule_params'])) {
                $value['rule_params'] = waUtils::jsonEncode($value['rule_params']);
            }
        } elseif (!empty($data['rule_params']) && is_array($data['rule_params'])) {
            $data['rule_params'] = waUtils::jsonEncode($data['rule_params']);
        }

        return parent::updateByField($field, $value, $data, $options, $return_object);
    }

    protected function stringIsJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    protected function prepareRule(&$rule)
    {
        $prepare_method = function ($rule_type) {
            $part_of_name = '';
            foreach (explode('_', $rule_type) as $part) {
                $part_of_name .= ucfirst($part);
            }

            /**
             * @uses shopPromoRulesModel::prepareBannerRule()
             */
            $method_name = "prepare{$part_of_name}Rule";
            return $method_name;
        };

        $method_name = $prepare_method($rule['rule_type']);

        if (method_exists($this, $method_name)) {
            $this->$method_name($rule);
        }
    }

    protected function prepareBannerRule(&$rule)
    {
        if (empty($rule['rule_params']['banners'])) {
            return;
        }
        $promo_id = $rule['promo_id'];
        foreach ($rule['rule_params']['banners'] as &$banner) {
            if (empty($banner['image_filename'])) {
                continue;
            }
            $banner_url = shopPromoBannerHelper::getPromoBannerUrl($promo_id, $banner['image_filename']);
            $banner['image'] = $banner_url;
        }
        unset($banner);
    }
}