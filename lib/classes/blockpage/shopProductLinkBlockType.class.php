<?php
wa('site');
/**
 * Block shows product name, description or price of a single shop product selected via block settings.
 */
class shopProductLinkBlockType extends siteBlockType
{

    public function render(siteBlockData $data, bool $is_backend, array $tmpl_vars=[])
    {
        $tmpl_vars['product'] = $data->data['additional']['product'];
        if ($tmpl_vars['product']['id']) {
            $data->data['link_props']['href'] = $data->data['additional']['href'];
        }
       // $data->data['sku_id'] = $tmpl_vars['sku_id'];
        if ($tmpl_vars['product']['status'] < 0) {
            $data->data['html'] = _w('Unavailable for purchase');
        }

        //list($frontend_urls, $total_storefronts_count, $url_template) = $this->getFrontendUrls($product, false);
        $storefronts = shopHelper::getStorefronts(true);
        if ($storefronts){
            $data->data['link_props']['storefronts'] = $storefronts;
        }

        return parent::render($data, $is_backend, $tmpl_vars);
    }

    public function additionalData(siteBlockData $data)
    {
        //$hash = '';
        $fields = 'id,name,summary,url,status';
        if (!empty($data->data['product_id'])) {
            //$hash = 'id/'.$data->data['product_id'];
            $prod_data = new shopProduct($data->data['product_id']);
            //
            $p = $prod_data->data; 

            if (!empty($data->data['link_props']['storefront'])) {
                $storefront = $data->data['link_props']['storefront'];
            } else {
                $routing = wa()->getRouting();
                $storefront = preg_replace('@^https?://@', '', $routing->getUrl('shop/frontend', true));
            }
            //wa_dump($storefront);
            $url = $prod_data ->getProductUrl($storefront, true);
            if ($url) {
                $data->data['link_props']['href'] = $p['url'];
            }
            
        } else {
            $p = ['id' => null, 'sku_id' => null, 'skus' => [], 'sku_type' => '1', 'status' => '0','price' => _w('Product price'), 'name' => _w('Product name'), 'summary' => _w('Product description')];
            $url = '';
            //$result_html = _w('Choose product');
        }
            $data->data['product_id'] = $p['id'];

            $result = [
                'product' => $p,
                'href' => $url
            ];
            //$result['html'] = $result_html;
    
    
            return $result;
    }
    public function getExampleBlockData()
    {
        $result = $this->getEmptyBlockData();
        $result->data = ['html' => _w('Go to product'), 'tag' => 'a', 'link_props' => ['href' => "", 'rel' => false, 'target' => false], 'block_props' => ['margin-bottom' => "m-b-0", 'button-style' => ['name' => "complementary", 'scheme' => 'complementary', 'value' => "btn-a", 'type' => 'palette'], 'button-size' => 'inp-m p-l-13 p-r-13']];
        return $result;
    }

    protected function getRawBlockSettingsFormConfig()
    {
        return [
            'type_name' => _w('Product link'),
            'sections' => [
                [   'type' => 'ProductIdGroup',
                    'name' => _w('Product'),
                ],
                [   'type' => 'ProductLinkGroup',
                    'name' => _w('Action'),
                ],
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
        return 'shop.ProductLink';
    }
}
