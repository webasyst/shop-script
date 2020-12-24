<?php

class shopImportexportHelper
{
    /** @var string */
    const PROMO_TYPE_PROMO_CODE = 'code';
    /** @var string */
    const PROMO_TYPE_GIFT = 'gift';
    /** @var string */
    const PROMO_TYPE_FLASH_DISCOUNT = 'discount';
    /** @var string */
    const PROMO_TYPE_N_PLUS_M = 'npm';
    /** @var string */

    /** @var shopImportexportModel */
    private $model;
    /** @var string */
    private $plugin;

    /** @var array available profiles */
    private $collection;

    private $name;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        $this->model = new shopImportexportModel();
    }

    /**
     * @param $id
     * @return array
     */
    public function getConfig($id = null)
    {
        $id = $this->getId($id);
        $config = $this->model->getByField(array(
            'id'     => $id,
            'plugin' => $this->plugin,
        ));

        if (null === $config || !is_array($config)) {
            /** случай когда профиля с $id не существует или нет ни одного созданного (дефолтного) профиля */
            if (!$this->collection) {
                $this->getList();
            }
            $id_profiles = array_keys($this->collection);
            $id_profile  = (empty($id_profiles) ? $this->addConfig() : array_pop($id_profiles));
            $config = $this->model->getByField(array(
                'id'     => $id_profile,
                'plugin' => $this->plugin,
            ));
        }
        $config['config'] = (isset($config['config']) ? json_decode($config['config'], true) : []);

        return $config;
    }

    /**
     * @param array $config
     * @param int   $id
     * @return bool|int|null|resource|string
     */
    public function setConfig($config = array(), $id = null)
    {
        $id = $this->getId($id);
        if ($id <= 0) {
            $name = self::getDefaultName();
            $description = '';
            if (($raw = waRequest::request('profile')) && (is_array($raw))) {
                if (!empty($raw['name'])) {
                    $name = $raw['name'];
                }
                if (!empty($raw['description'])) {
                    $description = $raw['description'];
                }

            }
            $id = $this->addConfig($name, $description);
        }
        $fields = array(
            'id'     => $id,
            'plugin' => $this->plugin,
        );
        $data = array(
            'config' => json_encode($config),
        );
        if (!empty($this->name)) {
            $data['name'] = $this->name;
        }
        $this->model->updateByField($fields, $data);
        return $id;
    }

    public function addConfig($name = '', $description = '', $config = array())
    {
        if (empty($name)) {
            $name = self::getDefaultName();
        }
        $data = array(
            'plugin'      => $this->plugin,
            'name'        => $name,
            'description' => $description,
            'config'      => json_encode($config),
        );

        return $this->model->insert($data);
    }

    public function deleteConfig($id = null)
    {
        $fields = array(
            'plugin' => $this->plugin,
        );
        if ($id !== null) {
            $fields['id'] = $id;
        }
        return $this->model->deleteByField($fields);
    }

    /**
     * @return array of available profiles
     */
    public function getList()
    {
        if (!isset($this->collection)) {
            $profiles = $this->model->getProfiles($this->plugin);
            $this->collection = $profiles[$this->plugin];
        }
        return $this->collection;
    }

    /**
     * @param string $hash
     * @return string[]
     */
    public static function getCollectionHash($hash = null)
    {
        if ($hash === null) {
            $hash = waRequest::post('hash', '', waRequest::TYPE_STRING_TRIM);
        }
        $raw = explode('/', $hash);

        $info = array(
            'type' => array_shift($raw),
            'name' => self::getDefaultName(),

        );
        if (!$info['name']) {
            $info['name'] = date('c');
        }
        $tail = implode('/', $raw);
        switch ($info['type']) {
            case 'id':
                $hash = 'id/'.waRequest::post('product_ids', $tail, waRequest::TYPE_STRING_TRIM);
                break;
            case 'set':
                $set_id = waRequest::post('set_id', $tail, waRequest::TYPE_STRING_TRIM);
                $hash = 'set/'.$set_id;
                $model = new shopSetModel();
                if ($set = $model->getById($set_id)) {
                    $info['name'] .= ' - '.$set['name'];
                }
                break;
            case 'type':
                $type_id = waRequest::post('type_id', $tail, waRequest::TYPE_INT);
                $hash = 'type/'.$type_id;
                $model = new shopTypeModel();
                if ($type = $model->getById($type_id)) {
                    $info['name'] .= ' - '.$type['name'];
                }
                break;
            case 'category':
                $category_id = waRequest::post('category_ids', $tail, waRequest::TYPE_INT);
                $model = new shopCategoryModel();
                if ($category = $model->getById($category_id)) {
                    $info['name'] .= ' - '.$category['name'];
                }
                $hash = 'category/'.$category_id;
                break;
            case 'tag':
            case 'search':
                $collection = new shopProductsCollection($info['type'].'/'.urldecode($tail));
                $products = $collection->getProducts('id,name,url', 0, 10000);
                $info['type'] = 'id';
                $hash = 'id/'.implode(',', array_keys($products));
                break;
            default:
                $hash = '*';
                break;
        }
        $info['hash'] = $hash;
        return $info;
    }

    public static function parseHash($hash, $params = array())
    {
        $info = array(
            'type'         => '',
            'data'         => array(),
            'set_id'       => '',
            'type_id'      => '',
            'product_ids'  => null,
            'category_ids' => null,
        );
        if (strpos($hash, '/')) {
            list($info['type'], $hash) = explode('/', $hash, 2);
        }
        switch ($info['type']) {
            case 'id':
                $ids = array_unique(array_filter(array_map('intval', explode(',', $hash))));
                sort($ids);
                $info['count'] = count($ids);
                $info['product_ids'] = implode(',', $ids);
                break;
            case 'set':
                $info['set_id'] = trim($hash);
                break;
            case 'type':
                $info['type_id'] = intval($hash);
                break;
            case 'category':
                $category_ids = array_unique(array_map('intval', explode(',', $hash)));
                sort($category_ids);
                $info['count'] = count($category_ids);
                $info['category_ids'] = implode(',', $category_ids);
                break;
            default:
                $info['hash'] = '';
                break;
        }
        if (!empty($params['categories']) || true) {
            $model = new shopCategoryModel();
            $categories = $model->getTree(null, 0, true);
            foreach ($categories as $id => $category) {
                if ($category['type'] == shopCategoryModel::TYPE_DYNAMIC) {
                    if (empty($category_ids) || !in_array($id, $category_ids, true)) {
                        unset($categories[$id]);
                    }
                }
            }

            $map = array();
            foreach ($categories as &$category) {
                $category['icon'] = ($category['type'] == shopCategoryModel::TYPE_STATIC) ? 'folder' : 'funnel';
                $map[$category['id']] = &$category;
            }
            unset($category);

            $category_id = explode(',', ifset($info['category_ids'], ''));
            foreach ($category_id as $id) {
                if (isset($map[$id])) {
                    $map[$id]['selected'] = 'selected';
                }
            }
            $category_id = array_diff($category_id, array_keys($categories));
            foreach ($model->getById($category_id) as $category) {
                $category['name'] = htmlspecialchars($category['name'], ENT_NOQUOTES, 'utf-8');
                $map[$category['id']] = &$category;
                if (isset($map[$category['parent_id']])) {
                    if (!isset($map['childs'])) {
                        $map['childs'] = array();
                    }

                    $map[$category['parent_id']]['childs'][$category['id']] = &$category;
                } else {
                    $path = $model->getPath($category['id']);
                    while ($path_category = array_shift($path)) {
                        $path_category['childs'][$category['id']] = &$category;
                        unset($category);
                        $category = $path_category;
                        $map[$category['id']] = &$category;

                        if (isset($map[$category['parent_id']])) {
                            $map[$category['parent_id']]['childs'][$category['id']] = &$category;
                            unset($category);
                            break;
                        }

                    }
                }
            }
            foreach ($category_id as $id) {
                if (isset($map[$id])) {
                    $map[$id]['selected'] = 'selected';
                }
            }

            $info['data']['categories'] = $categories;
        }
        return $info;
    }

    public function save()
    {
        $raw_profile = waRequest::post('profile');
        if (is_array($raw_profile)) {
            $profile_id = isset($raw_profile['id']) ? intval(intval($raw_profile['id'])) : 0;
        } else {
            $profile_id = intval($raw_profile);
            $raw_profile = array();
        }
        if ($profile_id) {
            $profiles = new shopImportexportHelper($this->plugin);
            if ($profile_id < 0) {
                $profile_id = $profiles->addConfig(ifset($raw_profile['name'], date('c')), ifset($raw_profile['description'], ''));
            }
            $profiles->setConfig($raw_profile, $profile_id);
        }
    }

    /**
     * Получения данных о промо-акциях магазина.
     * Метод предназначен для экспорта информации о
     * промо-акциях в сторонние системы плагинами интеграции
     *
     * @since 8.3.0
     * @param array $options
     * @return array
     * @throws waException
     */
    public function getPromoRules($options = array())
    {
        $list = array();

        $default_promo = array(
            'type'              => self::PROMO_TYPE_PROMO_CODE, # promo type
            'name'              => '',   # Public name
            'description'       => '',   # Public description
            'url'               => '',   # Public URL of promo's description
            'start_datetime'    => null, # Start datetime (unix timestamp)
            'end_datetime'      => null, # End datetime (unix timestamp)
            'settings'          => '',   # Setup link
            'source'            => null, # Internal name
            'hint'              => null, # Internal description
            'hash'              => '*',  # shop products collection hash
            'promo_code'        => '',   # Promo code (for PROMO_TYPE_PROMO_CODE)
            'discount_unit'     => null, # % symbol o currency ISO3 code (for PROMO_TYPE_PROMO_CODE, PROMO_TYPE_FLASH_DISCOUNT)
            'discount_value'    => null, # Discount value (for PROMO_TYPE_PROMO_CODE, PROMO_TYPE_FLASH_DISCOUNT)
            'required_quantity' => 1,    # Minimal required items quantity
            'free_quantity'     => null, # Free items quantity (for PROMO_TYPE_N_PLUS_M)
            'gifts_hash'        => null, # shop products collection hash (for PROMO_TYPE_GIFT)
        );

        # coupons
        $coupon_model = new shopCouponModel();
        $coupons = $coupon_model->getActiveCoupons();
        foreach ($coupons as $id => $coupon) {
            if ($coupon['type'] != '$FS') {
                $promo = array(
                    'type'           => self::PROMO_TYPE_PROMO_CODE,
                    'name'           => _w('Coupon discount'),
                    'description'    => sprintf('%s: %s', _w('Coupon discount'), shopCouponModel::formatValue($coupon)),
                    'settings'       => sprintf('./marketing/coupons/%d', $id),
                    'source'         => _w('Discount coupons'),
                    'hint'           => $coupon['comment'],
                    'promo_code'     => $coupon['code'],
                    'hash'           => ifempty($coupon, 'products_hash', '*'),
                    'discount_unit'  => $coupon['type'],
                    'discount_value' => $coupon['value'],
                    'end_datetime'   => strtotime($coupon['expire_datetime']),
                    'start_datetime' => strtotime($coupon['create_datetime']),
                );
                $promo_id = sprintf('shop.coupons.%s', $id);
                $list[$promo_id] = $promo + $default_promo;
            }
        }

        # promos
        $promo_model = new shopPromoModel();
        $params = array(
            'status'        => 'active',
            'ignore_paused' => true,
            'rule_type'     => 'custom_price',
            'with_rules'    => true,
        );
        $promos = $promo_model->getList($params);

        foreach ($promos as $id => $shop_promo) {
            $products = [];
            if (!empty($shop_promo['rules'])) {
                foreach ($shop_promo['rules'] as $rule) {
                    if ($rule['rule_type'] != 'custom_price') {
                        continue;
                    }
                    $products += array_keys($rule['rule_params']);
                }
            }

            if (!empty($products)) {
                $products = array_unique(array_map('intval', $products));
                asort($products);
                $promo = array(
                    'type'           => self::PROMO_TYPE_FLASH_DISCOUNT,
                    'name'           => _w('Products & prices'),
                    'description'    => sprintf('%s: %s', _w('Products & prices'), $shop_promo['name']),
                    'settings'       => sprintf('./marketing/promo/%d/', $id),
                    'source'         => _w('Products & prices'),
                    'hint'           => '',
                    'hash'           => 'id/'.implode(',', $products),
                    'end_datetime'   => strtotime($shop_promo['finish_datetime']),
                    'start_datetime' => strtotime($shop_promo['start_datetime']),
                );

                $promo_id = sprintf('shop.promos.%s', $id);
                $list[$promo_id] = $promo + $default_promo;
            }
        }

        # plugins
        $params = array(
            'plugin' => $this->plugin,
            'list'   => !empty($options['list']),
        );
        /**
         * @since 8.3.0
         * @event promo_rules Get all available promo rules
         * @param mixed[] $params
         * @param string  $params ['plugin'] Plugin id
         * @param bool    $params ['list']
         */
        $data = wa('shop')->event('promo_rules', $params);


        foreach ($data as $plugin => $plugin_data) {
            $plugin_id = preg_replace('@\-plugin$@', '', $plugin);
            foreach ($plugin_data as $id => $promo) {
                $promo_id = sprintf('plugins.%s.%s', $plugin_id, $id);
                if (empty($promo['settings'])) {
                    $promo['settings'] = sprintf('?action=plugins#/%s/', $plugin_id);
                }
                if (empty($promo['source'])) {
                    try {
                        $plugin_instance = wa('shop')->getPlugin($plugin_id);
                        $promo['source'] = $plugin_instance->getName();
                    } catch (waException $ex) {
                        $promo['source'] = $plugin_id;
                    }
                }
                $list[$promo_id] = $promo + $default_promo;
            }
        }

        return $list;
    }

    private function getId($id = null)
    {
        if (!$id) {
            if ($raw = waRequest::request('profile')) {
                if (is_array($raw)) {
                    $id = intval(ifset($raw['id']));
                    if (!empty($raw['name'])) {
                        $this->name = trim($raw['name']);
                    }
                } else {
                    $id = intval($raw);
                }
            } elseif (!empty($this->collection)) {
                reset($this->collection);
                $id = key($this->collection);
            }
        }
        return $id;
    }

    protected static function getDefaultName()
    {
        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();
        $name = $config->getGeneralSettings('name');
        if (!$name) {
            $name = date('c');
        }
        return $name;
    }
}
