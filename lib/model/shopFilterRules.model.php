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
     * @return bool|resource
     * @throws waException
     */
    public function deleteAllRules($filter_id)
    {
        $sql = "DELETE FROM ".$this->table." WHERE `rule_type` != 'search' AND `filter_id` = " . (int)$filter_id;
        return $this->exec($sql);
    }
}
