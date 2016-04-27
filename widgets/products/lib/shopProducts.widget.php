<?php

class shopProductsWidget extends waWidget
{
    const LIMIT = 12;

    public function defaultAction()
    {
        $settings = $this->getSettings();

        switch($settings['list_type']) {
            case 'last_sold':
                $products = self::getLastSold();
                break;
            case 'stock':
                $products = self::getStock();
                break;
            case 'low_stock':
                $products = self::getLowStock();
                break;
            case 'out_of_stock':
                $products = self::getLastSold(true);
                break;
            default: // bestsellers
                $days = substr($settings['list_type'], strlen('bestsellers'));
                $products = self::getBestsellers((int)$days);
                break;
        }

        $this->display(array(
            'widget_id' => $this->id,
            'products' => $products,
            'title' => $this->getTitle($settings['list_type']),
        ));
    }

    protected function getTitle($list_type)
    {
        $config = $this->getSettingsConfig();
        foreach($config['list_type']['options'] as $item) {
            if ($item['value'] == $list_type) {
                return _wp($item['title']);
            }
        }
        return $list_type;
    }

    protected function getLastSold($only_out_of_stock=false)
    {
        $out_of_stock_sql = '';
        if ($only_out_of_stock) {
            $out_of_stock_sql = 'WHERE p.count <= 0';
        }

        $m = new waModel();
        $sql = "SELECT p.*, o.create_datetime AS last_sold
                FROM shop_product AS p
                    JOIN shop_order_items AS oi
                        ON oi.product_id=p.id
                            AND oi.type='product'
                    JOIN shop_order AS o
                        ON o.id=oi.order_id
                {$out_of_stock_sql}
                ORDER BY oi.id DESC
                LIMIT ".(self::LIMIT * 3);
        $time = time();
        $result = array();
        foreach($m->query($sql) as $row) {
            if (!empty($result[$row['id']])) {
                continue;
            }
            $delta = $time - strtotime($row['last_sold']);
            $result[$row['id']] = $row + array(
                'metric' => self::getTimeInterval($delta),
                'delta' => $delta,
            );
            if (count($result) >= self::LIMIT) {
                break;
            }
        }

        // Sort in PHP because create_datetime does not necessarily come in the same order as o.id
        // for stub orders created via demoutil plugin.
        usort($result, create_function('$a,$b', 'return $a["delta"] > $b["delta"] ? 1 : ($a["delta"] < $b["delta"] ? -1 : 0);'));

        return $result;
    }

    public static function getTimeInterval($delta)
    {
        if ($delta < 5*60) {
            return _wp('Just now');
        } else if ($delta < 60*60) {
            return _wp('%d min ago', '%d mins ago', round($delta / 60));
        } else if ($delta < 24*60*60) {
            return _wp('%d hr ago', '%d hrs ago', round($delta /60/60));
        } else {
            return trim(str_replace(date('Y'), '', wa_date('humandate', time() - $delta)));
        }
    }

    protected function getStock()
    {
        $m = new waModel();
        $sql = "SELECT p.*, SUM(s.price*c.rate*IF(s.count > 0, s.count, 0)) AS metric
                FROM shop_product AS p
                    JOIN shop_product_skus AS s
                        ON s.product_id=p.id
                    JOIN shop_currency AS c
                        ON c.code=p.currency
                WHERE s.count > 0
                GROUP BY p.id
                ORDER BY metric DESC
                LIMIT ".self::LIMIT;

        $result = array();
        $primary_currency = wa('shop')->getConfig()->getCurrency(true);
        foreach($m->query($sql) as $row) {
            $row['metric'] = shop_currency_html($row['metric'], $primary_currency, $primary_currency);
            $result[] = $row;
        }

        return $result;
    }

    protected function getLowStock()
    {
        $time_threshold = time() - 90*24*3600;
        $date_start = date('Y-m-d', $time_threshold);
        $date_end = date('Y-m-d 23:59:59');

        // Get top sales (by number of units sold) for the last 90 days
        $m = new waModel();
        $sql = "SELECT p.*, SUM(oi.quantity) AS sold
                FROM shop_product AS p
                    JOIN shop_order_items AS oi
                        ON oi.product_id=p.id
                            AND oi.type='product'
                    JOIN shop_order AS o
                        ON o.id=oi.order_id
                WHERE o.paid_date >= ?
                    AND o.paid_date <= ?
                GROUP BY p.id
                HAVING sold > 0
                ORDER BY sold DESC
                LIMIT 100";
        $products = array();
        foreach($m->query($sql, $date_start, $date_end) as $p) {
            // Normalize number of sales for products created recently
            if (!empty($p['create_datetime'])) {
                $create_ts = strtotime($p['create_datetime']);
                if ($create_ts > $time_threshold) {
                    $days = max(30, (time() - $create_ts) / 24 / 3600);
                    $p['sold'] = $p['sold']*90/$days;
                }
            }
            $p['stock'] = 0;
            $products[$p['id']] = $p;
        }
        if (!$products) {
            return array();
        }

        // Get stock counts and estimate running out of stock
        $sql = "SELECT p.id, SUM(s.count) AS stock
                FROM shop_product AS p
                    JOIN shop_product_skus AS s
                        ON s.product_id=p.id
                WHERE s.count > 0
                    AND p.id IN (?)
                GROUP BY p.id";
        $result = array();
        foreach($m->query($sql, array(array_keys($products))) as $p) {
            $p += $products[$p['id']];
            $p['est'] = 90 * $p['stock'] / $p['sold'];
            $p['metric'] = _wp('%d day', '%d days', round($p['est']));
            $result[] = $p;
        }

        usort($result, create_function('$a, $b', 'return $a["est"] > $b["est"] ? 1 : ($a["est"] < $b["est"] ? -1 : 0);'));

        return array_slice($result, 0, self::LIMIT);
    }

    protected function getBestsellers($days)
    {
        $date_start = date('Y-m-d', time() - $days*24*3600);
        $date_end = date('Y-m-d 23:59:59');

        $m = new waModel();
        $sql = "SELECT p.*, oi.price * oi.quantity * o.rate AS metric
                FROM shop_product AS p
                    JOIN shop_order_items AS oi
                        ON oi.product_id=p.id
                            AND oi.type='product'
                    JOIN shop_order AS o
                        ON o.id=oi.order_id
                WHERE o.paid_date >= ?
                    AND o.paid_date <= ?
                GROUP BY p.id
                ORDER BY metric DESC
                LIMIT ".self::LIMIT;
        $result = array();
        $primary_currency = wa('shop')->getConfig()->getCurrency(true);
        foreach($m->query($sql, $date_start, $date_end) as $row) {
            $row['metric'] = shop_currency_html($row['metric'], $primary_currency, $primary_currency);
            $result[] = $row;
        }
        return $result;
    }
}