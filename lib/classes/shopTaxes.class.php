<?php

class shopTaxes
{

    /**
     * @param array $items order items to modify
     * @param array $params 'billing' => array(...), 'shipping' => array(...), 'discount_rate' => float
     * @param string $currency
     * @return array : tax_id => array ( rate => float, included => bool, name => string )
     */
    public static function apply(&$items, $params, $currency = null)
    {
        if ($currency === null) {
            $currency = wa('shop')->getConfig()->getCurrency(false);
        }

        // Gather info about taxes we need to apply.
        // This sets $i.tax_*, on all $items and gathers $tax_ids
        // !!! $items must be sorted in a particular way: product, then all of its services!
        $tax_ids = array();
        $parent_tax_id = null;
        foreach ($items as &$i) {
            if ($i['type'] == 'product') {
                $parent_tax_id = isset($i['product']['tax_id']) ?
                    $i['product']['tax_id'] :
                    (isset($i['tax_id']) ? $i['tax_id'] : 0);
            }
            if (isset($i['service']['tax_id'])) {
                if ($i['service']['tax_id'] === '0') {
                    // inherit from product
                    if (isset($i['product']['tax_id'])) {
                        $tax_ids[] = $i['tax_id'] = $i['product']['tax_id'];
                    } elseif ($parent_tax_id) {
                        $tax_ids[] = $i['tax_id'] = $parent_tax_id;
                    }
                } else {
                    $tax_ids[] = $i['tax_id'] = $i['service']['tax_id'];
                }
            } elseif (!empty($i['product']['tax_id'])) {
                $tax_ids[] = $i['tax_id'] = $i['product']['tax_id'];
            } elseif (!empty($i['tax_id'])) {
                $tax_ids[] = $i['tax_id'];
            }

            $i['tax'] = 0;
            $i += array(
                'tax_percent'  => null,
                'tax_included' => 0,
            );
        }
        unset($i);

        if (empty($tax_ids)) {
            return array();
        }

        $addresses = array_intersect_key($params, array('billing' => 1, 'shipping' => 1));
        $result = self::getTaxes($tax_ids, $addresses);

        //
        // To properly calculate taxes, we must have all discounts attached
        // to particular order item.
        //
        // Unfortunately, some types of discounts are not attached to an item and apply
        // globally to the whole order. To be able to calculate taxes, we need to
        // split such global discount proportionally between all items.
        //
        // To do that, we calculate effective value for each item in order:
        //      effective_value = i.price*i.quantity - i.total_discount
        // and split global discount between items proportionally
        // to this effective_value.
        //

        // Calculate certain values to use later
        $order_subtotal = 0.0;
        $total_item_discount = 0.0;
        $total_effective_value = 0.0;
        foreach ($items as &$i) {
            if ($i['type'] == 'shipping') {
                continue;
            }

            // $order_subtotal is total value of all items, no discounts or taxes applied
            $item_value = $i['price'] * $i['quantity'];
            if (!empty($i['currency'])) {
                $item_value = shop_currency($i['price'] * $i['quantity'], $i['currency'], $currency, false);
            }

            $order_subtotal += $item_value;

            // $total_effective_value is item value minus discounts
            $item_discount = ifset($i, 'total_discount', 0.0);
            if (!empty($i['currency'])) {
                $item_discount = shop_currency(ifset($i, 'total_discount', 0.0), $i['currency'], $currency, false);
            }
            $total_item_discount += $item_discount;

            $item_effective_value = $item_value - $item_discount;
            $total_effective_value += $item_effective_value;

            // Save effective value to use later
            $i['effective_value'] = $item_effective_value;
        }
        unset($i);

        // $discount_rate is $global_discount / $order_subtotal.
        // From this we can deduce $global_discount we need to split between items.
        $global_discount = 0;
        $discount_rate = min(1.0, max(0, ifset($params, 'discount_rate', 0)));
        if ($discount_rate > 0) {
            $global_discount = $order_subtotal * $discount_rate - $total_item_discount;
            $global_discount = max(0, $global_discount);
        }

        // Compute tax values for each item, and total tax
        foreach ($items as &$i) {
            $tax_id = ifempty($i['tax_id']);
            if (isset($result[$tax_id])) {
                $i['tax_percent'] = ifset($result[$tax_id]['rate'], null);
                $i['tax_included'] = ifset($result[$tax_id]['included']);
            }

            $p = $i['price'] * $i['quantity'];

            // Split global discount proportionally between all items except shipping
            if ($global_discount > 0 && $i['type'] != 'shipping' && $total_effective_value > 0) {
                $p -= $global_discount * $i['effective_value'] / $total_effective_value;
            }

            if (!empty($i['currency'])) {
                $p = shop_currency($p, $i['currency'], $currency, false);
            }

            $r = max(0.0, ifset($result[$tax_id]['rate'], 0.0));

            if ($i['tax_included']) {
                $i['tax'] = $p * $r / (100.0 + $r);
            } else {
                $i['tax'] = $p * $r / 100.0;
            }

            $i['tax'] = waCurrency::round($i['tax'], $currency);

            if ($i['tax_included']) {
                $result[$tax_id]['sum_included'] += $i['tax'];
            } elseif ($i['tax']) {
                $result[$tax_id]['sum'] += $i['tax'];
            }

            // we don't need this internal info anymore
            unset($i['effective_value']);
        }
        unset($i);

        return $result;
    }

