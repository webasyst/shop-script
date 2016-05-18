<?php
class shopStockRulesModel extends waModel
{
    protected $table = 'shop_stock_rules';

    public function getRules()
    {
        return $this->order('sort')->fetchAll('id');
    }

    public static function prepareRuleGroups($rules)
    {
        $current_group = array();
        $rule_groups = array();
        foreach($rules as $rule) {
            $current_group[] = $rule;
            if ($rule['stock_id'] || $rule['virtualstock_id']) {
                $rule_groups[] = array(
                    'conditions' => $current_group,
                    'stock_id' => $rule['stock_id'],
                    'virtualstock_id' => $rule['virtualstock_id'],
                    'parent_stock_id' => $rule['virtualstock_id'] ? 'v'.$rule['virtualstock_id'] : $rule['stock_id'],
                    'substock_id' => $rule['virtualstock_id'] ? $rule['stock_id'] : null,
                );
                $current_group = array();
            }
        }
        return $rule_groups;
    }

}
