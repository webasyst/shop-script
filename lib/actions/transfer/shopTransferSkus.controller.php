<?php

class shopTransferSkusController extends waController
{
    public function execute()
    {
        $this->render($this->getData());
    }

    public function findSkus($term, $stock_id, $offset = 0, $limit = 10)
    {
        $stock_id = (int) $stock_id;
        $offset = (int) $offset;
        $limit = (int) $limit;

        $term = trim($term);

        $model = new waModel();
        $q = $model->escape($term, 'like');

        $sql_map = array(
            "SELECT" => "s.id, s.name, s.count, p.id AS product_id, p.name AS product_name, p.image_id ",
            "FROM"   => "`shop_product` p ",
            "JOIN"   => array("`shop_product_skus` s ON p.id = s.product_id "),
            'LEFT JOIN' => array(),
            "WHERE"  => "(p.name LIKE ':Q' OR s.name LIKE ':Q' OR s.sku LIKE ':Q') ",
            "LIMIT"  => "{$offset}, {$limit}"
        );

        if ($stock_id) {
            $sql_map['SELECT'] .= ", ps.stock_id, ps.count AS stock_count, st.name AS stock_name";
            $sql_map['LEFT JOIN'][] = "`shop_product_stocks` ps ON ps.sku_id = s.id ";
            $sql_map['LEFT JOIN'][] = "`shop_stock` st ON ps.stock_id = st.id";
            $sql_map['WHERE']  .= "AND ps.stock_id = {$stock_id}";
        }

        $sql = $this->stringifySql($sql_map, array(":Q" => "{$q}%"));
        $skus = $model->query($sql)->fetchAll('id');

        $skus_count = count($skus);
        if ($skus_count < $limit) {
            $sql_map['LIMIT'] = $limit - $skus_count;
            $sql = $this->stringifySql($sql_map, array(":Q" => "%{$q}%"));
            $skus += $model->query($sql)->fetchAll('id');
        }

        $skus_count = count($skus);
        if ($skus_count < $limit) {
            $term_parts = explode(' ', $term);
            if (count($term_parts) > 1) {
                $sq = trim(array_pop($term_parts));
                $sq = trim($sq, '()');      // in case if someone type name of sku in brackets
                $pq = join(' ', $term_parts);
                $pq = trim($pq);

                $sq = $model->escape($sq, 'like');
                $pq = $model->escape($pq, 'like');

                $sql_map["WHERE"] = "(p.name LIKE ':PQ' AND (s.name LIKE ':SQ' OR s.sku LIKE ':SQ'))";
                if ($stock_id) {
                    $sql_map['WHERE']  .= "AND ps.stock_id = {$stock_id}";
                }
                $sql_map['LIMIT'] = $limit - $skus_count;

                $sql = $this->stringifySql($sql_map, array(":PQ" => "{$pq}%", ":SQ" => "{$sq}%"));
                $skus += $model->query($sql)->fetchAll('id');

                $skus_count = count($skus);
                if ($skus_count < $limit) {
                    $sql_map['LIMIT'] = $limit - $skus_count;

                    $sql = $this->stringifySql($sql_map, array(":PQ" => "%{$pq}%", ":SQ" => "%{$sq}%"));

                    $skus += $model->query($sql)->fetchAll('id');
                }
            }
        }

        $skus = self::workupSkus($skus);
        return array_values($skus);
    }

    private function stringifySql($sql_map, $substitute = array())
    {
        $sql = "";
        foreach ($sql_map as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $v) {
                    if ($v) {
                        $sql .= $key . ' ' . $v . ' ';
                    }
                }
            } else {
                if ($val) {
                    $sql .= $key . ' ' . $val . ' ';
                }
            }
        }
        foreach ($substitute as $sub => $repl) {
            $sql = str_replace($sub, $repl, $sql);
        }
        return $sql;
    }

    public static function workupSkus($skus)
    {
        $image_ids = array();
        foreach($skus as $sku) {
            $image_ids[] = $sku['image_id'];
        }
        $image_ids = array_unique($image_ids);

        $pim = new shopProductImagesModel();
        $images = $pim->getByField('id', $image_ids, 'product_id');
        $size = wa('shop')->getConfig()->getImageSize('crop_small');

        foreach ($skus as &$sku) {

            $fields = ifset($fields, array('product_name', 'name', 'sku'));
            foreach ($fields as $field) {
                if (isset($sku[$field])) {
                    $sku[$field . '_e'] = self::e($sku[$field]);
                }
            }

            $stock_message = ' ';
            $sku['disabled'] = false;
            if (isset($sku['stock_count'])) {
                $stock_id = ifset($sku['stock_id']);
                $stock_count = $sku['stock_count'];
                if ($stock_count <= 0) {
                    $sku['disabled'] = true;
                    $stock_name = ifset($sku['stock_name'], '');
                    $stock_name = $stock_name ? $stock_name : ifset($sku['stock_id'], '');
                    $stock_message = ' <i class="icon10 status-red"></i><span class="small s-stock-warning-none">' . sprintf(_w('Not in stock on %s'), $stock_name) . '</span> ';
                } else {
                    $stock_message = ' ' . shopHelper::getStockCountIcon($stock_count, $stock_id, true) . ' ';
                }
            }

            $wrapp_class = $sku['disabled'] ? 's-sku-disabled' : '';
            $sku['label'] = "<span class='{$wrapp_class}'>{$sku['product_name_e']} <span class='hint'>{$sku['name_e']}</span> {$stock_message}</span>";

            $name = $sku['product_name'] . ($sku['name'] ? ' (' . $sku['name'] . ')' : '');
            $sku['value'] = $name;

            $sku['image_url'] = null;
            if (isset($images[$sku['product_id']])) {
                $sku['image_url'] = shopImage::getUrl($images[$sku['product_id']], $size);
            }
        }
        unset($sku);

        return $skus;
    }

    public function getData()
    {
        return $this->findSkus(
            $this->getRequest()->get('term'),
            $this->getRequest()->get('stock_id')
        );
    }

    public function render($data)
    {
        die(json_encode($data));
    }

    private static function e($v)
    {
        return htmlspecialchars($v);
    }
}
