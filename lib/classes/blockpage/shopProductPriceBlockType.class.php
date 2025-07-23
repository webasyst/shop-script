<?php
wa('site');
/**
 * Block shows product name, description or price of a single shop product selected via block settings.
 */
class shopProductPriceBlockType extends siteBlockType
{
    public function render(siteBlockData $data, bool $is_backend, array $tmpl_vars=[])
    {
        $data->data['additional']['product'] = $tmpl_vars['product'];
        if ($tmpl_vars['product']['id'] === null) {
            return;
        }
        $data->data['product_id'] = $tmpl_vars['product']['id'];
        $tmpl_vars['modification'] = $tmpl_vars['sku_id'];

        if (ifset($tmpl_vars['product']['skus'], $tmpl_vars['sku_id'], false)) {
            $tmpl_vars['html'] = wa_currency_html($tmpl_vars['product']['skus'][$tmpl_vars['sku_id']]['primary_price_float'], $tmpl_vars['product']['currency']);
        } else {
            $tmpl_vars['html'] = 0;
        }
        return parent::render($data, $is_backend, $tmpl_vars);
    }

    public function getExampleBlockData()
    {
        $result = $this->getEmptyBlockData();
        $result->data = ['info_type' => 'price', 'tag' => 'p', 'block_props' => ['font-header' => "t-rgl", 'font-size' => ["name" => "Size #5", "value" => "t-5", "unit" => "px", "type" => "library"], 'margin-top' => "m-t-0", 'margin-bottom' => "m-b-10", 'margin-right' => "m-r-16",'align' => "t-l"]];
        return $result;
    }

    protected function getRawBlockSettingsFormConfig()
    {
        return [
            'type_name' => _w('Price info'),
            'sections' => [
                [   'type' => 'FontHeaderGroup',
                    'name' => _w('Font header'),
                ],
                [   'type' => 'FontGroup',
                    'name' => _w('Font'),
                ],
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
        return 'shop.ProductPrice';
    }
}
