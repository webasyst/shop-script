<?php

class shopDebugPlugin extends shopPlugin
{
    /**
     *
     * @param array $params
     * @param array [string]array $params['data'] raw product entry data
     * @param array [string][string]array $params['data']['debug_plugin'] raw plugin entry data, described at productCustomFieldsHandler
     * @param array [string]shopProduct $params['instance'] product entry instance
     */
    public function productSaveHandler($params)
    {
        waLog::log(var_export($params['data'], true), __FUNCTION__.'.log');
        if (isset($params['data']['debug_plugin']['field1'])) {//'Custom field#1'
            //TODO update field at plugin table/run some code/etc
        }
    }

    public function productCustomFieldsHandler()
    {
        return array(
            'product' => array(
                'field1' => 'Custom field#1',
                'field2' => 'Custom field#2',
            ),
            'sku'    => array(
                'field1' => 'Custom SKU field#1',
                'field2' => 'Custom SKU field#2',
            ),
        );
    }

    public function backendMenu($params)
    {
        $selected = (waRequest::get('plugin') == $this->id) ? 'selected' : 'no-tab';
        return array(
            'aux_li' => "<li class=\"small float-right {$selected}\" id=\"s-plugin-debug\"><a href=\"?plugin=debug\">Debug tools</a></li>",
        );
    }

    /**
     *
     * @param array $ids
     */
    public function productDeleteHandler($ids)
    {
        waLog::log(var_export($ids, true), __FUNCTION__.'.log');
    }

    public static function getContactFields()
    {
        $options = array();
        $fields = waContactFields::getAll();
        foreach ($fields as $field) {
            if ($field instanceof waContactCompositeField) {
                /**
                 * @var waContactCompositeField $field
                 */
                $composite_fields = $field->getFields();
                foreach ($composite_fields as $composite_field) {
                    /**
                     * @var waContactField $composite_field
                     */
                    $options[] = array(
                        'group' => $field->getName(),
                        'title' => $composite_field->getName(),
                        'value' => $field->getId().'.'.$composite_field->getId(),
                    );
                }
            } else {
                /**
                 * @var waContactField $field
                 */
                $options[] = array(
                    'title' => $field->getName(),
                    'value' => $field->getId(),
                );
            }
        }
        return $options;
    }

    public function backendOrders($params)
    {
        return array(
            'sidebar_top_li'    => '<li class="list"><a href="#/orders/hash/id/15271,15270/">Hello debug</a></li>',
            'sidebar_section'   => '',
            'sidebar_bottom_li' => '<li class="list"><a href="#/orders/view=split&hash=id%2F15271%2C15270/">Goodbye debug</a></li>',
        );
    }

    public function frontendProducts(&$params)
    {
        // YOUR CONDITION
        if (0) {
            foreach ($params['products'] as &$p) {
                $p['price'] = $p['price'] * 0.9;
                // or
                // $p['price'] = $p['original_price'] * 0.9;
            }
            unset($p);
            if (isset($params['skus'])) {
                foreach ($params['skus'] as &$s) {
                    $s['price'] = $s['price'] * 0.9;
                    // or
                    // $s['price'] = $s['original_price'] * 0.9;
                }
                unset($s);
            }
        }
    }

    public function orderCalculateDiscount($params)
    {
        $order = $params['order'];

        $currency = $order['currency'];
        $result = array();
        foreach ($order['items'] as $item_id => $item) {
            // YOUR CONDITION
            if (0) {
                // calculate discount for only one product in order currency, for example 30%
                $result['items'][$item_id] = array(
                    'discount'    => shop_currency($item['price'] * 0.3, $item['currency'], $currency, false) * $item['quantity'],
                    'description' => _w('Discount 30%')
                );
            }
        }
        // free shipping
        $params['order']['shipping'] = 0;
        $result['discount'] = 0;
        $result['description'] = _w('Free shipping');

        return $result;
    }

    public function backendProductSkuSettings($params)
    {
        $product = $params['product'];
        $sku = $params['sku'];
        $sku_id = $params['sku_id'];

        return <<<HTML
    <div class="field">
        <div class="name">Debug</div>
        <div class="value strike">
            <input type="text" name="skus[{$sku_id}][debug_plugin][custom_field]" value="" class="short numerical">
        </div>
    </div>
HTML;

    }
}
