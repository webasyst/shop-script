<?php

class shopSearchIndexCli extends waCliController
{
    public function execute()
    {
        $search = new shopIndexSearch();
        if (waRequest::param(0)) {
            $search->indexProduct(waRequest::param(0));
        } else {
            $product_model = new shopProductModel();
            $n = $product_model->countAll();
            $limit = 100;
            $i = 0;
            $product_model->exec("TRUNCATE TABLE shop_search_index");

            while ($i < $n) {
                echo $i."/".$n."\n";
                $sql = "SELECT * FROM ".$product_model->getTableName()." LIMIT ".$i.", ".$limit;
                $products = $product_model->query($sql)->fetchAll('id');
                $product_ids = array_keys($products);
                // get tags
                $sql = "SELECT pt.product_id, t.name FROM shop_product_tags pt
                JOIN shop_tag t ON pt.tag_id = t.id WHERE pt.product_id IN (i:id)";
                $data = $product_model->query($sql, array('id' => $product_ids));
                foreach ($data as $row) {
                    $products[$row['product_id']]['tags'][] = $row['name'];
                }
                // get features
                $sql = "SELECT pf.product_id, f.value FROM shop_product_features pf
                JOIN shop_feature_values f ON pf.feature_value_id = f.id WHERE pf.product_id IN (i:id)";
                $data = $product_model->query($sql, array('id' => $product_ids));
                foreach ($data as $row) {
                    $products[$row['product_id']]['features'][] = $row['value'];
                }
                // get skus
                $sql = "SELECT * FROM shop_product_skus WHERE product_id IN (i:id)";
                $data = $product_model->query($sql, array('id' => $product_ids));
                foreach ($data as $row) {
                    $products[$row['product_id']]['skus'][] = $row;
                }
                foreach ($products as $p) {
                    $search->indexProduct($p, false);
                }
                $i += $limit;
            }
        }
    }
}