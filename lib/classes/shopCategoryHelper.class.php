<?php

class shopCategoryHelper
{
    protected $feature_model;

    public function __construct()
    {
        $this->feature_model = new shopFeatureModel();
    }

    /**
     * @param $options
     * @return array
     * @throws waException
     */
    public function getFilters($options, $limit = shopFeatureModel::SEARCH_STEP)
    {
        $filters = $this->feature_model->getFilterFeatures($options, $limit);
        shopFeatureModel::appendTypeNames($filters);

        return $filters;
    }

    /**
     * @param $options
     * @return bool|int
     * @throws waException
     */
    public function getCount($options)
    {
        $count = $this->feature_model->getFeaturesCount($options);
        return $count;
    }

    /**
     * @param $features
     * @param null $all
     * @return array[]
     * @throws waException
     */
    public function getFeaturesValues($features, $all = null)
    {
        if ($features) {
            $features = $this->feature_model->getValues($features, $all);
        }

        return $features;
    }

    /**
     * @param $id
     * @return array
     * @throws waException
     */
    public function getTypesId($id)
    {
        static $count_types = null;
        if ($count_types === null) {
            $count_types = (new shopTypeModel())->countAll();
        }
        if ($count_types <= 0) {
            return [];
        }

        $product_collection = new shopProductsCollection("category/{$id}");
        $product_collection->groupBy('type_id');
        $types = $product_collection->getProducts('type_id', 0, $count_types);

        return waUtils::getFieldValues($types, 'type_id');
    }

    /**
     * @return array
     */
    public function getDefaultFilters()
    {
        return [
            'id'        => 'price',
            'name'      => _w('Price'),
            'type'      => '',
            'code'      => '',
            'type_name' => '',
            'available_for_sku' => false,
        ];
    }

    public function getProductFields($options = [])
    {
        $currency_model = new shopCurrencyModel();
        $currencies = $currency_model->getCurrencies();
        $currency_id = wa('shop')->getConfig()->getCurrency(true);
        $currency = $currencies[$currency_id];

        $options += [
            'types' => [],
            'tags' => [],
            'use_key' => true,
        ];

        $types = $options['types'];
        if ($options['types'] === true) {
            $type_model = new shopTypeModel();
            $types = $type_model->select('`id` AS `value`, `name`')->fetchAll();
        }
        $tags = $options['tags'];
        if ($options['tags'] === true) {
            $tag_model = new shopTagModel();
            $tags = $tag_model->select('`id` AS `value`, `name`, `count`')->fetchAll();
        }

        $fields = [
            'create_datetime' => [
                'name' => _w('Date added'),
                'data' => [
                    'type' => 'date',
                    'render_type' => 'range',
                ]
            ],
            'edit_datetime' => [
                'name' => _w('Last change date'),
                'data' => [
                    'type' => 'date',
                    'render_type' => 'range',
                ],
            ],
            'type_id' => [
                'name' => _w('Product type'),
                'data' => [
                    'type' => 'select',
                    'render_type' => 'select',
                    'options' => $types,
                ],
            ],
            'tag' => [
                'name' => _w('Tag'),
                'data' => [
                    'type' => 'select',
                    'render_type' => 'select',
                    'options' => $tags,
                ],
            ],
            'rating' => [
                'name' => _w('Rating'),
                'data' => [
                    'type' => 'double',
                    'render_type' => 'range',
                ],
            ],
            'price' => [
                'name' => _w('Price'),
                'data' => [
                    'type' => 'double',
                    'render_type' => 'range',
                    'currency' => $currency,
                ],
            ],
            'compare_price' => [
                'name' => _w('Compare at price'),
                'data' => [
                    'type' => 'double',
                    'render_type' => 'range',
                    'currency' => $currency,
                ],
            ],
            'purchase_price' => [
                'name' => _w('Purchase price'),
                'data' => [
                    'type' => 'double',
                    'render_type' => 'range',
                    'currency' => $currency,
                ],
            ],
            'count' => [
                'name' => _w('In stock'),
                'data' => [
                    'type' => 'double',
                    'render_type' => 'range',
                ],
            ],
            'badge' => [
                'name' => _w('Badge'),
                'data' => [
                    'type' => 'varchar',
                    'render_type' => 'select',
                    'options' => [
                        [
                            'name' => _w('New!'),
                            'value' => 'new',
                            'icon' => 'fas fa-bolt'
                        ],
                        [
                            'name' => _w('Low price!'),
                            'value' => 'lowprice',
                            'icon' => 'fas fa-piggy-bank',
                        ],
                        [
                            'name' => _w('Bestseller!'),
                            'value' => 'bestseller',
                            'icon' => 'fas fa-chart-line',
                        ],
                        [
                            'name' => _w('Custom badge'),
                            'value' => 'custom',
                            'icon' => 'fas fa-code',
                        ]
                    ]
                ],
            ],
        ];

        foreach ($fields as $key => &$field) {
            $field['data']['id'] = $key;
            // compromise for formatting
            $field['data']['name'] = $field['name'];
        }
        unset($field);
        if (!$options['use_key']) {
            $fields = array_values($fields);
        }

        return $fields;
    }

    /**
     * Returns path (relative to wa-data/public or wa-data/protected) to directory containing category's files.
     *
     * @param int $category_id
     * @return string
     */
    public static function getFolder(int $category_id): string
    {
        $str = str_pad($category_id, 4, '0', STR_PAD_LEFT);
        return join('/', [
            'categories',
            substr($str, -2),
            substr($str, -4, 2),
            $category_id,
            '',
        ]);
    }

    /**
     * Returns path to category's image filder or to $file inside it
     *
     * @param int $category_id
     * @param string $file Sub-path of file or directory
     * @param bool $public Whether path to either directly available or authorization-protected file/directory must be returned
     * @return string
     */
    public static function getImagePath(int $category_id, string $file = '', bool $public = false): string
    {
        $path = self::getFolder($category_id).'images/'.(strlen($file) ? ltrim($file, '/') : '');
        return wa()->getDataPath($path, $public, 'shop', false);
    }

    /**
     * Returns URL to public category's image filder or to $file inside it
     *
     * @param int $category_id
     * @param string $file Sub-path of file or directory
     * @param bool $public Whether path to either directly available or authorization-protected file/directory must be returned
     * @return string
     */
    public static function getImageUrl(int $category_id, string $file = '', bool $absolute=true): string
    {
        $path = self::getFolder($category_id).'images/'.(strlen($file) ? ltrim($file, '/') : '');
        return wa()->getDataUrl($path, true, 'shop', $absolute);
    }

    public static function getThumbInfo(array $category): ?array
    {
        if (empty($category['thumb_ext'])) {
            return null;
        }
        $result = [];
        $app_config = wa('shop')->getConfig();
        $v = strtotime(ifempty($category, 'edit_datetime', $category['create_datetime']));
        $template = self::getImageUrl($category['id'], '0/0.%s.'.$category['thumb_ext'].'?v='.$v, true);
        $allowed_sizes = $app_config->getOption('category_image_sizes');
        $enable_2x = $app_config->getOption('enable_2x');
        foreach ($allowed_sizes as $size) {
            $result['url'.$size] = sprintf($template, $size);
            if ($enable_2x) {
                $result['url'.$size.'@2x'] = sprintf($template, $size.'@2x');
            }
            if (empty($result['default'])) {
                // first image size in config is the default size
                $result['default'] = $result['url'.$size];
                if ($enable_2x) {
                    $result['default@2x'] = $result['url'.$size.'@2x'];
                }
            }
        }
        return $result;
    }
}
