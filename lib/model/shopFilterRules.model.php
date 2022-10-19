<?php

class shopFilterRulesModel extends waModel
{
    protected $table = 'shop_filter_rules';

    // it has a minimum element
    const OPEN_INTERVAL_LEFT_CLOSED = 0;
    // it has a maximum element
    const OPEN_INTERVAL_RIGHT_CLOSED = 1;

    /**
     * @param array $filters
     * @return mixed
     * @throws waException
     */
    public function fillFilterRules($filters)
    {
        if (is_array($filters)) {
            $rules = $this->where("`filter_id` IN (?)", array_column($filters, 'id'))->order("`rule_group`, `id`")->fetchAll('id');
            foreach ($filters as &$filter) {
                if (!isset($filter['rules'])) {
                    $filter['rules'] = [];
                }
                foreach ($rules as $rule) {
                    if ($rule['filter_id'] == $filter['id']) {
                        $filter['rules'][] = $rule;
                    }
                }
            }
            unset($filter);
        }

        return $filters;
    }

    /**
     * @param int $filter_id
     * @return array
     */
    public function getByFilterId($filter_id)
    {
        return $this->where("`filter_id` = ?", (int)$filter_id)->order("`rule_group`, `id`")->fetchAll('id');
    }

    /**
     * @param int $filter_id
     * @param int $deleted_rule_group
     * @return void
     */
    public function correctSortAfterDelete($filter_id, $deleted_rule_group)
    {
        $sql = "UPDATE shop_filter_rules 
            SET `rule_group` = `rule_group` - 1
            WHERE `filter_id` = ? AND `rule_group` > ?";
        $this->exec($sql, (int)$filter_id, (int)$deleted_rule_group);
    }

    /**
     * @param int $filter_id
     * @param bool $delete_search
     * @return bool|resource
     */
    public function deleteRules($filter_id, $delete_search = true)
    {
        $sql = "DELETE FROM " . $this->table . "
                WHERE `filter_id` = ?";
        if (!$delete_search) {
            $sql .= " AND `rule_type` != 'search'";
        }
        return $this->exec($sql, (int)$filter_id);
    }

    /**
     * @param int $source_id
     * @param int $destination_id
     * @param bool $copy_search
     * @return bool|resource
     */
    public function copyRules($source_id, $destination_id, $copy_search = true)
    {
        $rules = shopFilter::getAllTypes(true);
        if (!$copy_search) {
            unset($rules['search']);
        }
        $sql = 'INSERT INTO `'.$this->table.'` (`filter_id`, `rule_type`, `rule_params`, `rule_group`, `open_interval`)
                SELECT ?, `rule_type`, `rule_params`, `rule_group`, `open_interval`
                FROM `'.$this->table.'`
                WHERE `filter_id` = ? AND `rule_type` IN("' . implode('","', array_keys($rules)) . '")
                ORDER BY `id` ASC';
        $this->exec($sql, (int)$destination_id, (int)$source_id);
        $update_group_sql = "UPDATE `".$this->table."` fr, (
                                SELECT MAX(`rule_group`) + 1 AS `max_rule_group`
                                FROM `".$this->table."`
                                WHERE `filter_id` = i:fid
                                GROUP BY `rule_group`
                            ) subfr
                            SET fr.rule_group = subfr.max_rule_group
                            WHERE fr.filter_id = i:fid AND fr.rule_type = 'search'";
        return $this->exec($update_group_sql, ['fid' => $destination_id]);
    }
}
