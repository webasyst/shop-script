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
        if ($tmpl_vars['product']['status'] < 0) {
            $data->data['html'] = _w('Not available for sale');
        }
        if (!$tmpl_vars['product']['id']) {
            $data->data['html'] = _w('Choose product');
        }
        return parent::render($data, $is_backend, $tmpl_vars + [
            'children' => array_reduce($data->getRenderedChildren($is_backend, [
                'product' => $tmpl_vars['product'],
                'sku_id' => $data->data['sku_id'],
            ]), 'array_merge', []),
        ]);
    }

    public function additionalData(siteBlockData $data)
    {
        $hash = '';
        $fields = 'id,name,summary,images,price,skus,sku_type,status,sku_features';
        if (!empty($data->data['product_id'])) {
            $hash = 'id/'.$data->data['product_id'];
            /*if ($data->data['product_id'] !== $data->data['additional']['product']['id']) {
                unset($data->data['sku_id']);
            }*/
        }
        $products = (new shopProductsCollection($hash))->getProducts($fields, 0, 1);
        if (empty($products) || $hash == '') {
            //throw new waException('No product selected');
            $p = ['id' => null, 'sku_id' => null, 'sku_type' => '1','status' => '0', 'skus' => [], 'currency' => 'RUB', 'price' => '0', 'name' => '', 'summary' => ''];
        } else {
            $p = reset($products);
            unset($data->data['html']);
        }

        $data->data['product_id'] = $p['id'];
        if (empty($data->data['sku_id'])) {
            $data->data['sku_id'] = $p['sku_id'];
        }

        $result = [
            'product' => $p,
        ];
       /* switch (ifset($data->data, 'info_type', 'name')) {
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
        }*/
        return $result;
    }

    public function shouldRenderBlockOnSave($old_data, $new_data)
    {
        return (ifset($old_data, 'product_id', null) != ifset($new_data, 'product_id', null)) || (ifset($old_data, 'sku_id', null) != ifset($new_data, 'sku_id', null));
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
        $result->data = ['block_props' => ['padding-top' => "p-t-10", 'padding-bottom' => "p-b-10"], 'wrapper_props' => ['justify-align' => "j-s"]];

        return $result;
    }

    protected function getRawBlockSettingsFormConfig()
    {
        return [
            'type_name' => _w('Widget with a shopping button'),
            'sections' => [
                [   'type' => 'ProductIdGroup',
                    'name' => _w('Product'),
                ],
                [   'type' => 'ProductSkuGroup',
                    'name' => _w('Modification'),
                ],
                [   'type' => 'RowsAlignGroup',
                    'name' => _w('Alignment'),
                ],
                [   'type' => 'RowsWrapGroup',
                    'name' => _w('Wrap Line'),
                ],
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
                [   'type' => 'TagsGroup',
                    'name' => _w('SEO'),
                ],
            ],
        ] + parent::getRawBlockSettingsFormConfig();
    }

    public function getTypeId()
    {
        return 'shop.ProductSaleWidget';
    }
}
