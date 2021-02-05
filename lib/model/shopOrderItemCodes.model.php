<?php
class shopOrderItemCodesModel extends waModel
{
    protected $table = 'shop_order_item_codes';

    /**
     * Load product codes into order items.
     *
     * @param array $order_items   rows from shop_order_items indexed by their ids
     * @param array $products      rows from shop_product indexed by their id (we take 'type_id' key from there)
     * @return array - copy of $order_items witn additional key 'product_codes' added to every item
     */
    public function extendOrderItems($order_items, $products=null)
    {
        // Load products data to take type_id from
        if ($products === null) {
            $product_ids = [];
            foreach($order_items as $item) {
                $product_ids[$item['product_id']] = $item['product_id'];
            }
            $product_model = new shopProductModel();
            $products = $product_model->getById($product_ids);
            unset($product_ids, $item);
        }

        // Get values for product codes
        $product_code_values = $this->getByItemId(array_keys($order_items));

        // Get product codes applicable for items of this order
        $product_code_model = new shopProductCodeModel();
        $product_codes = $product_code_model->getByType(array_map(function($item) use ($products) {
            return ifset($products, $item['product_id'], 'type_id', 0);
        }, $order_items));

        // Product codes that have been assigned to order items but do not belong to product type
        // (This may happen if product code has been changed or forcefully assigned to order via plugin)
        $additional_code_ids = array_flip(array_map(function($row) {
            return $row['code_id'];
        }, $product_code_values));
        $additional_code_ids = array_diff_key($additional_code_ids, $product_codes);
        $product_codes += $product_code_model->getById(array_keys($additional_code_ids));

        $all_enabled_plugins = wa('shop')->getConfig()->getPlugins();
        foreach ($product_codes as $id => $code) {
            $code_plugin_enabled = !empty($code['plugin_id']) ? isset($all_enabled_plugins[$code['plugin_id']]) : false;
            $product_codes[$id]['code_plugin_enabled'] = $code_plugin_enabled;
            $product_codes[$id]['protected_code'] = $code['protected'] && $code_plugin_enabled;
        }

        foreach ($order_items as &$item) {
            // Product codes - they can be assigned to any order item including services.
            // GUI shows them for products and services when assigned already, but only for products if not assigned.
            $item['product_codes'] = [];
            if ($item['type'] === 'product') {
                $current_product_type_id = ifset($products, $item['product_id'], 'type_id', 0);
                foreach($product_codes as $code) {
                    if (isset($code['type_ids'][0]) || isset($code['type_ids'][$current_product_type_id])) {
                        $code['values'] = [];
                        unset($code['type_ids']);
                        $item['product_codes'][$code['id']] = $code;
                    }
                }
            }
        }
        unset($item);

        // Assign values for product codes into items
        foreach($product_code_values as $row) {
            $index = $row['sort'];
            $code_id = $row['code_id'];
            $item_id = $row['order_item_id'];
            if (!isset($order_items[$item_id])) {
                // ignore codes for deleted order items
                continue;
            }
            if (!isset($order_items[$item_id]['product_codes'][$code_id])) {
                if (isset($product_codes[$code_id])) {
                    // This must be a code that does not belong to product type.
                    // Maybe product type settings changed. Or code force assigned by a plugin.
                    // Can even be assigned to service.
                    $order_items[$item_id]['product_codes'][$code_id] = $product_codes[$code_id];
                } else {
                    // An old code may be removed from settings, but still need to show it in existing orders.
                    $order_items[$item_id]['product_codes'][$code_id] = [
                        'id' => $code_id,
                        'name' => $row['code']." (deleted id={$code_id})",
                        'code' => $row['code'],
                        'values' => [],
                    ];
                }
            }

            if (!isset($order_items[$item_id]['product_codes'][$code_id]['values'][$index])) {
                $order_items[$item_id]['product_codes'][$code_id]['values'][$index] = $row['value'];
            } else {
                $order_items[$item_id]['product_codes'][$code_id]['values'][] = $row['value'];
            }
        }

        foreach ($order_items as &$item) {
            $item['expected_product_code_blocks_count'] = $item['quantity'];
            if (!empty($item['product_codes'])) {
                foreach($item['product_codes'] as $code) {
                    $item['expected_product_code_blocks_count'] = max($item['expected_product_code_blocks_count'], count($code['values']));
                }
            }
        }
        unset($item);

        return $order_items;
    }

    public function getByItemId($item_ids)
    {
        if (!$item_ids) {
            return [];
        }
        $sql = "SELECT * FROM {$this->table} WHERE order_item_id IN (?) ORDER BY order_item_id, sort";
        return $this->query($sql, [$item_ids])->fetchAll();
    }

    public function countOrdersByCode($code_id)
    {
        $sql = "SELECT COUNT(DISTINCT order_id) FROM {$this->table} WHERE code_id=?";
        return $this->query($sql, [$code_id])->fetchField();
    }
}
