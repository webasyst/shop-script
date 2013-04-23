<?php

class shopFrontendCartSaveController extends waJsonController
{
    public function execute()
    {
        $cart = new shopCart();
        $item_id = waRequest::post('id');
        $cart_items_model = new shopCartItemsModel();
        $item = $cart_items_model->getById($item_id);
        if ($q = waRequest::post('quantity', 0, 'int')) {
            if (!wa()->getSetting('ignore_stock_count')) {
                if ($item['type'] == 'product') {
                    $sku_model = new shopProductSkusModel();
                    $sku = $sku_model->getById($item['sku_id']);
                    // check quantity
                    if ($sku['count'] !== null && $q > $sku['count']) {
                        $q = $sku['count'];
                        $this->response['error'] = sprintf(_w('Only %d left in stock. Sorry.'), $q);
                        $this->response['q'] = $q;
                    }
                }
            }
            $cart->setQuantity($item_id, $q);
            $this->response['item_total'] = shop_currency($cart->getItemTotal($item_id), true);
        } elseif ($v = waRequest::post('service_variant_id')) {
            $cart->setServiceVariantId($item_id, $v);
            $this->response['item_total'] = shop_currency($cart->getItemTotal($item['parent_id']), true);
        }
        $this->response['total'] = shop_currency($cart->total(), true);
        $this->response['discount'] = shop_currency($cart->discount(), true);
        $this->response['discount_numeric'] = $cart->discount();
        $this->response['count'] = $cart->count();
    }

    public function getFullPrice($item)
    {
    }

    public function getServices($product_id, $sku_id)
    {
        $product_model = new shopProductModel();
        $product = $product_model->getById($product_id);

        $type_service_model = new shopTypeServicesModel();
        $service_ids = $type_service_model->getServiceIds($product['type_id']);

        $sql = "SELECT v.*, ps.price p_price, ps.status, ps.sku_id, s.currency FROM shop_service_variants v
                LEFT JOIN shop_product_services ps ON v.id = ps.service_variant_id AND ps.product_id = i:product_id
                JOIN shop_service s ON v.service_id = s.id
                WHERE ".($service_ids ? "v.service_id IN (i:service_ids) OR ": '')."
                ps.product_id = i:product_id OR ps.sku_id = i:sku_id
                ORDER BY ps.sku_id";

        $product_services_model = new shopProductServicesModel();
        $rows = $product_services_model->query($sql, array(
            'service_ids' => $service_ids, 'product_id' => $product_id, 'sku_id' => $sku_id))->fetchAll();

        $services = array();
        foreach ($rows as $row) {
            $services[$row['service_id']][$row['id']] = array(
                'name' => $row['name'],
                'price' => $row['p_price'] ? $row['p_price'] : $row['price'],
                'currency' => $row['currency']
            );
        }
        return $services;
    }
}
