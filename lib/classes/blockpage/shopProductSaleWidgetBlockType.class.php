<?php
wa('site');
/**
 * Block shows product name, description or price of a single shop product selected via block settings.
 */
class shopProductSaleWidgetBlockType extends siteBlockType
{

    public function render(siteBlockData $data, bool $is_backend, array $tmpl_vars=[])
    {
        $tmpl_vars['product'] = $data->data['additional']['product'];
        $hidden_block = !$is_backend && $tmpl_vars['product']['id'] === null;
        if ($hidden_block) {
            return;
        }
        // if ($tmpl_vars['product']['status'] < 0) {
        //     $data->data['html'] = _w('Unavailable for purchase');
        // }
        // if (!$tmpl_vars['product']['id']) {
        //     $data->data['html'] = _w('Choose product');
        // }
        return parent::render($data, $is_backend, $tmpl_vars + [
            'children' => array_reduce($data->getRenderedChildren($is_backend, [
                'product' => $tmpl_vars['product'],
                'sku_id' => ifset($tmpl_vars['product']['sku_id']),
                'disabled' => ifset($tmpl_vars['product']['status']) < 0,
            ]), 'array_merge', []),
        ]);
    }

    public function additionalData(siteBlockData $data)
    {
        $hash = '';
        $fields = 'id,name,summary,images,price,skus,sku_type,status,sku_features';
        if (!empty($data->data['product_id'])) {
            $hash = 'id/'.$data->data['product_id'];
            $products = (new shopProductsCollection($hash, ['frontend' => false]))->getProducts($fields, 0, 1);
            if (wa()->getEnv() === 'frontend' && $products && $products[$data->data['product_id']]['status'] == '-1') {
                $products = [];
            }
        }

        if (empty($products) || $hash == '') {
            //throw new waException('No product selected');
            $p = ['id' => null, 'sku_id' => null, 'sku_type' => '1','status' => '0', 'skus' => [], 'currency' => 'RUB', 'price' => '0', 'name' => '', 'summary' => ''];
        } else {
            $p = reset($products);
            unset($data->data['html']);
        }
        $data->data['product_id'] = $p['id'];

        if(!isset($data->data['hidden_attrs'])) {
            $data->data['hidden_attrs'] = [];
        }
        if(!isset($data->data['element_layout'])) {
            $data->data['element_layout'] = 'line';
        }

        $result = [
            'product' => $p,
        ];

        return $result;
    }

    public function shouldRenderBlockOnSave($old_data, $new_data)
    {
        return (ifset($old_data, 'product_id', null) != ifset($new_data, 'product_id', null));
    }

    public function getExampleBlockData()
    {
        // Default row contents: vertical sequence with heading and a paragraph of text
        $hseq = (new siteVerticalSequenceBlockType())->getEmptyBlockData();
        $price = (new shopProductPriceBlockType())->getExampleBlockData();
        $button = (new shopProductButtonBlockType())->getExampleBlockData();
        $sku = (new shopProductSkuBlockType())->getExampleBlockData();
        $sku->data['indestructible'] = true;
        $button->data['indestructible'] = true;
        $price->data['indestructible'] = true;
        $hseq->addChild($sku);
        $hseq->addChild($price);
        $hseq->addChild($button);
        //$hseq->addChild((new siteParagraphBlockType())->getExampleBlockData());
        $hseq->data['is_horizontal'] = true;
        $hseq->data['indestructible'] = true;

        $hseq->data['is_complex'] = 'no_complex';
        $result = $this->getEmptyBlockData();
        $result->addChild($hseq, '');
        $result->data = ['block_props' => ['padding-top' => "p-t-10", 'padding-bottom' => "p-b-10", 'full-width' => 'f-w'], 'wrapper_props' => ['justify-align' => "j-s"], 'element_layout' => 'line', 'hidden_attrs' => ['sku' => 1, 'price' => 1]];

        return $result;
    }

    protected function getRawBlockSettingsFormConfig()
    {
        return [
            'type_name' => _w('Widget'),
            'sections' => [
                [   'type' => 'ProductIdGroup',
                    'name' => _w('Product'),
                ],
                [   'type' => 'ProductSkuElementLayoutGroup',
                    'name' => _w('Positioning'),
                ],
                [   'type' => 'RowsAlignGroup',
                    'name' => _w('Alignment'),
                ],
                [   'type' => 'RowsWrapGroup',
                    'name' => _w('Wrap line'),
                ],
                [   'type' => 'RowsAttrsVisibilityGroup',
                    'name' => _w('Display in each variant'),
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
        return 'shop.ProductSaleWidget';
    }
}
