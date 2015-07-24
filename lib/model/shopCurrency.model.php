<?php

class shopCurrencyModel extends waModel
{
    protected $table = 'shop_currency';

    protected $primary_currency;
    protected $currencies;
    protected $id = 'code';

    public function getAll($key = null, $normalize = false)
    {
        $data = $this->query("SELECT * FROM `{$this->table}` ORDER BY `sort`")->fetchAll($key, $normalize);
        foreach ($data as & $item) {
            $item['rate'] = (double) $item['rate'];
            if (!((double) $item['rounding'])) {
                $item['rounding'] = null;
            }
        }
        return $data;
    }

    public function getRate($currency)
    {
        $currency = $this->escape($currency);
        $rate = $this->query("SELECT rate FROM `{$this->table}` WHERE code = '$currency'")->fetchField('rate');
        return $this->castValue('double', $rate);
    }

    public function getById($code)
    {
        $currencies = $this->getCurrencies($code);
        if (!$currencies) {
            return false;
        }
        return (is_array($code)) ? $currencies : $currencies[$code];
    }

    public function getCurrencies($codes = null)
    {
        $runtime_cache = new waRuntimeCache('shop_currencies');
        if ($runtime_cache->isCached()) {
            $data = $runtime_cache->get();
        } else {
            $data = array();
            if ($cache = wa('shop')->getCache()) {
                $data = $cache->get('currencies');
            }
            if (!$data) {
                $data = array();
                $primary = $this->getPrimaryCurrency();
                $currencies = waCurrency::getAll(true);

                foreach ($this->query("SELECT * FROM " . $this->table . " ORDER BY sort") as $c) {
                    $code = $c['code'];
                    if (isset($currencies[$code])) {
                        $c['rate'] = (double)$c['rate'];
                        if (!((double)$c['rounding'])) {
                            $c['rounding'] = null;
                        }
                        $data[$code] = $currencies[$code] + $c;
                        $data[$code]['is_primary'] = $primary == $code;
                    }
                }
                if (!empty($cache)) {
                    $cache->set('currencies', $data, 86400);
                }
            }
            $runtime_cache->set($data);
        }

        if ($codes) {
            $result = array();
            foreach ((array) $codes as $code) {
                if (isset($data[$code])) {
                    $result[$code] = $data[$code];
                }
            }
            return $result;
        } else {
            return $data;
        }
    }

    public function convertByRate($price, $rate_from, $rate_to) {
        $price = $this->castValue('double', $price);
        if ($rate_from == $rate_to) {
            return $price;
        }
        $rate_from = (float) $rate_from;
        $rate_to   = (float) $rate_to;
        return ($price * $rate_from) / $rate_to;
    }

    public function convert($price, $from, $to)
    {
        $price = $this->castValue('double', $price);
        if ($from == $to) {
            return $price;
        }
        $currencies = $this->getCurrencies(array($from, $to));
        if (!isset($currencies[$from])) {
            throw new waException("Unknown currency: $from");
        }
        if (!isset($currencies[$to])) {
            throw new waException("Unknown currency: $to");
        }

        $rate_from = $currencies[$from]['rate'];
        $rate_to   = $currencies[$to]['rate'];
        if (!$rate_to) {
            throw new waException("Unknown rate of $rate_to or rate is 0");
        }
        return ($price * $rate_from) / $rate_to;
    }

    /**
     * Remove currency with (or not) converting
     * @param string $code
     * @param string|null $convert_to If null than converting is omitted
     * @return boolean
     */
    public function removeCurrency($code, $convert_to)
    {
        if (!$convert_to) {
            return $this->deleteById($code);
        }

        if ($code == $convert_to) {
            return false;
        }
        $currencies = $this->getCurrencies(array($code, $convert_to));
        if (!isset($currencies[$code])) {
            throw new waException("Unknown currency: $code");
        }
        if (!isset($currencies[$convert_to])) {
            throw new waException("Unknown currency: $convert_to");
        }

        $rate    = (double) $currencies[$code]['rate'];
        $rate_to = (double) $currencies[$convert_to]['rate'];
        if (!$rate || !$rate_to) {
            return false;
        }

        $this->convertPrices($code, $rate, $convert_to, $rate_to);
        $convert_to = $this->escape($convert_to);
        $this->exec("UPDATE `shop_product` SET currency = s:0 WHERE currency = s:1", $convert_to, $code);
        $this->exec("UPDATE `shop_service` SET currency = s:0 WHERE currency = s:1", $convert_to, $code);

        $this->recalcProductPrimaryPrices($convert_to);
        $this->recalcServicePrimaryPrices($convert_to);
        $this->deleteCache();
        return $this->deleteById($code);
    }

    public function getPrimaryCurrency()
    {
        if (!$this->primary_currency) {
            $this->primary_currency = wa('shop')->getConfig()->getCurrency();
        }
        return $this->primary_currency;
    }

