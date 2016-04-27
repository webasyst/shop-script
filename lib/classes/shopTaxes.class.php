<?php

class shopTaxes
{

    /**
     * @param array $items order items to modify
     * @param array $params 'billing' => array(...), 'shipping' => array(...), 'discount_rate' => float
     * @return array : tax_id => array ( rate => float, included => bool, name => string )
     */
    public static function apply(&$items, $params, $currency = null)
    {
        $addresses = array_intersect_key($params, array('billing' => 1, 'shipping' => 1));
        $discount_rate = ifset($params['discount_rate'], 0);
        $tax_ids = array();
        $parent_tax_id = null;
        foreach($items as &$i) {
            if ($i['type'] == 'product') {
                $parent_tax_id = isset($i['product']['tax_id']) ?
                    $i['product']['tax_id'] :
                    (isset($i['tax_id']) ? $i['tax_id'] : 0);
            }
            if (!empty($i['product']['tax_id'])) {
                $tax_ids[] = $i['tax_id'] = $i['product']['tax_id'];
            } elseif (isset($i['service']['tax_id'])) {
                // inherit from product
                if ($i['service']['tax_id'] === '0') {
                    if ($parent_tax_id) {
                        $tax_ids[] = $i['tax_id'] = $parent_tax_id;
                    }
                } else {
                   $tax_ids[] = $i['tax_id'] = $i['service']['tax_id'];
                }
            } elseif (!empty($i['tax_id'])) {
                $tax_ids[] = $i['tax_id'];
            }

            $i['tax'] = 0;
            $i['tax_percent'] = 0;
            $i['tax_included'] = 0;
        }
        unset($i);

        if (empty($tax_ids)) {
            return array();
        }

        $result = array();
        $tm = new shopTaxModel();
        $trm = new shopTaxRegionsModel();
        $taxes = $tm->getById($tax_ids);
        foreach($taxes as $t) {
            $result[$t['id']] = array(
                'rate' => 0.0,
                'included' => $t['included'],
                'name' => $t['name'],
                'sum_included' => 0.0,
                'sum' => 0.0,
            );

            // Check if there are rates based on country and region
            $result[$t['id']]['rate'] = $trm->getByTaxAddress($t['id'], $addresses[$t['address_type']]);
        }

        // Rates by zip code override rates by region, when applicable
        $main_country = wa()->getSetting('country', null, 'shop');
        foreach (array('shipping', 'billing') as $addr_type) {
            // ZIP-based rates are only applied to main shop country
            if (empty($addresses[$addr_type]['zip']) || (!empty($addresses[$addr_type]['country']) && $addresses[$addr_type]['country'] !== $main_country)) {
                continue;
            }

            $tzcm = new shopTaxZipCodesModel();
            foreach($tzcm->getByZip($addresses[$addr_type]['zip'], $addr_type, $tax_ids) as $tax_id => $rate) {
                $result[$tax_id]['rate'] = $rate;
                $result[$tax_id]['name'] = $taxes[$tax_id]['name'];
            }
        }

        // Compute tax values for each item, and total tax
        foreach($items as &$i) {
            $tax_id = ifempty($i['tax_id']);
            $i['tax_percent'] = ifset($result[$tax_id]['rate'], 0.0);
            $i['tax_included'] = ifset($result[$tax_id]['included']);

            $p = shop_currency((1 - $discount_rate) * $i['price'] * $i['quantity'], $i['currency'], $currency, false);
            $r = ifset($result[$tax_id]['rate'], 0.0);

            if ($i['tax_included']) {
                $i['tax'] = $p*$r/(100.0+$r);
            } else {
                $i['tax'] = $p*$r/100.0;
            }

            if ($i['tax_included']) {
                $result[$tax_id]['sum_included'] += $i['tax'];
            } elseif  ($i['tax']) {
                $result[$tax_id]['sum'] += $i['tax'];
            }
        }
        unset($i);

        return $result;
    }

    /**
     * Creates new or modifies existing tax.
     *
     * Examples:
     *

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

     *
     * @param array $tax_data
     * @return array DB row from shop_tax, including id (useful for new record)
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
            foreach($tax_data['zip_codes'] as $code => $rate) {
                if (!$code) {
                    continue;
                }
                $code = str_replace('*', '%', $code);
                $rate = (float) str_replace(',', '.', ifempty($rate, '0'));
                $rows[$code] = array(
                    'tax_id' => $tax['id'],
                    'zip_expr' => $code,
                    'tax_value' => $rate,
                    'sort' => count($rows),
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
            foreach($tax_data['countries'] as $country_iso3 => $country_data) {
                $country_global_rate = (float) str_replace(',', '.', ifempty($country_data['global_rate'], '0'));

                $no_region_added = true;
                $params_added = false;
                if (!empty($country_data['regions']) && is_array($country_data['regions'])) {
                    foreach($country_data['regions'] as $region_code => $region_data) {
                        if (is_array($region_data)) {
                            $tax_value_modifier = ifempty($region_data['tax_value_modifier']);
                            $tax_value = (float) str_replace(',', '.', ifempty($region_data['tax_value'], '0'));
                            $tax_name = ifempty($region_data['name']);
                        } else {
                            $tax_name = null;
                            $tax_value_modifier = '';
                            $tax_value = (float) str_replace(',', '.', $region_data);
                        }
                        $params = null;

                        if ($tax_value_modifier) {
                            $params = serialize(array(
                                'tax_value_modifier' => $tax_value_modifier,
                                'tax_value' => $tax_value,
                            ));
                            switch($tax_value_modifier) {
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
                            'tax_id' => $tax['id'],
                            'tax_name' => $tax_name,
                            'country_iso3' => $country_iso3,
                            'region_code' => $region_code,
                            'tax_value' => $tax_value,
                            'params' => $params,
                        );

                        if ($params) {
                            $params_added = true;
                        }
                        $no_region_added = false;
                    }
                }

                if ($no_region_added || $params_added || $country_global_rate > 0) {
                    $region_rates[] = array(
                        'tax_id' => $tax['id'],
                        'tax_name' => null,
                        'country_iso3' => $country_iso3,
                        'region_code' => null,
                        'tax_value' => $country_global_rate,
                        'params' => null,
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
