<?php
wa('site');
/**
 * Block shows product name, description or price of a single shop product selected via block settings.
 */
class shopProductInfoBlockType extends siteBlockType
{
    public function render(siteBlockData $data, bool $is_backend, array $tmpl_vars=[])
    {
        $tmpl_vars['product'] = $data->data['additional']['product'];

        $tmpl_vars['html'] = $data->data['additional']['html'];

        if ($tmpl_vars['product']['status'] < 0) {
            $data->data['additional']['html'] = _w('Unavailable for purchase');
        }
        return parent::render($data, $is_backend, $tmpl_vars);
    }

    public function additionalData(siteBlockData $data)
    {
        $hash = '';
        $fields = 'id,name,summary,images,price,status';
        if (!empty($data->data['product_id'])) {
            $hash = 'id/'.$data->data['product_id'];
        }
        $products = (new shopProductsCollection($hash))->getProducts($fields, 0, 1);
        if (empty($products) || $hash == '') {
            $p = ['id' => null, 'sku_id' => null, 'skus' => [], 'sku_type' => '1', 'status' => '0','price' => _w('Product price'), 'name' => _w('Product name'), 'summary' => _w('Product description')];
            if (empty($products)) {
                $p['status'] = '-1';
            }
        } else {
            $p = reset($products);
            //unset($data->data['html']);
        }

        $data->data['product_id'] = $p['id'];
        $result = [
            'product' => $p,
        ];

        switch (ifset($data->data, 'info_type', 'name')) {
            case 'price':
                $result['html'] = shop_currency($p['price']);
                break;
            case 'description':
                $result['html'] = strip_tags($p['summary']);
                break;
            case 'name':
            default:
                $result['html'] = htmlspecialchars($p['name']);
                break;
        }
        return $result;
    }

    public function getExampleBlockData()
    {

        $result = $this->getEmptyBlockData();
        $result->data = ['info_type' => 'name', 'tag' => 'p', 'block_props' => ['font-header' => "t-rgl", 'font-size' => ["name" => "Size #3", "value" => "t-3", "unit" => "px", "type" => "library"], 'margin-top' => "m-t-0", 'margin-bottom' => "m-b-12", 'align' => "t-l"]];
        return $result;
    }

    protected function getRawBlockSettingsFormConfig()
    {
        return [
            'type_name' => _w('Product info'),
            'sections' => [
                [   'type' => 'ProductIdGroup',
                    'name' => _w('Product'),
                ],
                [   'type' => 'ProductInfoGroup',
                    'name' => _w('Type of product information'),
                ],
                [   'type' => 'FontHeaderGroup',
                    'name' => _w('Font header'),
                ],
                [   'type' => 'FontGroup',
                    'name' => _w('Font'),
                ],
                /*[   'type' => 'FontStyleGroup',
                    'name' => _w('Font style'),
                ],
                [   'type' => 'TextColorGroup',
                    'name' => _w('Color'),
                ],*/
                [   'type' => 'LineHeightGroup',
                    'name' => _w('Line height'),
                ],
                [   'type' => 'AlignGroup',
                    'name' => _w('Alignment'),
                ],
                [   'type' => 'TabsWrapperGroup',
                    'name' => _w('Tabs'),
                ],
                [   'type' => 'MarginGroup',
                    'name' => _w('Margin'),
                ],
                [   'type' => 'ShadowsGroup',
                    'name' => _w('Shadows'),
                    'shadow_type' => 'text'
                ],
                [   'type' => 'VisibilityGroup',
                    'name' => _w('Visibility on devices'),
                ],
                [   'type' => 'IdGroup',
                    'name' => _w('Identifier (ID)'),
                ],
                [   'type' => 'TagsGroup',
                    'name' => _w('SEO'),
                ],
            ],
        ] + parent::getRawBlockSettingsFormConfig();
    }

    public function getTypeId()
    {
        return 'shop.ProductInfo';
    }
}
