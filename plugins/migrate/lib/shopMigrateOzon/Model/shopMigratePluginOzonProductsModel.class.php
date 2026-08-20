<?php

class shopMigratePluginOzonProductsModel extends shopMigratePluginOzonModel
{
    protected $table = 'shop_migrate_ozon_products';
    const JSON_ENCODE_OPTIONS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function addBatch($snapshot_id, array $products)
    {
        if (!$products) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $rows = array();
        foreach ($products as $product) {
            $rows[] = array(
                'snapshot_id'              => (int) $snapshot_id,
                'product_id'               => (int) ifset($product['product_id']),
                'offer_id'                 => (string) ifset($product['offer_id']),
                'ozon_sku'                 => $this->extractSku($product),
                'description_category_id'  => (int) ifset($product['description_category_id']),
                'type_id'                  => (int) ifset($product['type_id']),
                'model_id'                 => (int) ifset($product['model_id'], 0),
                'model_count'              => (int) ifset($product['model_count'], 0),
                'name'                     => (string) ifset($product['name'], ''),
                'flags'                    => $this->encodeJson(ifset($product['flags'], array())),
                'details'                  => isset($product['details']) ? $this->encodeJson($product['details']) : null,
                'created_at'               => $now,
                'updated_at'               => $now,
            );
        }
        $this->multipleInsert($rows, array(
            'offer_id',
            'ozon_sku',
            'description_category_id',
            'type_id',
            'model_id',
            'model_count',
            'name',
            'flags',
            'details',
            'updated_at',
        ));
    }

    public function updateDetails($snapshot_id, $product_id, array $details)
    {
        $data = array(
            'updated_at' => date('Y-m-d H:i:s'),
            'details'    => $this->encodeJson($details),
        );
        $model_info = ifset($details['model_info'], array());
        $data['model_id'] = (int) ifset($model_info['model_id'], 0);
        $data['model_count'] = (int) ifset($model_info['count'], 0);
        $sku = $this->extractSku($details);
        if ($sku !== null) {
            $data['ozon_sku'] = $sku;
        }
        if (!empty($details['name'])) {
            $data['name'] = (string) $details['name'];
        }
        if (isset($details['description_category_id'])) {
            $data['description_category_id'] = (int) $details['description_category_id'];
        }
        if (isset($details['type_id'])) {
            $data['type_id'] = (int) $details['type_id'];
        }
        $this->updateByField(
            array(
                'snapshot_id' => (int) $snapshot_id,
                'product_id'  => (int) $product_id,
            ),
            $data
        );
    }

    public function getAllBySnapshot($snapshot_id)
    {
        return $this->select('*')
            ->where('snapshot_id = ?', (int) $snapshot_id)
            ->order('product_id ASC')
            ->fetchAll('product_id');
    }

    public function getIds($snapshot_id)
    {
        return $this->select('product_id')
            ->where('snapshot_id = ?', (int) $snapshot_id)
            ->fetchAll(null, true);
    }

    public function getIdsAfter($snapshot_id, $after_product_id, $limit)
    {
        return $this->select('product_id')
            ->where('snapshot_id = ? AND product_id > ?', (int) $snapshot_id, (int) $after_product_id)
            ->order('product_id ASC')
            ->limit(max(1, (int) $limit))
            ->fetchAll(null, true);
    }

    public function getStockRowsAfter($snapshot_id, $after_product_id, $limit)
    {
        return $this->select('product_id, offer_id, ozon_sku')
            ->where('snapshot_id = ? AND product_id > ?', (int) $snapshot_id, (int) $after_product_id)
            ->order('product_id ASC')
            ->limit(max(1, (int) $limit))
            ->fetchAll('product_id');
    }

    public function getCategoryTypePairs($snapshot_id, $offset, $limit)
    {
        $offset = max(0, (int) $offset);
        $limit = max(1, (int) $limit);
        $sql = "
            SELECT description_category_id, type_id, COUNT(*) AS products_count
            FROM {$this->table}
            WHERE snapshot_id = i:snapshot_id
                AND description_category_id > 0
                AND type_id > 0
            GROUP BY description_category_id, type_id
            ORDER BY description_category_id, type_id
            LIMIT {$offset}, {$limit}
        ";
        return $this->query($sql, array('snapshot_id' => (int) $snapshot_id))->fetchAll();
    }

