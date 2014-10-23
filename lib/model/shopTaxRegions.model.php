<?php

class shopTaxRegionsModel extends waModel
{
    protected $table = 'shop_tax_regions';

    public function getByTax($tax_id)
    {
        return $this->getByField('tax_id', $tax_id, true);
    }

    /**
     * @param int $tax_id
     * @param array $address
     * @return float
     */
    public function getByTaxAddress($tax_id, $address)
    {
        $result = false;
        $country = ifempty($address['country'], wa('shop')->getConfig()->getGeneralSettings('country'));

        if ($country) {
            $data = array('id' => $tax_id, 'country' => $country);
            if (empty($address['region'])) {
                $region_sql = " AND region_code IS NULL ";
            } else {
                $data['region'] = $address['region'];
                $region_sql = " AND (region_code IS NULL OR region_code=:region) ";
            }

            $sql = "SELECT tax_value
                    FROM {$this->table}
                    WHERE tax_id=:id
                        AND country_iso3=:country
                        {$region_sql}
                    ORDER BY region_code IS NOT NULL
                    LIMIT 1";
            $result = $this->query($sql, $data)->fetchField();
        }

        if ($result === false) {
            static $all_rates = null, $eu_rates = null, $rest_rates = null;
            if ($rest_rates === null) {
                $all_rates = $eu_rates = $rest_rates = array();
                $sql = "SELECT * FROM {$this->table} WHERE country_iso3 IN ('%AL', '%EU', '%RW') AND region_code iS NULL";
                foreach($this->query($sql) as $row) {
                    switch($row['country_iso3']) {
                        case '%AL':
                            $all_rates[$row['tax_id']] = $row['tax_value'];
                            break;
                        case '%EU':
                            $eu_rates[$row['tax_id']] = $row['tax_value'];
                            break;
                        case '%RW':
                            $rest_rates[$row['tax_id']] = $row['tax_value'];
                            break;
                    }
                }
            }
            if (isset($all_rates[$tax_id])) {
                $result = $all_rates[$tax_id];
            }
            if (isset($rest_rates[$tax_id])) {
                $result = $rest_rates[$tax_id];
            }
            if ($country && self::isEuropean($country)) {
                $result = ifset($eu_rates[$tax_id]);
            }
        }

        return (float) $result;
    }

    public static function isEuropean($country_iso3)
    {
        static $list = array(
            'aut' => true,
            'bel' => true,
            'bgr' => true,
            'cyp' => true,
            'cze' => true,
            'dnk' => true,
            'est' => true,
            'fin' => true,
            'fra' => true,
            'deu' => true,
            'grc' => true,
            'hun' => true,
            'irl' => true,
            'ita' => true,
            'lva' => true,
            'ltu' => true,
            'lux' => true,
            'mlt' => true,
            'nld' => true,
            'pol' => true,
            'prt' => true,
            'rou' => true,
            'svk' => true,
            'svn' => true,
            'esp' => true,
            'swe' => true,
            'gbr' => true,
        );
        return !empty($list[$country_iso3]);
    }
}

