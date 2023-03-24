<?php

/**
 * Class shopCurrencyModel
 * array(
 *  'code'=>'char(3)',
 *  'rate'=>'decimal(18,10)',
 *  'rounding'=>'decimal(8,2),
 *  'round_up_only'=>'int(11)',
 *  'sort'=>int(11),
 * )
 */
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
        return (is_array($code)) ? $currencies : ifset($currencies, $code, false);
    }

    public function getCurrencies($codes = null)
    {
        $runtime_cache = new waRuntimeCache('shop_currencies');
        if ($runtime_cache->isCached()) {
            $data = $runtime_cache->get();
        } else {
            $data = array();
            if ($cache = wa('shop')->getCache()) {
                $locale = wa()->getLocale();
                $data = $cache->get('currencies_'.$locale);
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
                            $c['rounding'] = self::getRounding($currencies[$code]);
                        }
                        $data[$code] = $currencies[$code] + $c;
                        $data[$code]['is_primary'] = $primary == $code;
                    }
                }
                if (!empty($cache)) {
                    $cache->set('currencies_'.$locale, $data, 86400);
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

    public function convertByRate($price, $rate_from, $rate_to)
    {
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
        $price = $this->castValue('decimal', $price);
        if ($from == $to) {
            return $price;
        }
        if (!$price) {
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
     * @return bool
     * @throws waException
     */
    public function removeCurrency($code, $convert_to)
    {
        if ($convert_to) {
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

            $rate = (double)$currencies[$code]['rate'];
            $rate_to = (double)$currencies[$convert_to]['rate'];
            if (!$rate || !$rate_to) {
                return false;
            }

            $this->convertPrices($code, $rate, $convert_to, $rate_to);
            $this->exec("UPDATE `shop_product` SET currency = s:0 WHERE currency = s:1", $convert_to, $code);
            $this->exec("UPDATE `shop_service` SET currency = s:0 WHERE currency = s:1", $convert_to, $code);

            $this->recalcProductPrimaryPrices($convert_to);
            $this->recalcServicePrimaryPrices($convert_to);
        }
        /**
         * @event currency_delete
         * @param string [string]mixed $params
         * @param string [string]string $params['code'] Currency code
         * @param string [string]string $params['convert_to'] Target convert currency code
         * @return void
         */
        $params = array(
            'code'       => $code,
            'convert_to' => $convert_to,
        );
        wa('shop')->event('currency_delete', $params);
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
     * @param bool $convert need converting prices in old currency
     * @return bool
     * @throws waException
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

            $currency_number = $this->getAllowedMaxNumber($this, 'rate');
            $this->exec("UPDATE `{$this->table}` SET rate = LEAST(rate/$rate, $currency_number)");

            $customer_model = new shopCustomerModel();
            $allowed_customer_number = $this->getAllowedMaxNumber($customer_model, 'total_spent');
            $this->exec("UPDATE `shop_customer`  SET total_spent = LEAST(total_spent/$rate, $allowed_customer_number)");

            $product_model = new shopProductModel();
            $allowed_product_number = $this->getAllowedMaxNumber($product_model, 'total_sales');
            $this->exec("UPDATE `shop_product` SET total_sales = LEAST(total_sales/$rate, $allowed_product_number)");

            $order_model = new shopOrderModel();
            $allowed_order_number = $this->getAllowedMaxNumber($order_model, 'rate');
            $this->exec("UPDATE `shop_order` SET rate = LEAST(rate/$rate, $allowed_order_number)");
            try {
                $this->exec("UPDATE `shop_order_params` SET value = value/$rate WHERE name='shipping_currency_rate'");
            } catch (waException $e) {
                waLog::log('Error updating currencies. Perhaps there is a currency with a comma.');
            }
            $expense_model = new shopExpenseModel();
            $allowed_expense_number = $this->getAllowedMaxNumber($expense_model, 'amount');
            $this->exec("UPDATE `shop_expense` SET amount = LEAST(amount/$rate, $allowed_expense_number)");
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

            /**
             * @event currency_primary
             * @param string [string]mixed $params
             * @param string [string]string $params['code'] New primary currency code
             * @param string [string]string $params['old_code'] Currency code used to be primary
             * @param string [string]string $params['old_rate'] New primary currency rate relative to the old one
             * @return void
             */
            $params = array(
                'old_code'   => $old_code,
                'code'       => $new_code,
                'old_rate'   => $rate,
            );
            wa('shop')->event('currency_primary', $params);
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
        $product_skus_model = new shopProductSkusModel();
        foreach ($price_fields as $p_name) {
            $allowed_number = $this->getAllowedMaxNumber($product_skus_model, $p_name);
            $sql = "UPDATE `shop_product_skus` ps
                JOIN `shop_product` p ON p.id = ps.product_id
                SET ps.{$p_name} = LEAST((ps.{$p_name}*{$rate_from})/{$rate_to}, $allowed_number)
                WHERE {$cond}";
            $this->exec($sql);
        }
    }

    private function convertServicePrices($from, $rate_from, $to, $rate_to)
    {
        $rate_from = $this->castValue('double', $rate_from);
        $rate_to = $this->castValue('double', $rate_to);

        $service_variants_model = new shopServiceVariantsModel();
        $allowed_service_variants_number = $this->getAllowedMaxNumber($service_variants_model, 'price');
        $cond = "s.currency = '".$this->escape($from)."'";
        $sql = "UPDATE `shop_service_variants` sv
                JOIN `shop_service` s ON s.id = sv.service_id
                SET sv.price = LEAST((sv.price*$rate_from)/$rate_to, $allowed_service_variants_number)
                WHERE sv.price IS NOT NULL AND $cond";
        $this->exec($sql);

        $product_services_model = new shopProductServicesModel();
        $allowed_product_services_number = $this->getAllowedMaxNumber($product_services_model, 'price');
        $sql = "UPDATE `shop_product_services` ps
                JOIN `shop_service` s ON s.id = ps.service_id
                SET ps.price = LEAST((ps.price*$rate_from)/$rate_to, $allowed_product_services_number)
                WHERE ps.price IS NOT NULL AND $cond";
        $this->exec($sql);

        $service_model = new shopServiceModel();
        $allowed_service_number = $this->getAllowedMaxNumber($service_model, 'price');
        $sql = "UPDATE `shop_service` s
                SET s.price = LEAST((price*$rate_from)/$rate_to, $allowed_service_number), s.currency = '".$this->escape($to)."'
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
        $rounding = self::getRounding($currencies[$code]);
        $data = array(
            'code'     => $code,
            'sort'     => $sort,
            'rounding' => $rounding,
        );
        $result = $this->insert($data);
        $this->deleteCache();
        return $result ? $data : $result;
    }

    public function deleteCache()
    {
        $cache = wa('shop')->getCache();
        if ($cache) {
            $cache->deleteGroup('sets');
            foreach(waLocale::getAll() as $locale) {
                $cache->delete('currencies_'.$locale);
            }
        }
        $cache = new waRuntimeCache('shop_currencies');
        $cache->delete();
    }

    /**
     * Change rate of currency
     *
     * @param string $code
     * @param double $rate
     * @return bool|null|waDbResultUpdate
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

            /**
             * @event currency_change
             * @param string [string]mixed $params
             * @param string [string]string $params['code'] Currency code changed
             * @param string [string]string $params['old_rate'] Old currency rate relative to primary currency
             * @param string [string]string $params['new_rate'] New currency rate relative to primary currency
             * @return void
             */
            $params = array(
                'code'       => $code,
                'old_rate'   => $old_rate,
                'new_rate'   => $rate,
            );
            wa('shop')->event('currency_change', $params);

            return $result;
        }
        return true;
    }

    /**
     *
     * Recalculate all "primary_price" fields of products
     *
     * @param string|null $code Affecting to products with this currency code or to all products in case null
     */
    private function recalcProductPrimaryPrices($code = null)
    {
        $where = $code ? "WHERE p.currency = '".$this->escape($code)."'" : '';
        $product_model = new shopProductModel();
        $allowed_product_min_price_number = $this->getAllowedMaxNumber($product_model, 'min_price');
        $allowed_product_max_price_number = $this->getAllowedMaxNumber($product_model, 'max_price');
        $sql = "UPDATE `shop_product` p JOIN
            (
                SELECT p.id, MIN(ps.price) AS min_price, MAX(ps.price) AS max_price
                FROM `shop_product` p
                JOIN `shop_product_skus` ps ON ps.product_id = p.id
                GROUP BY p.id
            ) r ON p.id = r.id
            JOIN `shop_currency` c ON c.code = p.currency
            SET p.min_price = LEAST(r.min_price*c.rate, $allowed_product_min_price_number), p.max_price = LEAST(r.max_price*c.rate, $allowed_product_max_price_number)
            $where
        ";
        $this->exec($sql);

        $allowed_product_price_number = $this->getAllowedMaxNumber($product_model, 'price');
        $sql = "UPDATE `shop_product` p
            JOIN `shop_product_skus` ps ON ps.product_id = p.id AND ps.id = p.sku_id
            JOIN `shop_currency` c ON c.code = p.currency
            SET p.price = LEAST(ps.price*c.rate, $allowed_product_price_number)
            $where";
        $this->exec($sql);

        $allowed_product_compare_price_number = $this->getAllowedMaxNumber($product_model, 'compare_price');
        $sql = "UPDATE `shop_product` p
            JOIN `shop_product_skus` ps ON ps.product_id = p.id AND ps.id = p.sku_id
            JOIN `shop_currency` c ON c.code = p.currency
            SET p.compare_price = LEAST(ps.compare_price*c.rate, $allowed_product_compare_price_number)
            $where";
        $this->exec($sql);

        $product_skus_model = new shopProductSkusModel();
        $allowed_product_skus_number = $this->getAllowedMaxNumber($product_skus_model, 'primary_price');
        $sql = "UPDATE `shop_product` p
                JOIN `shop_product_skus` ps ON p.id = ps.product_id
                JOIN `shop_currency` c ON c.code = p.currency
                SET ps.primary_price = LEAST(ps.price*c.rate, $allowed_product_skus_number)
                $where";
        $this->exec($sql);
    }

    /**
     * Recalculate all "primary_price" fields of services
     *
     * @param string|null $code Affecting to services with this currency code or to all services in case null
     */
    private function recalcServicePrimaryPrices($code = null)
    {
        $where = $code ? "WHERE s.currency = '".$this->escape($code)."'" : '';

        $service_variants_model = new shopServiceVariantsModel();
        $allowed_service_variants_number = $this->getAllowedMaxNumber($service_variants_model, 'primary_price');
        $sql = "UPDATE `shop_service_variants` sv
                JOIN `shop_service` s ON s.id = sv.service_id
                JOIN `shop_currency` c ON c.code = s.currency
                SET sv.primary_price = LEAST(sv.price*c.rate, $allowed_service_variants_number)
                $where";
        $this->exec($sql);

        $product_services_model = new shopProductServicesModel();
        $allowed_product_services_number = $this->getAllowedMaxNumber($product_services_model, 'primary_price');
        $sql = "UPDATE `shop_product_services` ps
                JOIN `shop_service` s ON s.id   = ps.service_id
                JOIN `shop_currency` c ON c.code = s.currency
                SET ps.primary_price = LEAST(ps.price*c.rate, $allowed_product_services_number)
                $where";
        $this->exec($sql);

        $service_model = new shopServiceModel();
        $allowed_service_number = $this->getAllowedMaxNumber($service_model, 'price');
        $sql = "UPDATE `shop_service` s
                JOIN `shop_service_variants` sv ON s.variant_id = sv.id
                SET s.price = LEAST(sv.primary_price, $allowed_service_number)
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

    public static function getRounding($currency)
    {
        $rounding = null;
        if (isset($currency['precision']) && wa_is_int($currency['precision']) && $currency['precision'] > 0 && $currency['precision'] <= 4) {
            return pow(10, -$currency['precision']);
        }
        return $rounding;
    }

    /**
     * @param $model
     * @param string $column_name
     * @return string
     */
    protected function getAllowedMaxNumber($model, $column_name)
    {
        $metadata = $model->getMetadata();
        if (isset($metadata[$column_name])) {
            $params = explode(',', $metadata[$column_name]['params']);
            $allowed_number = str_repeat('9', $params[0]);
            if (isset($params[1])) {
                $allowed_number .= '.' . str_repeat('9', $params[1]);
            }
            return $allowed_number;
        } else {
            return '0';
        }
    }
}
