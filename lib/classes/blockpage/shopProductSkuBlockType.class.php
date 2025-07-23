<?php
wa('site');
/**
 * Block shows product name, description or price of a single shop product selected via block settings.
 */
class shopProductSkuBlockType extends siteBlockType
{
    public function render(siteBlockData $data, bool $is_backend, array $tmpl_vars=[])
    {
        $data->data['additional']['product'] = $tmpl_vars['product'];
        $data->data['product_id'] = $tmpl_vars['product']['id'];
        $tmpl_vars['modification'] = $tmpl_vars['sku_id'];
        $tmpl_vars['articles'] = ifset($tmpl_vars['product']['skus'], false);
        //$result = $this->additionalData($data);
        //$tmpl_vars['html'] = $tmpl_vars['product']['price'];//$result['html'];
        return parent::render($data, $is_backend, $tmpl_vars);
    }

    public function getExampleBlockData()
    {
        $result = $this->getEmptyBlockData();
        $result->data = ['info_type' => 'color', 'block_props' => ['font-header' => "t-rgl", 'font-size' => ["name" => "Size #5", "value" => "t-5", "unit" => "px", "type" => "library"], 'margin-top' => "m-t-0", 'margin-bottom' => "m-b-10", 'margin-right' => "m-r-16", 'align' => "t-l"]];
        return $result;
    }

    protected function getRawBlockSettingsFormConfig()
    {
        return [
            'type_name' => _w('Variant'),
            'sections' => [
                /*[   'type' => 'FontHeaderGroup',
                    'name' => _w('Font header'),
                ],*/
                [   'type' => 'TabsWrapperGroup',
                    'name' => _w('Tabs'),
                ],
                [   'type' => 'MarginGroup',
                    'name' => _w('Margin'),
                ],
                [   'type' => 'VisibilityGroup',
                    'name' => _w('Visibility on devices'),
                ],
                [   'type' => 'IdGroup',
                    'name' => _w('Identifier (ID)'),
                ],
            ],
        ] + parent::getRawBlockSettingsFormConfig();
    }

    public function getTypeId()
    {
        return 'shop.ProductSku';
    }
}
