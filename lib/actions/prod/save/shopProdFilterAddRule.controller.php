<?php
/**
 * Add filter rule
 */
class shopProdFilterAddRuleController extends waJsonController
{
    /**
     * @var shopFilterRulesModel $filter_rules_model
     */
    protected $filter_rules_model;

    public function execute()
    {
        $filter_id = waRequest::post('filter_id', null, waRequest::TYPE_INT);
        $rule_type = waRequest::post('rule_type', null, waRequest::TYPE_STRING_TRIM);
        $rule_params = waRequest::post('rule_params', [], waRequest::TYPE_ARRAY);
        $open_interval = waRequest::post('open_interval');
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);

        $new_presentation_id = shopProdPresentationEditColumnsController::duplicatePresentation($presentation_id, false);
        if ($new_presentation_id) {
            $this->response['new_presentation_id'] = $new_presentation_id;
            $presentation_model = new shopPresentationModel();
            $filter_id = $presentation_model->select('filter_id')->where('`id` = ?', $new_presentation_id)->fetchField('filter_id');
        }

        $types = shopFilter::getAllTypes(true);
        $filter_model = new shopFilterModel();
        $filter = $filter_model->getById($filter_id);
        $this->validateData($types, $filter, $rule_type, $rule_params, $open_interval);
        if (!$this->errors) {
            $this->filter_rules_model = new shopFilterRulesModel();
            $rules = $this->filter_rules_model->getByField('filter_id', $filter_id, 'id');
            $single_filter_keys = ['categories', 'sets', 'types'];
            $replaces_previous = [];
            foreach ($types as $type => $params) {
                if ($params['replaces_previous'] && !in_array($type, $single_filter_keys)) {
                    $replaces_previous[] = $type;
                }
            }
            $new_rule_group = 0;
            if ($rules) {
                $new_rule_group = max(array_column($rules, 'rule_group'));
            }
            $count_deleted_rules = 0;
            if (in_array($rule_type, $single_filter_keys)) {
                foreach ($rules as $rule) {
                    if (in_array($rule['rule_type'], $single_filter_keys)) {
                        $this->deleteFilterRule($filter_id, $rule, $count_deleted_rules);
                    }
                }
            } elseif (in_array($rule_type, $replaces_previous)) {
                foreach ($rules as $rule) {
                    if ($rule['rule_type'] == $rule_type) {
                        $this->deleteFilterRule($filter_id, $rule, $count_deleted_rules);
                    }
                }
            }
            if ($count_deleted_rules) {
                $new_rule_group = $new_rule_group - $count_deleted_rules + 1;
            } else {
                $new_rule_group++;
            }
            $new_rule = $this->formatRules($rule_params, $filter_id, $rule_type, $new_rule_group, $open_interval);
            $this->filter_rules_model->multipleInsert($new_rule);
        }
    }

    /**
     * @param array $types
     * @param array $filter_id
     * @param string $rule_type
     * @param array $rule_params
     * @param int|null $open_interval
     * @return void
     */
    protected function validateData($types, $filter, $rule_type, &$rule_params, $open_interval)
    {
        if (!$filter) {
            $this->errors = [
                'id' => 'filter_id',
                'text' => _w('Filter not found'),
            ];
            return;
        }

        if (!isset($types[$rule_type])) {
            $this->errors = [
                'id' => 'rule_type',
                'text' => _w('Incorrect rule type'),
            ];
            return;
        }

        if ($open_interval !== null
            && $open_interval !== shopFilterRulesModel::OPEN_INTERVAL_LEFT_CLOSED
            && $open_interval !== shopFilterRulesModel::OPEN_INTERVAL_RIGHT_CLOSED
        ) {
            $this->errors = [
                'id' => 'open_interval',
                'text' => _w('Range limits set incorrectly'),
            ];
            return;
        }

        $validated_params = shopFilter::validateValue($rule_type, $rule_params, $types[$rule_type]);
        if (empty($validated_params)) {
            $this->errors = [
                'id' => 'rule_params',
                'text' => _w('Incorrect rule params'),
            ];
        } elseif ($validated_params != $rule_params) {
            $rule_params = $validated_params;
        }
    }

    /**
     * @param array $rules
     * @param int $filter_id
     * @param string $rule_type
     * @param int $rule_group
     * @param int|null $open_interval
     * @return array
     */
    protected function formatRules($rules, $filter_id, $rule_type, $rule_group, $open_interval)
    {
        $new_rule = [];
        foreach ($rules as $rule) {
            $new_rule[] = [
                'filter_id' => $filter_id,
                'rule_type' => $rule_type,
                'rule_params' => $rule,
                'rule_group' => $rule_group,
                'open_interval' => $open_interval,
            ];
        }

        return $new_rule;
    }

    protected function deleteFilterRule($filter_id, $rule, &$count_deleted_rules)
    {
        $this->filter_rules_model->deleteById($rule['id']);
        $this->filter_rules_model->correctSortAfterDelete($filter_id, $rule['rule_group']);
        $count_deleted_rules++;
    }
}
