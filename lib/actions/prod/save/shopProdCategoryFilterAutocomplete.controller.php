<?php

class shopProdCategoryFilterAutocompleteController extends waController
{
    public function execute()
    {
        $term = waRequest::request('term', '', waRequest::TYPE_STRING_TRIM);

        $result = $this->getProductFields($term);
        $result += $this->getFeatures($term);
        echo json_encode(array_values($result));
    }

    /**
     * @param string $term
     * @return array[]
     * @throws waException
     */
    protected function getProductFields($term)
    {
        $currency_model = new shopCurrencyModel();
        $currencies = $currency_model->getCurrencies();
        $currency_id = wa('shop')->getConfig()->getCurrency(true);
        $currency = $currencies[$currency_id];

        $type_model = new shopTypeModel();
        $types = $type_model->select('`id`, `name`')->fetchAll();

        $tag_model = new shopTagModel();
        $tags = $tag_model->getAll();

        $fields = [
            [
                'name' => _w('Date added'),
                'data' => [
                    'id' => 'create_datetime',
                    'type' => 'date',
                    'render_type' => 'range',
                ]
            ],
            [
                'name' => _w('Дата последнего изменения'),
                'data' => [
                    'id' => 'edit_datetime',
                    'type' => 'date',
                    'render_type' => 'range',
                ],
            ],
            [
                'name' => _w('Type'),
                'data' => [
                    'id' => 'type',
                    'type' => 'select',
                    'render_type' => 'select',
                    'options' => $types,
                ],
            ],
            [
                'name' => _w('Tag'),
                'data' => [
                    'id' => 'tag',
                    'type' => 'select',
                    'render_type' => 'select',
                    'options' => $tags,
                ],
            ],
            [
                'name' => _w('Rating'),
                'data' => [
                    'id' => 'rating',
                    'type' => 'double',
                    'render_type' => 'range',
                ],
            ],
            [
                'name' => _w('Price'),
                'data' => [
                    'id' => 'price',
                    'type' => 'double',
                    'render_type' => 'range',
                    'currency' => $currency,
                ],
            ],
            [
                'name' => _w('Compare at price'),
                'data' => [
                    'id' => 'compare_price',
                    'type' => 'double',
                    'render_type' => 'range',
                    'currency' => $currency,
                ],
            ],
            [
                'name' => _w('Purchase price'),
                'data' => [
                    'id' => 'purchase_price',
                    'type' => 'double',
                    'render_type' => 'range',
                    'currency' => $currency,
                ],
            ],
            [
                'name' => _w('In stock'),
                'data' => [
                    'id' => 'count',
                    'type' => 'double',
                    'render_type' => 'range',
                ],
            ],
            [
                'name' => _w('Badge'),
                'data' => [
                    'id' => 'badge',
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

        foreach ($fields as &$field) {
            $field['type'] = 'product_param';
            $field['data']['name'] = $field['name'];
            $field['data']['display_type'] = 'product';
            if ($field['data']['render_type'] == 'range') {
                $field['data']['options'] = [
                    ['name' => '', 'value' => ''],
                    ['name' => '', 'value' => '']
                ];
            }
        }

        return array_filter($fields, function ($field) use ($term) {
            return mb_stripos($field['name'], $term) !== false;
        });
    }

    protected function getFeatures($term)
    {
        $result = [];

        $model = new shopFeatureModel();
        $term = $model->escape($term, 'like');
        $where = "`type` != 'text' AND `type` != 'divider' AND `type` NOT LIKE '2d.%' AND `type` NOT LIKE '3d.%' 
            AND `parent_id` IS NULL AND (`name` LIKE '%$term%' OR `code` LIKE '%$term%')";
        $features = $model->where($where)->order('`count` DESC')->limit(20)->fetchAll('code', true);
        foreach ($features as $feature_code => &$feature) {
            $feature["code"] = $feature_code;
        }
        unset($feature);

        $selectable_values = shopPresentation::addSelectableValues($features);
        $features = shopProdSkuAction::formatFeatures($selectable_values);

        foreach ($features as $feature) {
            $feature['display_type'] = 'feature';
            $result[] = [
                'name' => $feature['name'],
                'code' => $feature['code'],
                'type' => 'feature',
                'data' => $feature,
            ];
        }

        return $result;
    }
}