    protected static function getTaxes($tax_ids, $addresses)
    {
        if (empty($tax_ids)) {
            return array();
        }
        $result = array();
        $tm = new shopTaxModel();
        $trm = new shopTaxRegionsModel();
        $taxes = $tm->getById($tax_ids);
        foreach ($taxes as $t) {
            $result[$t['id']] = array(
                'rate'         => 0.0,
                'included'     => $t['included'],
                'name'         => $t['name'],
                'sum_included' => 0.0,
                'sum'          => 0.0,
            );

            // Check if there are rates based on country and region
            $result[$t['id']]['rate'] = $trm->getByTaxAddress($t['id'], $addresses[$t['address_type']]);
        }

        // Rates by zip code override rates by region, when applicable
        $main_country = wa()->getSetting('country', null, 'shop');
        foreach (array('shipping', 'billing') as $address_type) {
            // ZIP-based rates are only applied to main shop country
            if (empty($addresses[$address_type]['zip']) || (!empty($addresses[$address_type]['country']) && $addresses[$address_type]['country'] !== $main_country)) {
                continue;
            }

            $tzcm = new shopTaxZipCodesModel();
            foreach ($tzcm->getByZip($addresses[$address_type]['zip'], $address_type, $tax_ids) as $tax_id => $rate) {
                $result[$tax_id]['rate'] = $rate;
                $result[$tax_id]['name'] = $taxes[$tax_id]['name'];
            }
        }
        return $result;
    }

    public static function shipping($shipping, $params, $currency = null)
    {
        $addresses = array_intersect_key($params, array('billing' => 1, 'shipping' => 1));


        $tax_ids = array($shipping['tax_id']);


            $shipping['tax'] = 0;
            $shipping['tax_percent'] = null;
            $shipping['tax_included'] = 0;

        $result = self::getTaxes($tax_ids, $addresses);

        // Compute tax values for each item, and total tax
        $tax_id = ifempty($shipping['tax_id']);
        $shipping['tax_percent'] = ifset($result[$tax_id]['rate'], null);
        $shipping['tax_included'] = ifset($result[$tax_id]['included']);

        $p = $shipping['price'];

        $p = shop_currency($p, $shipping['currency'], $currency, false);
        $r = max(0.0, ifset($result[$tax_id]['rate'], 0.0));

        if ($shipping['tax_included']) {
            $shipping['tax'] = $p * $r / (100.0 + $r);
        } else {
            $shipping['tax'] = $p * $r / 100.0;
        }

        $shipping['tax'] = waCurrency::round($shipping['tax'], $currency);

        if ($shipping['tax_included']) {
            $result[$tax_id]['sum_included'] += $shipping['tax'];
        } elseif ($shipping['tax']) {
            $result[$tax_id]['sum'] += $shipping['tax'];
        }

        return $result;
    }