    public function countBySnapshot($snapshot_id)
    {
        return (int) $this->select('COUNT(*)')
            ->where('snapshot_id = ?', (int) $snapshot_id)
            ->fetchField();
    }

    public function countCategoriesBySnapshot($snapshot_id)
    {
        return (int) $this->select('COUNT(DISTINCT description_category_id)')
            ->where('snapshot_id = ? AND description_category_id > 0', (int) $snapshot_id)
            ->fetchField();
    }

    public function countCategoryTypePairs($snapshot_id)
    {
        $sql = "
            SELECT COUNT(*)
            FROM (
                SELECT description_category_id, type_id
                FROM {$this->table}
                WHERE snapshot_id = i:snapshot_id
                    AND description_category_id > 0
                    AND type_id > 0
                GROUP BY description_category_id, type_id
            ) pairs
        ";
        return (int) $this->query($sql, array('snapshot_id' => (int) $snapshot_id))->fetchField();
    }

    public function getImportUnitSummaries($snapshot_id, $offset, $limit)
    {
        $offset = max(0, (int) $offset);
        $limit = max(1, (int) $limit);
        $sql = $this->getImportUnitSummarySql((int) $snapshot_id)."
            LIMIT {$offset}, {$limit}
        ";
        return $this->query($sql)->fetchAll();
    }

    public function countImportUnits($snapshot_id)
    {
        $sql = 'SELECT COUNT(*) FROM ('.$this->getImportUnitSummarySql((int) $snapshot_id).') import_units';
        return (int) $this->query($sql)->fetchField();
    }

    public function countProductsBeforeImportUnitOffset($snapshot_id, $offset)
    {
        $offset = max(0, (int) $offset);
        if ($offset === 0) {
            return 0;
        }
        $sql = 'SELECT COALESCE(SUM(products_count), 0) FROM ('
            .$this->getImportUnitSummarySql((int) $snapshot_id)
            .' LIMIT '.$offset.') processed_units';
        return (int) $this->query($sql)->fetchField();
    }

    public function getProductsForImportUnits($snapshot_id, array $unit_summaries)
    {
        $model_ids = array();
        $product_ids = array();
        foreach ($unit_summaries as $unit) {
            if ((string) ifset($unit['unit_type'], '') === 'group') {
                $model_ids[] = (int) ifset($unit['unit_id']);
            } else {
                $product_ids[] = (int) ifset($unit['unit_id']);
            }
        }

        $where = array();
        $params = array((int) $snapshot_id);
        if ($model_ids) {
            $where[] = '(model_count > 1 AND model_id IN ('.implode(',', array_fill(0, count($model_ids), '?')).'))';
            $params = array_merge($params, $model_ids);
        }
        if ($product_ids) {
            $where[] = 'product_id IN ('.implode(',', array_fill(0, count($product_ids), '?')).')';
            $params = array_merge($params, $product_ids);
        }
        if (!$where) {
            return array();
        }

        $sql = "SELECT * FROM {$this->table} WHERE snapshot_id = ? AND (".implode(' OR ', $where).') ORDER BY product_id';
        return $this->query($sql, $params)->fetchAll('product_id');
    }

    private function getImportUnitSummarySql($snapshot_id)
    {
        return "
            SELECT unit_type, unit_id, MIN(product_id) AS first_product_id, COUNT(*) AS products_count
            FROM (
                SELECT product_id,
                    IF(model_id > 0 AND model_count > 1, 'group', 'single') AS unit_type,
                    IF(model_id > 0 AND model_count > 1, model_id, product_id) AS unit_id
                FROM {$this->table}
                WHERE snapshot_id = ".(int) $snapshot_id."
            ) product_units
            GROUP BY unit_type, unit_id
            ORDER BY first_product_id
        ";
    }

    private function extractSku(array $data)
    {
        if (isset($data['ozon_sku']) && $data['ozon_sku'] !== '') {
            return (string) $data['ozon_sku'];
        }
        if (isset($data['sku']) && $data['sku'] !== '') {
            return (string) $data['sku'];
        }
        foreach ((array) ifset($data['sources'], array()) as $source) {
            if (isset($source['sku']) && $source['sku'] !== '') {
                return (string) $source['sku'];
            }
        }
        return null;
    }

    private function encodeJson($value)
    {
        $encoded = json_encode($value, self::JSON_ENCODE_OPTIONS);
        return $encoded === false ? null : $encoded;
    }
}
