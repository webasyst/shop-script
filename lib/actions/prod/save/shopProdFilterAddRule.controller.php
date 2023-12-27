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
        $presentation_id = waRequest::post('presentation_id', null, waRequest::TYPE_INT);

        $new_presentation_id = shopProdPresentationEditColumnsController::duplicatePresentation($presentation_id);
        if ($new_presentation_id) {
            $this->response['new_presentation_id'] = $new_presentation_id;
            $presentation_model = new shopPresentationModel();
            $filter_id = $presentation_model->select('filter_id')->where('`id` = ?', $new_presentation_id)->fetchField('filter_id');
            $presentation_id = $new_presentation_id;
        }

        $open_interval = null;
        $filter_options = shopFilter::getAllTypes();
        $filter_model = new shopFilterModel();
        $filter = $filter_model->getById($filter_id);
        shopFilter::shopProdFiltersEvent(ref(null), $filter_options);
        $types = shopFilter::flattenTypes($filter_options);

        $this->validateData($types, $filter, $rule_type, $rule_params, $open_interval);
        if (!$this->errors) {
            $this->filter_rules_model = new shopFilterRulesModel();
            $rules = $this->filter_rules_model->getByFilterId($filter_id);
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
                        break;
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

            if ($rule_type == 'search') {
                $this->resetSort($presentation_id);
            }
        }
    }

    /**
     * @param array $types
     * @param array $filter_id
     * @param string $rule_type
     * @param array $rule_params
     * @param null $open_interval
     * @return void
     */
    protected function validateData($types, $filter, $rule_type, &$rule_params, &$open_interval)
    {
        if (!$filter) {
            $this->errors = [
                'id' => 'filter_id',
                'text' => _w('Filter not found.'),
            ];
            return;
        }

        if (!isset($types[$rule_type])) {
            $this->errors = [
                'id' => 'rule_type',
                'text' => _w('Incorrect rule type.'),
            ];
            return;
        }

        $unit = null;
        if (isset($rule_params['start'])) {
            if (isset($rule_params['unit'])) {
                if (!mb_strlen($rule_params['unit'])) {
                    $this->errors = [
                        'id' => 'open_interval',
                        'text' => _w('Incorrect range limits.'),
                    ];
                    return;
                } else {
                    $unit = $rule_params['unit'];
                    unset($rule_params['unit']);
                }
            }
            if (!mb_strlen($rule_params['start']) && mb_strlen($rule_params['end'])) {
                $open_interval = shopFilterRulesModel::OPEN_INTERVAL_RIGHT_CLOSED;
                unset($rule_params['start']);
            } elseif (mb_strlen($rule_params['start']) && !mb_strlen($rule_params['end'])) {
                $open_interval = shopFilterRulesModel::OPEN_INTERVAL_LEFT_CLOSED;
                unset($rule_params['end']);
            }
            $rule_params = array_values($rule_params);
        }

        $validated_params = shopFilter::validateValue($rule_params, $rule_type, $types[$rule_type], $unit);
        if (empty($validated_params)) {
            $this->errors = [
                'id' => 'rule_params',
                'text' => _w('Incorrect rule parameters.'),
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
        $this->filter_rules_model->deleteByField([
            'filter_id' => $filter_id,
            'rule_type' => $rule['rule_type'],
        ]);
        $this->filter_rules_model->correctSortAfterDelete($filter_id, $rule['rule_group']);
        $count_deleted_rules++;
    }

    /**
     * @param int $presentation_id
     * @return void
     */
    protected function resetSort($presentation_id)
    {
        $presentation_model = new shopPresentationModel();
        $presentation_model->updateById($presentation_id, ['sort_column_id' => null]);
    }
}