    /**
     * Set new primary currency
     *
     * @param string $new_code
     * @param boolean $convert need converting prices in old currency
     * @return boolean
     */
    public function setPrimaryCurrency($new_code, $convert = true)
    {
        $old_code = $this->getPrimaryCurrency();
        if ($old_code != $new_code) {

            $currencies = $this->getCurrencies(array($old_code, $new_code));
            if (!isset($currencies[$old_code])) {
                throw new waException("Unknown currency: $old_code");
            }
            if (!isset($currencies[$new_code])) {
                throw new waException("Unknown currency: $new_code");
            }

            $old_rate = $this->castValue('double', $currencies[$old_code]['rate']);
            if (!$old_rate) {
                throw new waException("Old primary currency have zero rate");
            }

            $rate = $this->castValue('double', $currencies[$new_code]['rate']);
            if (!$rate) {
                throw new waException("New primary currency have zero rate");
            }

            // check invariant old primary currency rate == 1.0. If broken - repair before
            $old_rate = $this->getById($old_code);
            $old_rate = $old_rate['rate'];
            if ($old_rate != 1) {
                $this->updateByField('code', $old_code, array('rate' => 1));
            }

            if ($convert) {
                $this->convertPrices($old_code, 1, $new_code, $rate);
            }

            $this->exec("UPDATE `{$this->table}` SET rate = rate/$rate");
            $this->exec("UPDATE `shop_customer`  SET total_spent = total_spent/$rate");
            $this->exec("UPDATE `shop_product` SET total_sales = total_sales/$rate");
            $this->exec("UPDATE `shop_order` SET rate = rate/$rate");
            $this->exec("UPDATE `shop_expense` SET amount = amount/$rate");
            $this->exec("DELETE FROM `shop_sales`");
            $this->updateById($old_code, array('sort' => $currencies[$new_code]));
            $this->updateById($new_code, array('sort' => 0));

            if ($convert) {
                $this->exec("UPDATE `shop_product` SET currency = s:0 WHERE currency = s:1", $new_code, $old_code);
                $this->exec("UPDATE `shop_service` SET currency = s:0 WHERE currency = s:1", $new_code, $old_code);
            }

            $this->recalcProductPrimaryPrices();
            $this->recalcServicePrimaryPrices();

            wa('shop')->getConfig()->setCurrency($new_code);
            $this->primary_currency = $new_code;
            $this->deleteCache();
        }
        return true;
    }

    /**
     * Convert prices (user prices, NOT primary_prices)
     * @param string $from
     * @param double $rate_from
     * @param string $to
     * @param double $rate_to
     */
    private function convertPrices($from, $rate_from, $to, $rate_to)
    {
        $this->convertProductPrices($from, $rate_from, $to, $rate_to);
        $this->convertServicePrices($from, $rate_from, $to, $rate_to);
    }

    private function convertProductPrices($from, $rate_from, $to, $rate_to)
    {
        $rate_from = $this->castValue('double', $rate_from);
        $rate_to = $this->castValue('double', $rate_to);
        $cond = "p.currency = '".$this->escape($from)."'";
        $price_fields = array('price', 'purchase_price', 'compare_price');
        foreach ($price_fields as $p_name) {
            $sql = "UPDATE `shop_product_skus` ps
                JOIN `shop_product` p ON p.id = ps.product_id
                SET ps.{$p_name} = (ps.{$p_name}*{$rate_from})/{$rate_to}
                WHERE {$cond}";
            $this->exec($sql);
        }
    }

    private function convertServicePrices($from, $rate_from, $to, $rate_to)
    {
        $rate_from = $this->castValue('double', $rate_from);
        $rate_to = $this->castValue('double', $rate_to);

        $cond = "s.currency = '".$this->escape($from)."'";
        $sql = "UPDATE `shop_service_variants` sv
                JOIN `shop_service` s ON s.id = sv.service_id
                SET sv.price = (sv.price*$rate_from)/$rate_to
                WHERE sv.price IS NOT NULL AND $cond";
        $this->exec($sql);

        $sql = "UPDATE `shop_product_services` ps
                JOIN `shop_service` s ON s.id = ps.service_id
                SET ps.price = (ps.price*$rate_from)/$rate_to
                WHERE ps.price IS NOT NULL AND $cond";
        $this->exec($sql);

        $sql = "UPDATE `shop_service` s
                SET s.price = (price*$rate_from)/$rate_to, s.currency = '".$this->escape($to)."'
                WHERE $cond";
        $this->exec($sql);
    }

    public function add($code)
    {
        $code = $this->escape($code);
        if ($this->getById($code)) {
            return false;
        }
        $currencies = waCurrency::getAll(true);
        if (!isset($currencies[$code])) {
            return false;
        }
        $sort = $this->query("SELECT MAX(sort) sort FROM `{$this->table}`")->fetchField('sort') + 1;
        $result = $this->insert(array(
            'code' => $code,
            'sort' => $sort
        ));
        $this->deleteCache();
        return $result;
    }

