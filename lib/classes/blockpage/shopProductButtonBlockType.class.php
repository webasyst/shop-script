<?php
wa('site');
/**
 * Block shows product name, description or price of a single shop product selected via block settings.
 */
class shopProductButtonBlockType extends siteBlockType
{

    public function render(siteBlockData $data, bool $is_backend, array $tmpl_vars=[])
    {
        $data->data['additional']['product'] = $tmpl_vars['product'];
        $data->data['product_id'] = $tmpl_vars['product']['id'];
        $tmpl_vars['disabled'] = $tmpl_vars['product']['status'] < 1;
        if (!$tmpl_vars['disabled'] && $tmpl_vars['sku_id']) {
            $count = $tmpl_vars['product']['skus'][$tmpl_vars['sku_id']]['count'];
            $tmpl_vars['disabled'] = $count !== null && $count <= 0;
        }
        $tmpl_vars['modification'] = $tmpl_vars['sku_id'];
        //$result = $this->additionalData($data);
       // $tmpl_vars['html'] = $tmpl_vars['product']['price'];//$result['html'];
        return parent::render($data, $is_backend, $tmpl_vars);
    }

    public function getExampleBlockData()
    {
        $result = $this->getEmptyBlockData();
        $result->data = ['html' => _w('Add to cart'), 'goto' => 'cart', 'tag' => 'a', 'block_props' => ['margin-bottom' => "m-b-10", 'button-style' => ['name' => "complementary", 'scheme' => 'complementary', 'value' => "btn-a", 'type' => 'palette'], 'button-size' => 'inp-m p-l-13 p-r-13']];
        return $result;
    }

    protected function getRawBlockSettingsFormConfig()
    {
        return [
            'type_name' => _w('Button'),
            'sections' => [
                /*[   'type' => 'ButtonLinkGroup',
                    'name' => _w('Action'),
                ],*/
                [   'type' => 'ButtonStyleGroup',
                    'name' => _w('Style'),
                ],
                [   'type' => 'ButtonSizeGroup',
                    'name' => _w('Size'),
                ],
                [   'type' => 'TabsWrapperGroup',
                    'name' => _w('Tabs'),
                ],
                [   'type' => 'MarginGroup',
                    'name' => _w('Margin'),
                ],
                [   'type' => 'ShadowsGroup',
                    'name' => _w('Shadows'),
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
        return 'shop.ProductButton';
    }
}
