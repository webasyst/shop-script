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
        $hidden_block = !$is_backend && ($tmpl_vars['product']['status'] == '-1' || $tmpl_vars['product']['id'] === null);
        if ($hidden_block) {
            return;
        }

        $tmpl_vars['html'] = $data->data['additional']['html'];
        $tmpl_vars['disabled'] = $data->data['additional']['disabled'];
        return parent::render($data, $is_backend, $tmpl_vars);
    }

    public function additionalData(siteBlockData $data)
    {
        $result_html = $this->getExampleTitle($data);
        if (!empty($data->data['product_id'])) {
            $prod_data = new shopProduct($data->data['product_id']);
            $p = $prod_data->data;

            if (empty($data->data['sku_id'])) {
                $data->data['sku_id'] = $p['sku_id'];
            }
            $p['skus'] = $prod_data->skus;
            $sku = [];
            if (isset($data->data['sku_id']) && isset($p['skus'][$data->data['sku_id']])) {
                $sku = $p['skus'][$data->data['sku_id']];
            }


            switch (ifset($data->data, 'info_type', 'name')) {
                case 'price':
                    //$result['html'] = shop_currency($p['price'], ["unit" => $p['currency'], "in_currency" => $p['currency'], "format" => "price_wrapper"]);
                    $price = $sku['price'] ?? $p['price'];
                    $result_html = wa_currency_html($price, $p['currency']);
                    break;
                case 'compare_price':
                    $compare_price = $sku['compare_price'] ?? $p['compare_price'];
                    $result_html = '<span style="text-decoration: line-through;">'.wa_currency_html($compare_price, $p['currency']).'</span>';
                    break;
                case 'description':
                    $result_html = strip_tags($p['description']);
                    break;
                case 'summary':
                    $result_html = strip_tags($p['summary']);
                    break;
                case 'stock':
                    $result_html = strip_tags($this->getStockInfo($sku['count'] ?? $p['count']));
                    break;
                case 'name':
                default:
                    $result_html = htmlspecialchars($p['name']);
                    break;
            }

        } else {
            $p = ['id' => null, 'sku_id' => null, 'skus' => [], 'sku_type' => '1', 'status' => '0','price' => _w('Product price'), 'name' => _w('Product name'), 'summary' => _w('Product description')];
        }

        $result = [
            'product' => $p,
            'html' => $result_html,
            'disabled' => $p['id'] !== null && $p['status'] < 0,
        ];

        return $result;
    }

    public function shouldRenderBlockOnSave($old_data, $new_data)
    {
        return (ifset($old_data, 'product_id', null) != ifset($new_data, 'product_id', null));
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
                [
                    'type' => 'ProductSkuGroup',
                    'name' => _w('Variant'),
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

    public function getStockInfo($n=0)
    {
        $low=5;
        $critical=2;

    if ($n > $low || $n === null)
        $result = '<span class="text-green">'._w("In stock").'</span>';
    elseif ($n > $critical)
        $result = '<span class="stock-low">'._w("Only %d left in stock", "Only %d left in stock", $n).'</span>';
    elseif ($n > 0)
        $result = '<span class="text-orange">'._w("Only %d left in stock", "Only %d left in stock", $n).'</span>';
    else
        $result = '<span class="stock-none">'._w("Out of stock").'</span>';

        return $result;
    }

    protected function getExampleTitle(siteBlockData $data)
    {
        $info_type = ifset($data->data, 'info_type', null);
        switch ($info_type) {
            case 'price':
                return _w('Product price');
            case 'compare_price':
                return _w('Strike-through product price');
            case 'description':
                return _w('Product description');
            case 'summary':
                return _w('Product summary');
            case 'stock':
                return _w('Product availability');
            default:
                return _w('Product name');
        }
    }
}
