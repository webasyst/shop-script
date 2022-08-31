<?php

class shopFilterModel extends waModel
{
    protected $table = 'shop_filter';

    const DUPLICATE_MODE_TEMPLATE = 'template';
    const DUPLICATE_MODE_FILTER = 'filter';
    // from template to filter
    const DUPLICATE_MODE_TRANSIENT = 'transient';
    // from filter to template
    const DUPLICATE_MODE_CREATE = 'create';

    /**
     * @var shopFilterRulesModel
     */
    protected $rules_model = null;

    protected function rulesModel()
    {
        if (empty($this->rules_model)) {
            $this->rules_model = new shopFilterRulesModel();
        }
        return $this->rules_model;
    }

    /**
     * @param array $filters
     * @param array $options
     * @return array|mixed
     * @throws waException
     */
    public function workup($filters, $options = [])
    {
        if (!empty($options['rules'])) {
            $filters = $this->rulesModel()->fillFilterRules($filters);
        }
        return $filters;
    }

    /**
     * @param int $id
     * @param array $options
     * @return array|false|mixed|null
     */
    public function getById($id, $options = [])
    {
        $filter = parent::getById($id);
        if (!$filter) {
            return null;
        }
        $filter = $this->workup([$filter], $options);
        return reset($filter);
    }

    /**
     * @param int $contact_id
     * @param array $options
     * @return array|mixed
     * @throws waException
     */
    public function getTemplatesByUser($contact_id, $options = [])
    {
        $filters = $this->where('`name` IS NOT NULL AND `parent_id` IS NULL AND `creator_contact_id` = ' . (int)$contact_id)
            ->order('sort')->fetchAll('id');
        if ($filters) {
            $filters = $this->workup($filters, $options);
        }
        return $filters;
    }

    /**
     * @param int $contact_id
     * @param array $options
     * @return array|false|mixed|null
     * @throws waException
     */
    public function getDefaultTemplateByUser($contact_id, $options = [])
    {
        $template = $this->where('`name` IS NULL AND `parent_id` IS NULL AND `creator_contact_id` = ' . (int)$contact_id)
            ->order('sort')->limit(1)->fetchAll('id');
        if (!$template) {
            $id = $this->createDefaultFilter($contact_id);
            return $this->getById($id, $options);
        }
        $filters = $this->workup($template, $options);
        return reset($filters);
    }

    /**
     * @param int $template_id
     * @param int $contact_id
     * @param array $options
     * @return array|false|mixed|null
     * @throws waException
     */
    public function getTransientByTemplate($template_id, $contact_id, $options = [])
    {
        $filters = $this->getByField([
            'creator_contact_id' => $contact_id,
            'parent_id' => $template_id,
        ], 'id');

        if ($filters) {
            if (!empty($options['reset_filter_to_template'])) {
                $filter = reset($filters);
                $template = $this->getById($template_id);
                if (!$template) {
                    throw new waException('template not found: '.$template_id);
                }
                $this->copyRules($template['id'], $filter['id'], true);
                $this->updateById($filter['id'], ['parent_id' => $template['id']]);
            }

            $filters = $this->workup($filters, $options);
            return reset($filters);
        }

        $transient_id = $this->duplicate($template_id);
        return $this->getById($transient_id, $options);
    }

    /**
     * @param int $source_id
     * @param int $destination_id
     * @return mixed|string
     * @throws waException
     */
    public function rewrite($source_id, $destination_id)
    {
        $filter = $this->getById($source_id);
        $destination_filter = $this->getById($destination_id);
        if (!$filter) {
            throw new waException('source filter not found ' . $source_id);
        }
        if (!$destination_filter) {
            throw new waException('destination filter not found ' . $destination_id);
        }
        $this->copyRules($filter['id'], $destination_filter['id'], true);

        return $destination_filter['id'];
    }

    /**
     * @param int $id
     * @param string $mode
     * @param array $data
     * @param bool $copy_rules
     * @return bool|int|resource
     * @throws waException
     */
    public function duplicate($id, $mode = self::DUPLICATE_MODE_TRANSIENT, $data = [], $copy_rules = true)
    {
        $filter = $this->getById($id);
        if (!$filter) {
            throw new waException('filter not found ' . $id);
        }
        if (($mode == self::DUPLICATE_MODE_TEMPLATE || $mode == self::DUPLICATE_MODE_TRANSIENT) && !empty($filter['parent_id'])) {
            throw new waException('source filter must be a template: '.$id);
        } elseif (($mode == self::DUPLICATE_MODE_FILTER || $mode == self::DUPLICATE_MODE_CREATE) && empty($filter['parent_id'])) {
            throw new waException('source filter must be associated with a template: '.$id);
        }
        $rules = [
            'creator_contact_id' => wa()->getUser()->getId(),
            'sort' => 0,
        ];
        if ($mode == self::DUPLICATE_MODE_TRANSIENT) {
            $rules['parent_id'] = $filter['id'];
            $rules['name'] = null;
        } elseif ($mode == self::DUPLICATE_MODE_CREATE) {
            $rules += [
                'parent_id' => null,
                'use_datetime' => null,
                'browser' => null,
            ];
        } elseif (self::DUPLICATE_MODE_FILTER) {
            $rules['name'] = null;
        } elseif (self::DUPLICATE_MODE_TEMPLATE) {
            $rules['parent_id'] = null;
        }
        $insert = array_merge($filter, $rules, $data);
        unset($insert['id']);
        $new_filter_id = $this->insert($insert);
        if (!$new_filter_id) {
            throw new waException('Unable to copy filter ('.$id.') ' . wa_dump_helper($insert));
        }
        $this->correctSort();

        if ($copy_rules) {
            $this->copyRules($filter['id'], $new_filter_id);
        }

        return $new_filter_id;
    }

    /**
     * @param int $source_id
     * @param int $destination_id
     * @param bool $delete_existing
     * @return void
     */
    protected function copyRules($source_id, $destination_id, $delete_existing = false)
    {
        if ($delete_existing) {
            $this->rulesModel()->deleteByField('filter_id', $destination_id);
        }
        $rules = shopFilter::getAllTypes(true);
        $sql = 'INSERT INTO `shop_filter_rules` (`filter_id`, `rule_type`, `rule_params`, `rule_group`)
                SELECT ?, `rule_type`, `rule_params`, `rule_group`
                FROM `shop_filter_rules`
                WHERE `filter_id` = ? AND `rule_type` IN("' . implode('","', array_keys($rules)) . '")';
        $this->exec($sql, [(int)$destination_id, (int)$source_id]);
    }

    /**
     * @param int $contact_id
     * @return bool|int|resource
     */
    protected function createDefaultFilter($contact_id)
    {
        return $this->insert([
            'creator_contact_id' => $contact_id,
        ]);
    }

    public function correctSort()
    {
        $sort = 0;
        $filters = $this->select('`id`, `sort`')->where('`parent_id` IS NULL')->order('`sort`')->fetchAll();
        foreach ($filters as $item) {
            if ($item['sort'] != $sort) {
                $this->updateByField('id', $item['id'], ['sort' => $sort]);
                $this->updateByField('parent_id', $item['id'], ['sort' => $sort]);
            }
            $sort++;
        }
    }
}
