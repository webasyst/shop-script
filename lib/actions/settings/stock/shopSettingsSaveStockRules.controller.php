<?php

class shopSettingsSaveStockRulesController extends waJsonController
{
    public function execute()
    {
        $rules_data = self::getRulesData();

        $stock_rules_model = new shopStockRulesModel();
        $existing_rules = $stock_rules_model->getAll('id');

        // delete what's missing
        $deleted_rules = array_diff_key($existing_rules, $rules_data);
        $stock_rules_model->deleteById(array_keys($deleted_rules));

        // create new and update existing rules
        foreach ($rules_data as $id => $rule) {
            if ($id > 0) {
                $stock_rules_model->updateById($id, $rule);
            } else {
                $stock_rules_model->insert($rule);
            }
        }

        $this->response = 'ok';
    }

    protected static function getRulesData()
    {
        $i = 0;
        $rules_data = array();
        $stocks = shopHelper::getStocks();
        foreach (waRequest::post('rules', array(), 'array') as $id => $rule) {
            if (!is_array($rule) || empty($rule['rule_type']) || !is_string($rule['rule_type'])) {
                continue;
            }
            if (empty($rule['rule_data']) || !is_string($rule['rule_data'])) {
                $rule['rule_data'] = '';
            }

            list($virtualstock_id, $stock_id) = self::getStockIds($rule, $stocks);
            $rules_data[$id] = array(
                'rule_type'       => $rule['rule_type'],
                'rule_data'       => $rule['rule_data'],
                'virtualstock_id' => $virtualstock_id,
                'stock_id'        => $stock_id,
                'sort'            => $i,
            );
            $i++;
        }
        return $rules_data;
    }

    protected static function getStockIds($rule, $stocks)
    {
        $virtualstock_id = $stock_id = null;

        $parent_stock_id = ifset($rule['parent_stock_id']);
        if ($parent_stock_id && !isset($stocks[$parent_stock_id])) {
            $parent_stock_id = null;
        }

        if ($parent_stock_id) {
            if (isset($stocks[$parent_stock_id]['substocks'])) {
                $substock_id = ifset($rule['substock_id']);
                if (!$substock_id || !in_array($substock_id, $stocks[$parent_stock_id]['substocks'])) {
                    $substock_id = null;
                }

                $virtualstock_id = $stocks[$parent_stock_id]['id'];
                $stock_id = $substock_id;
            } else {
                $virtualstock_id = null;
                $stock_id = $parent_stock_id;
            }
        }

        return array($virtualstock_id, $stock_id);
    }
}
