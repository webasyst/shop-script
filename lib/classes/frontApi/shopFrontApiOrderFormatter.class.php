<?php
/*
 */
class shopFrontApiOrderFormatter extends shopFrontApiFormatter
{
    public function format(array $order)
    {
        $allowed_fields = [
            'id' => 'integer',
            'create_datetime' => 'string',
            'state_id' => 'string',
            'currency' => 'string',
            'paid_date' => 'string',
            'items' => 'array',
        ];

        $price_fields = ['total', 'tax', 'shipping', 'discount'];
        $result = self::formatPriceField($order, $price_fields, $order['currency']);
        foreach ($price_fields as $f) {
            foreach ([
                '' => 'number', 
                '_exact' => 'string', 
                '_str' => 'string', 
                '_html' => 'string',
            ] as $suffix => $type) {
                $allowed_fields[$f.$suffix] = $type;
            }
        }

        $items = $result['items'];
        unset($result['items']);
        $formatter = $this->getItemFormatter();
        foreach ($items as &$item) {
            $item = $formatter->format($item);
            $item['value'] = $item['price']*$item['quantity'];
            $item = shopFrontApiFormatter::formatPriceField($item, ['price', 'value'], $order['currency']);
        }
        unset($item);
        $result['items'] = $items;

        return array_intersect_key(self::formatFieldsToType($result, $allowed_fields), $allowed_fields);
    }

    protected function getItemFormatter()
    {
        return new shopFrontApiItemFormatter();
    }
}
