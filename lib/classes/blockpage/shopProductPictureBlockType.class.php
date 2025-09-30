<?php
wa('site');
/**
 */
class shopProductPictureBlockType extends siteBlockType
{
    public function render(siteBlockData $data, bool $is_backend, array $tmpl_vars=[])
    {
        $tmpl_vars['product'] = $data->data['additional']['product'];
        $tmpl_vars['is_empty'] = $data->data['additional']['is_empty'];
        $tmpl_vars['disabled'] = $data->data['additional']['disabled'];
        $hidden_block = !$is_backend && $tmpl_vars['product']['id'] === null;
        if ($hidden_block) {
            return;
        }
        //$tmpl_vars['html'] = $data->data['additional']['html'];
        return parent::render($data, $is_backend, $tmpl_vars);
    }

    public function additionalData(siteBlockData $data)
    {
        $hash = '';
        $fields = 'id,name,summary,images,price,status';
        if (!empty($data->data['product_id'])) {
            $hash = 'id/'.$data->data['product_id'];

            $products = (new shopProductsCollection($hash, ['frontend' => false]))->getProducts($fields, 0, 1);
            if (wa()->getEnv() === 'frontend' && $products && $products[$data->data['product_id']]['status'] == '-1') {
                $products = [];
            }
        }

        if (empty($products) || $hash == '') {
            //throw new waException('No product selected');
            $p = ['id' => null, 'sku_id' => null, 'skus' => [], 'sku_type' => '1', 'status' => '0','price' => _w('Product price'), 'name' => _w('Product name'), 'summary' => _w('Product description')];
            if (empty($products)) {
                $p['status'] = '-1';
            }
        } else {
            $p = reset($products);
            unset($data->data['html']);
        }

        $data->data['product_id'] = $p['id'];
        $result = [
            'product' => $p,
            'is_empty' => empty($p['image_id']) || $p['id'] === null,
            'disabled' => !empty($p['image_id']) && $p['status'] == '-1',
        ];
        return $result;
    }

    public function getExampleBlockData()
    {
        $result = $this->getEmptyBlockData();
        $result->data = ['picture_type' => 'url_big', 'block_props' => ['margin-bottom' => "m-b-14"]];
        return $result;
    }

    protected function getRawBlockSettingsFormConfig()
    {
        return [
            'type_name' => _w('Product picture'),
            'sections' => [
                [   'type' => 'ProductIdGroup',
                    'name' => _w('Product'),
                ],
                [   'type' => 'ProductPictureGroup',
                    'name' => _w('Type of product picture'),
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
                [   'type' => 'BorderGroup',
                    'name' => _w('Border'),
                    'is_block' => true, //Exception IMG element
                ],
                [   'type' => 'BorderRadiusGroup',
                    'name' => _w('Angle'),
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
        return 'shop.ProductPicture';
    }
}
