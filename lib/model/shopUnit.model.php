<?php
/**
 * Measurement units
 */
class shopUnitModel extends waModel
{
    protected $table = 'shop_unit';

    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;
    const STATUS_ENABLED_AND_LOCKED = 2;

    public function getAll($key = null, $normalize = false)
    {
        $piece  = self::getPc();
        $result = $this->order('sort')->fetchAll($key, $normalize);
        if ($key) {
            $result = [ifset($piece, $key, 0) => $piece] + $result;
        } else {
            array_unshift($result, $piece);
        }
        return $result;
    }

    /**
     * @param string $key
     * @return array|array[]
     * @throws waException
     */
    public function getAllEnabled($key = null)
    {
        $piece  = self::getPc();
        $result = $this->where('status = 1')->order('sort')->fetchAll($key);
        if ($key) {
            $result = [ifset($piece, $key, 0) => $piece] + $result;
        } else {
            array_unshift($result, $piece);
        }
        return $result;
    }

    public function getAllCustom($key = null, $normalize = false)
    {
        return parent::getAll($key, $normalize);
    }

    /**
     * @param $unit_id
     * @param $status
     * @return mixed
     */
    public function changeStatus($unit_id, $status)
    {
        $unit = $this->getById($unit_id);
        if (!$unit) {
            return false;
        }
        if ($status == $unit['status']) {
            return $unit;
        }
        if ($status != self::STATUS_DISABLED && $status != self::STATUS_ENABLED && $status != self::STATUS_ENABLED_AND_LOCKED) {
            return false;
        }

        if ($status === 0 || $status === '0') {
            $this->updateById($unit_id, ['sort' => 0, 'status' => $status]);
            $unit['sort']   = 0;
            $unit['status'] = $status;
            return $unit;
        } elseif (!empty($status)) {
            $maximum = (int) $this->query('SELECT MAX(sort) AS maximum FROM shop_unit WHERE status = 1 ORDER BY sort')->fetchField();
            ++$maximum;
            $this->updateById($unit_id, ['sort' => $maximum, 'status' => $status]);
            $unit['sort']   = $maximum;
            $unit['status'] = $status;
            return $unit;
        } else {
            return false;
        }
    }

    public function setSortOrder(array $unit_ids)
    {
        $sort = 1;
        foreach($unit_ids as $unit_id) {
            $this->updateById($unit_id, ['sort' => $sort]);
            $sort++;
        }
    }

    public static function getPc()
    {
        return [
            'id'              => '0',
            'short_name'      => _w('pc.'),
            'name'            => _w('Piece'),
            'name2'           => _w('piece', 'pieces', 2),
            'name5'           => _w('piece', 'pieces', 5),
            'okei_code'       => '796',
            'builtin'         => '2',
            'storefront_name' => _w('pcs.'),
            'sort'            => '-1',
            'status'          => '2'
        ];
    }

    /**
     * @return array
     * @throws waDbException
     */
    public function getUsedUnit()
    {
        $units_used_types = $this->query('SELECT DISTINCT stock_unit_id FROM shop_type')->fetchAll();
        $units_used_prod  = $this->query('SELECT DISTINCT stock_unit_id, base_unit_id FROM shop_product')->fetchAll();

        return array_unique(array_merge(
            array_column($units_used_types, 'stock_unit_id'),
            array_column($units_used_prod, 'stock_unit_id'),
            array_column($units_used_prod, 'base_unit_id')
        ));
    }
}
