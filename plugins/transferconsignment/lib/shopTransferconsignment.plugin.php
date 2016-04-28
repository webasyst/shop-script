<?php

class shopTransferconsignmentPlugin extends shopTransferPrintformPlugin
{
    public function preparePrintform($data, waView $view)
    {
        $primary_currency = $data['primary_currency'];
        $products = $data['products'];
        $skus = $data['skus'];

        $total_price = 0;

        $transfer = $data['transfer'];
        foreach ($transfer['skus'] as &$item) {
            $sku = $skus[$item['sku_id']];
            $product = $products[$sku['product_id']];
            $currency = $product['currency'] !== null ? $product['currency'] : $primary_currency;
            $price = round($sku['price'], 2);
            $total = $price * $item['count'];
            $item['info'] = array(
                'name' => $product['name'] . ($sku['name'] ? ' (' . $sku['name'] . ')' : ''),
                'price' => wa_currency($price, '', '%'),
                'total' => wa_currency($total, '', '%'),
                'currency' => $currency
            );

            // convert from own currency to primary currency
            $total_pc = shop_currency($total, $currency, $primary_currency, false);
            // apply precision
            $total_pc = wa_currency($total_pc, '', '%');
            // accumulate
            $total_price += $total_pc;
        }
        unset($item);

        $data['transfer'] = $transfer;
        $data['total_price'] = $total_price;

        return $data;
    }
}