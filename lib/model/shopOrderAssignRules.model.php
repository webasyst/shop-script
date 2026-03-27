<?php

class shopOrderAssignRulesModel extends waModel
{
    protected $table = 'shop_order_assign_rules';

    public function getRules($ids = null)
    {
        if (empty($ids)) {
            $rules = $this->order('sort, id DESC')->fetchAll('id');
        } else {
            $ids = waUtils::toIntArray($ids);
            $rules = $this->where('id', $ids)->order('sort, id DESC')->fetchAll('id');
        }

        return $this->ruleDecode($rules);
    }

    private function ruleEncode($rules = [])
    {
        foreach ($rules as &$rule) {
            if (isset($rule['conditions'])) {
                $rule['conditions'] = json_encode($rule['conditions']);
            }
            if (isset($rule['rule_data'])) {
                $rule['rule_data'] = json_encode($rule['rule_data']);
            }
        }
        unset($rule);

        return $rules;
    }

    private function ruleDecode($rules = [])
    {
        foreach ($rules as &$rule) {
            if (!empty($rule['conditions'])) {
                $rule['conditions'] = json_decode($rule['conditions'], true);
            }
            if (!empty($rule['rule_data'])) {
                $rule['rule_data'] = json_decode($rule['rule_data'], true);
            }
        }
        unset($rule);

        return $rules;
    }

    public function getByField($field, $value = null, $all = false, $limit = false)
    {
        $dbq = $this->select('*')->where($this->getWhereByField($field, $value))->order('sort, id DESC');
        if (is_array($field)) {
            $all = $value;
        }

        if ($all) {
            return $this->ruleDecode($dbq->fetchAll());
        }
        $result = $this->ruleDecode([$dbq->fetchAssoc()]);

        return reset($result);
    }

    public function updateById($id, $data, $options = null, $return_object = false)
    {
        $data = $this->ruleEncode([$data]);
        $data = reset($data);
        return parent::updateById($id, $data, $options, $return_object);
    }

    public function updateByField($field, $value, $data = null, $options = null, $return_object = false)
    {
        return parent::updateByField($field, $value, $data, $options, $return_object);
    }

    public function insert($data, $type = 0)
    {
        $data = $this->ruleEncode([$data]);

        return parent::insert(reset($data), $type);
    }

    public function multipleInsert($data)
    {
        $data = $this->ruleEncode($data);

        return parent::multipleInsert($data);
    }
}
