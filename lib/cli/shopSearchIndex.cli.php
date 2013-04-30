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
                $sql = "SELECT p.*, t.name type_name FROM ".$product_model->getTableName()." p
                LEFT JOIN shop_type t ON p.type_id = t.id
                LIMIT ".$i.", ".$limit;
                $products = $product_model->query($sql)->fetchAll('id');
                $product_ids = array_keys($products);
                // get skus
                $sql = "SELECT * FROM shop_product_skus WHERE product_id IN (i:id)";
                $data = $product_model->query($sql, array('id' => $product_ids));
                foreach ($data as $row) {
                    $products[$row['product_id']]['skus'][] = $row;
                }
                // get tags
                $sql = "SELECT pt.product_id, t.name FROM shop_product_tags pt
                JOIN shop_tag t ON pt.tag_id = t.id WHERE pt.product_id IN (i:id)";
                $data = $product_model->query($sql, array('id' => $product_ids));
                foreach ($data as $row) {
                    $products[$row['product_id']]['tags'][] = $row['name'];
                }
                // get features
                $sql = "SELECT pf.product_id, fv.value FROM shop_product_features pf
                JOIN shop_feature f ON pf.feature_id = f.id AND f.type = 'varchar'
                JOIN shop_feature_values_varchar fv ON pf.feature_value_id = fv.id WHERE pf.product_id IN (i:id)";
                $data = $product_model->query($sql, array('id' => $product_ids));
                foreach ($data as $row) {
                    $products[$row['product_id']]['features'][] = $row['value'];
                }

                $sql = "SELECT pf.product_id, fv.value FROM shop_product_features pf
                JOIN shop_feature f ON pf.feature_id = f.id AND f.type = 'double'
                JOIN shop_feature_values_double fv ON pf.feature_value_id = fv.id WHERE pf.product_id IN (i:id)";
                $data = $product_model->query($sql, array('id' => $product_ids));
                foreach ($data as $row) {
                    $products[$row['product_id']]['features'][] = $row['value'];
                }

                $sql = "SELECT pf.product_id, fv.value FROM shop_product_features pf
                JOIN shop_feature f ON pf.feature_id = f.id AND f.type = 'text'
                JOIN shop_feature_values_text fv ON pf.feature_value_id = fv.id WHERE pf.product_id IN (i:id)";
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