<?php
/*
 * Formatter for /shop currencies
 */
class shopFrontApiCurrencyFormatter extends shopFrontApiFormatter
{
    public function format(array $currency)
    {
        $schema = [
            'code' => 'string',
            'sign' => 'string',
            'iso4217' => 'string',
            'sign_delim' => 'string',
            'title' => 'string',
            'sign_html' => 'string',
            'rounding' => 'string',
            'sign_position' => 'integer',
            'precision' => 'integer',
            'rate' => 'number',
            'round_up_only' => 'boolean',
            'sort' => 'integer',
            'is_primary' => 'boolean',
            'name' => 'array',
            'frac_name' => 'array',
        ];
        $currency = self::formatFieldsToType($currency, $schema);
        return array_intersect_key($currency, $schema);
    }
}