    public function deleteCache()
    {
        $cache = wa('shop')->getCache();
        $cache && $cache->delete('currencies');
        $cache = new waRuntimeCache('shop_currencies');
        $cache->delete();
    }

    /**
     * Change rate of currency
     *
     * @param string $code
     * @param double $rate
     */
    public function changeRate($code, $rate)
    {
        $primary = $this->getPrimaryCurrency();
        if ($code == $primary) {
            return false;
        }
        $currency = $this->getById($code);
        if (!$currency) {
            return false;
        }

        $rate = (double) $rate;
        $old_rate = (double) $currency['rate'];
        if ($rate < 0) {
            return false;
        }
        if ($rate != $old_rate) {
            $result = $this->updateById($code, array('rate' => $rate));

            $this->recalcProductPrimaryPrices($code);
            $this->recalcServicePrimaryPrices($code);
            $this->deleteCache();
            return $result;
        }
        return true;
    }

    /**
     *
     * Recalc all "primary_price" fields of products
     *
     * @param string|null $code Affecting to products with this currency code or to all products in case null
     */
    private function recalcProductPrimaryPrices($code = null)
    {
        $where = $code ? "WHERE p.currency = '".$this->escape($code)."'" : '';
        $sql = "UPDATE `shop_product` p JOIN
            (
                SELECT p.id, MIN(ps.price) AS min_price, MAX(ps.price) AS max_price
                FROM `shop_product` p
                JOIN `shop_product_skus` ps ON ps.product_id = p.id
                GROUP BY p.id
            ) r ON p.id = r.id
            JOIN `shop_currency` c ON c.code = p.currency
            SET p.min_price = r.min_price*c.rate, p.max_price = r.max_price*c.rate
            $where
        ";
        $this->exec($sql);

        $sql = "UPDATE `shop_product` p
            JOIN `shop_product_skus` ps ON ps.product_id = p.id AND ps.id = p.sku_id
            JOIN `shop_currency` c ON c.code = p.currency
            SET p.price = ps.price*c.rate
            $where";
        $this->exec($sql);

        $sql = "UPDATE `shop_product` p
            JOIN `shop_product_skus` ps ON ps.product_id = p.id AND ps.id = p.sku_id
            JOIN `shop_currency` c ON c.code = p.currency
            SET p.compare_price = ps.compare_price*c.rate
            $where";
        $this->exec($sql);

        $sql = "UPDATE `shop_product` p
                JOIN `shop_product_skus` ps ON p.id = ps.product_id
                JOIN `shop_currency` c ON c.code = p.currency
                SET ps.primary_price = ps.price*c.rate
                $where";
        $this->exec($sql);
    }

    /**
     * Recalc all "primary_price" fields of services
     *
     * @param string|null $code Affecting to services with this currency code or to all services in case null
     */
    private function recalcServicePrimaryPrices($code = null)
    {
        $where = $code ? "WHERE s.currency = '".$this->escape($code)."'" : '';

        $sql = "UPDATE `shop_service_variants` sv
                JOIN `shop_service` s ON s.id = sv.service_id
                JOIN `shop_currency` c ON c.code = s.currency
                SET sv.primary_price = sv.price*c.rate
                $where";
        $this->exec($sql);

        $sql = "UPDATE `shop_product_services` ps
                JOIN `shop_service` s ON s.id   = ps.service_id
                JOIN `shop_currency` c ON c.code = s.currency
                SET ps.primary_price = ps.price*c.rate
                $where";
        $this->exec($sql);

        $sql = "UPDATE `shop_service` s
                JOIN `shop_service_variants` sv ON s.variant_id = sv.id
                SET s.price = sv.primary_price
                $where";
        $this->exec($sql);
    }

    public function move($code, $before_code = null)
    {
        $primary = $this->getPrimaryCurrency();
        if ($code == $primary) {
            return false;
        }
        if (!$before_code) {
            $item = $this->getById($code);
            if (!$item) {
                return false;
            }
            $sort = $this->query("SELECT MAX(sort) sort FROM {$this->table}")->fetchField('sort') + 1;
            $this->updateById($code, array('sort' => $sort));
        } else {
            $code = $this->escape($code);
            $before_code = $this->escape($before_code);
            $items = $this->query("SELECT * FROM {$this->table} WHERE code IN ('$code', '$before_code')")->fetchAll('code');
            if (!$items || count($items) != 2) {
                return false;
            }
            $sort = $items[$before_code]['sort'];
            $this->exec("UPDATE {$this->table} SET sort = sort + 1 WHERE sort >= $sort");
            $this->updateById($code, array('sort' => $sort));
        }
        $this->deleteCache();
        return true;
    }
}