    /**
     * Creates new or modifies existing tax.
     *
     * Examples:
     * <pre>

        // Pass id to modify existing tax:
        shopTaxes::save(array(
            'id' => 100500,
            ... see below ...
        ));

        // Pass no id to create new tax:
        shopTaxes::save(array(
            'name' => '...',
            'included' => true,           // true for taxes included in price, false for added
            'address_type' => 'shipping', // shipping|billing

            // rates by zip code: zip code => %
            'zip_codes' => array(
                '1234*' => 1.11,
                '123*' => 2.22,
            ),

            // rates by country and/or country regions
            'countries' => array(

                // Country in simple mode: global rate only, no by-region setup
                'rus' => array(
                    'global_rate' => 10.1, // %
                ),

                // Country in advanced mode: rate by region
                'usa' => array(
                    'regions' => array(
                        'AK' => 2.34, // %
                        'AL' => 3.45,
                    ),
                ),

                // Country in super-advanced mode: global rate and by-region modifiers
                'can' => array(
                    'global_rate' => 5,
                    'regions' => array(
                        'BC' => array(
                            'name' => 'ASDF',
                            'tax_value' => 1.23, // %
                            'tax_value_modifier' => '+',
                        ),
                        'MB' => array(
                            'name' => 'QWER',
                            'tax_value' => 1.05,
                            'tax_value_modifier' => '*',
                        ),
                        'NB' => array(
                            'name' => 'ZXCV',
                            'tax_value' => 6.15,
                            'tax_value_modifier' => '', // replaces global rate
                        ),
                    ),
                ),

                // Use special codes instead of country-iso3 for groups:
                // '%AL' = All countries
                // '%EU' = All european countries
                // '%RW' = Rest of the world
            ),
        ));
</pre>
     *
     *
     * @param array $tax_data
     * @return array DB row from shop_tax, including id (useful for new record)
     * @throws waException
     */
    public static function save($tax_data)
    {
        if (!is_array($tax_data)) {
            throw new waException('$tax_data must be an array.');
        }

        $tm = new shopTaxModel();

        //
        // shop_tax
        //
        $tax = $tm->getEmptyRow();
        unset($tax['id']);
        $tax = array_intersect_key($tax_data, $tax) + $tax;

        // Default values (instead of validation)
        if (!strlen(ifset($tax['name'], ''))) {
            $tax['name'] = _w('<no name>');
        }
        $tax['included'] = $tax['included'] ? 1 : 0;
        $tax['address_type'] = $tax['address_type'] === 'shipping' ? 'shipping' : 'billing';

        // Save into shop_tax
        if (empty($tax_data['id'])) {
            $tax['id'] = $tm->insert($tax);
        } else {
            $tm->updateById($tax_data['id'], $tax);
            $tax['id'] = $tax_data['id'];
        }

        //
        // shop_tax_zip_codes
        //
        $tzcm = new shopTaxZipCodesModel();
        $tzcm->deleteByField('tax_id', $tax['id']);
        if (!empty($tax_data['zip_codes']) && is_array($tax_data['zip_codes'])) {
            $rows = array();
            foreach ($tax_data['zip_codes'] as $code => $rate) {
                if (!$code) {
                    continue;
                }
                $code = str_replace('*', '%', $code);
                $rate = (float)str_replace(',', '.', ifempty($rate, '0'));
                $rows[$code] = array(
                    'tax_id'    => $tax['id'],
                    'zip_expr'  => $code,
                    'tax_value' => $rate,
                    'sort'      => count($rows),
                );
            }
            if ($rows) {
                $tzcm->multipleInsert(array_values($rows));
            }
        }

        //
        // shop_tax_regions
        //
        $trm = new shopTaxRegionsModel();
        $trm->deleteByField('tax_id', $tax['id']);
        if (!empty($tax_data['countries']) && is_array($tax_data['countries'])) {
            $region_rates = array();
            foreach ($tax_data['countries'] as $country_iso3 => $country_data) {
                $country_global_rate = (float)str_replace(',', '.', ifempty($country_data['global_rate'], '0'));

                $no_region_added = true;
                $params_added = false;
                if (!empty($country_data['regions']) && is_array($country_data['regions'])) {
                    foreach ($country_data['regions'] as $region_code => $region_data) {
                        if (is_array($region_data)) {
                            $tax_value_modifier = ifempty($region_data['tax_value_modifier']);
                            $tax_value = (float)str_replace(',', '.', ifempty($region_data['tax_value'], '0'));
                            $tax_name = ifempty($region_data['name']);
                        } else {
                            $tax_name = null;
                            $tax_value_modifier = '';
                            $tax_value = (float)str_replace(',', '.', $region_data);
                        }
                        $params = null;

                        if ($tax_value_modifier) {
                            $params = serialize(array(
                                'tax_value_modifier' => $tax_value_modifier,
                                'tax_value'          => $tax_value,
                            ));
                            switch ($tax_value_modifier) {
                                case '*':
                                    $tax_value *= $country_global_rate;
                                    break;
                                case '+':
                                    if ($country_global_rate) {
                                        $tax_value += $country_global_rate;
                                    } else {
                                        $params = null;
                                    }
                                    break;
                            }
                            if ($tax_value == $country_global_rate) {
                                continue;
                            }
                        }

                        $region_rates[] = array(
                            'tax_id'       => $tax['id'],
                            'tax_name'     => $tax_name,
                            'country_iso3' => $country_iso3,
                            'region_code'  => $region_code,
                            'tax_value'    => $tax_value,
                            'params'       => $params,
                        );

                        if ($params) {
                            $params_added = true;
                        }
                        $no_region_added = false;
                    }
                }

                if ($no_region_added || $params_added || $country_global_rate > 0) {
                    $region_rates[] = array(
                        'tax_id'       => $tax['id'],
                        'tax_name'     => null,
                        'country_iso3' => $country_iso3,
                        'region_code'  => null,
                        'tax_value'    => $country_global_rate,
                        'params'       => null,
                    );
                }
            }

            if ($region_rates) {
                $trm->multipleInsert($region_rates);
            }
        }

        return $tax;
    }
}
