<?php

class shopPresentationModel extends waModel
{
    protected $table = 'shop_presentation';

    const DUPLICATE_MODE_TEMPLATE = 'template';
    const DUPLICATE_MODE_PRESENTATION = 'presentation';
    // from template to presentation
    const DUPLICATE_MODE_TRANSIENT = 'transient';
    // from presentation to template
    const DUPLICATE_MODE_CREATE = 'create';

    /**
     * @var shopPresentationColumnsModel $columns_model
     */
    protected $columns_model = null;

    protected function columnsModel()
    {
        if (empty($this->columns_model)) {
            $this->columns_model = new shopPresentationColumnsModel();
        }
        return $this->columns_model;
    }

    /**
     * @param array $presentations
     * @param array $options
     * @return array|mixed
     */
    public function workup($presentations, $options = [])
    {
        if (!empty($options['columns'])) {
            $presentations = $this->columnsModel()->fillPresentationColumns($presentations);
        }
        return $presentations;
    }

    /**
     * @param $id
     * @param $options
     * @return array|false|mixed|null
     */
    public function getById($id, $options = [])
    {
        $presentations = [$id => parent::getById($id)];
        if (empty($presentations[$id])) {
            return null;
        }
        $presentations = $this->workup($presentations, $options);
        return reset($presentations);
    }

    /**
     * @param int $contact_id
     * @param array $options
     * @return array|mixed
     * @throws waDbException
     */
    public function getTemplatesByUser($contact_id, $options = [])
    {
        $fields = '*';
        if (isset($options['fields']) && !empty($options['fields']) && is_array($options['fields'])) {
            $fields = $this->escape(implode(', ', $options['fields']));
        }

        $presentations = $this->select($fields)->where('`name` IS NOT NULL AND `parent_id` IS NULL AND `creator_contact_id` = ' . (int)$contact_id)
            ->order('sort')->fetchAll('id');
        if ($presentations) {
            $presentations = $this->workup($presentations, $options);
        }
        return $presentations;
    }

    /**
     * @param int $contact_id
     * @param array $options
     * @return array|false|mixed|null
     * @throws waDbException
     */
    public function getDefaultTemplateByUser($contact_id, $options = [])
    {
        $base_template = $this->where('`creator_contact_id` = ? AND `name` IS NULL AND `parent_id` IS NULL', [$contact_id])->order('sort')->limit(1)->fetchAll('id');
        if (!$base_template) {
            $id = $this->createDefaultTemplate($contact_id);
            $contact_settings_model = new waContactSettingsModel();
            $created_default_presentations = $contact_settings_model->getOne($contact_id, 'shop', 'created_default_presentations');
            if (empty($created_default_presentations)) {
                $contact_settings_model->set($contact_id, 'shop', 'created_default_presentations', 1);
                $this->createBaseTemplates($contact_id);
            }
            return $this->getById($id, $options);
        }
        $presentations = $this->workup($base_template, $options);
        return reset($presentations);
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
        $presentations = [];
        $datetime = new DateTime('now');
        $duplicate_options = [
            'use_datetime' => $datetime->format('Y-m-d H:i:s')
        ];
        $datetime = new DateTime('-1 month');
        $where = '`creator_contact_id` = i:ccid AND `parent_id` IS NOT NULL AND `use_datetime` >= s:udt';
        $where_params = [
            'ccid' => $contact_id,
            'udt' => $datetime->format('Y-m-d H:i:s')
        ];
        if (!empty($options['open_presentation_ids']) && is_array($options['open_presentation_ids'])) {
            $where .= ' AND `id` NOT IN (s:op)';
            $where_params['op'] = implode(',', $options['open_presentation_ids']);
        }
        $where_browser = '';
        if (isset($options['browser'])) {
            $where_browser = " AND `browser` = s:br";
            $where_params['br'] = $options['browser'];
            $duplicate_options['browser'] = $options['browser'];
        }
        $order = 'use_datetime DESC';
        if (!empty($options['active_presentation_id'])) {
            $where_params['id'] = $options['active_presentation_id'];
            $presentations = $this->where("`id` = i:id AND " . $where . $where_browser, $where_params)->order($order)->fetchAll('id');
        } else {
            if ($where_browser) {
                $presentations = $this->where($where . $where_browser, $where_params)->order($order)->fetchAll('id');
            }
            if (empty($presentations)) {
                $presentations = $this->where($where, $where_params)->order($order)->fetchAll('id');
            }
        }
        if ($presentations) {
            if (!empty($options['use_presentation'])) {
                $presentation = reset($presentations);
                $transient_id = $this->duplicate($presentation['id'], self::DUPLICATE_MODE_PRESENTATION);
                return $this->getById($transient_id, $options);
            } elseif (!empty($options['reset_presentation_to_template'])) {
                $presentation = reset($presentations);
                $template = $this->getById($template_id);
                if (!$template) {
                    throw new waException('template not found: '.$template_id);
                }

                $sort_column = [];
                if ($presentation['sort_column_id']) {
                    $sort_column = $this->columnsModel()->getById($presentation['sort_column_id']);
                }
                $this->copyColumns($template['id'], $presentation['id'], true);

                $update = [
                    'parent_id' => $template['id'],
                    'sort_column_id' => null,
                    'view' => $template['view'],
                    'rows_on_page' => $template['rows_on_page'],
                ];
                if ($sort_column) {
                    $new_sort_column = $this->columnsModel()->getByField([
                        'presentation_id' => $presentation['id'],
                        'column_type' => $sort_column['column_type'],
                    ]);
                    if ($new_sort_column) {
                        $update['sort_column_id'] = $new_sort_column['id'];
                    }
                }

                $update = array_merge($update, $duplicate_options);
                $this->updateById($presentation['id'], $update);
                return $this->getById($presentation['id'], $options);
            } else {
                $presentations = $this->workup($presentations, $options);
                return reset($presentations);
            }
        }

        $transient_id = $this->duplicate($template_id);
        return $this->getById($transient_id, $options);
    }

    /**
     * @param int $source_presentation_id
     * @param int $destination_template_id
     * @return mixed|string
     * @throws waException
     */
    public function rewrite($source_presentation_id, $destination_template_id)
    {
        $source_presentation = $this->getById($source_presentation_id);
        $destination_template = $this->getById($destination_template_id);
        if (!$source_presentation) {
            throw new waException('presentation not found ' . $source_presentation_id);
        }
        if (!$destination_template) {
            throw new waException('template not found ' . $destination_template_id);
        }
        if (!empty($destination_template['parent_id'])) {
            throw new waException('template ' . $destination_template_id . ' cannot be a presentation');
        }
        // update only parameter fields
        $update_fields = [
            'sort_column_id' => null,
            'view' => $source_presentation['view'],
            'rows_on_page' => $source_presentation['rows_on_page'],
            'sort_order' => 'ASC',
        ];

        $this->updateById($destination_template_id, $update_fields);
        $this->copyColumns($source_presentation['id'], $destination_template['id'], true);

        return $destination_template['id'];
    }

    /**
     * @param int $id template_id | presentation_id
     * @param string $mode
     * @param array $data
     * @param bool $copy_columns
     * @return bool|int|resource
     * @throws waException
     */
    public function duplicate($id, $mode = self::DUPLICATE_MODE_TRANSIENT, $data = [], $copy_columns = true)
    {
        $presentation = $this->getById($id);
        if (!$presentation) {
            throw new waException('presentation not found '.$id);
        }
        if (($mode == self::DUPLICATE_MODE_TEMPLATE || $mode == self::DUPLICATE_MODE_TRANSIENT) && !empty($presentation['parent_id'])) {
            throw new waException('source presentation must be a template: '.$id);
        } elseif (($mode == self::DUPLICATE_MODE_PRESENTATION || $mode == self::DUPLICATE_MODE_CREATE) && empty($presentation['parent_id'])) {
            throw new waException('source presentation must be associated with a template: '.$id);
        }
        $user_id = wa()->getUser()->getId();
        $rules = [
            'sort' => 0,
        ];
        if ($mode == self::DUPLICATE_MODE_TRANSIENT) {
            $rules['parent_id'] = $presentation['id'];
            $rules['name'] = null;
        } elseif ($mode == self::DUPLICATE_MODE_CREATE) {
            $rules += [
                'parent_id' => null,
                'sort_column_id' => null,
                'sort_order' => 'ASC',
                'use_datetime' => null,
                'browser' => null,
                'filter_id' => null,
            ];
        } elseif ($mode == self::DUPLICATE_MODE_PRESENTATION) {
            $rules['name'] = null;
        } elseif ($mode == self::DUPLICATE_MODE_TEMPLATE) {
            $rules += [
                'parent_id' => null,
                'sort_column_id' => null,
                'sort_order' => 'ASC',
                'use_datetime' => null,
                'browser' => null,
            ];
        }
        if (!empty($presentation['filter_id']) && $mode == self::DUPLICATE_MODE_PRESENTATION) {
            $filter_model = new shopFilterModel();
            $new_filter_id = $filter_model->duplicate($presentation['filter_id'], $filter_model::DUPLICATE_MODE_FILTER);
            $rules['filter_id'] = $new_filter_id;
        } elseif ($mode == self::DUPLICATE_MODE_TRANSIENT) {
            $filter_model = new shopFilterModel();
            $contact_id = isset($data['creator_contact_id']) && $data['creator_contact_id'] > 0 ? $data['creator_contact_id'] : $user_id;
            $default_template = $filter_model->getDefaultTemplateByUser($contact_id);
            $new_filter_id = $filter_model->duplicate($default_template['id']);
            $rules['filter_id'] = $new_filter_id;
        }
        if (!isset($data['creator_contact_id']) || $data['creator_contact_id'] < 1) {
            $rules['creator_contact_id'] = $user_id;
        }

        $insert = array_merge($presentation, $data, $rules);
        unset($insert['id']);
        $new_presentation_id = $this->insert($insert);
        if (!$new_presentation_id) {
            throw new waException('Unable to copy presentation ('.$id.') '.wa_dump_helper($insert));
        }
        $this->correctSort();

        if ($copy_columns) {
            $this->copyColumns($presentation['id'], $new_presentation_id);
            if ($mode == self::DUPLICATE_MODE_PRESENTATION) {
                $new_sort_column_id = null;
                $sort_column = $this->columnsModel()->getById($presentation['sort_column_id']);
                if ($sort_column) {
                    $new_column = $this->columnsModel()->getByField([
                        'presentation_id' => $new_presentation_id,
                        'column_type' => $sort_column['column_type'],
                    ]);
                    if ($new_column) {
                        $new_sort_column_id = $new_column['id'];
                    }
                }
                $this->updateById($new_presentation_id, ['sort_column_id' => $new_sort_column_id]);
            }
        }
        return $new_presentation_id;
    }

    /**
     * @param int $source_presentation_id
     * @param int $dest_presentation_id
     * @param bool $delete_existing
     * @return void
     * @throws waException
     */
    protected function copyColumns($source_presentation_id, $dest_presentation_id, $delete_existing = false)
    {
        if ($delete_existing) {
            $this->columnsModel()->deleteByField('presentation_id', $dest_presentation_id);
        }
        $presentation = new shopPresentation($source_presentation_id);
        $columns = $presentation->getEnabledColumns();
        $enabled_columns = array_column($columns, 'column_type');
        $sql = "INSERT INTO `shop_presentation_columns` (`presentation_id`, `column_type`, `width`, `data`, `sort`)
                SELECT ?, `column_type`, `width`, `data`, `sort`
                FROM `shop_presentation_columns`
                WHERE `presentation_id` = ?";
        if ($enabled_columns) {
            $sql .= ' AND `column_type` IN("' . implode('","', $enabled_columns) . '")';
        }
        $sql .= ' ORDER BY `id` ASC';
        $this->exec($sql, $dest_presentation_id, $source_presentation_id);
    }

    /**
     * @param int $contact_id
     * @return bool|int|resource
     * @throws waException
     */
    protected function createDefaultTemplate($contact_id)
    {
        $id = $this->insert([
            'creator_contact_id' => $contact_id,
            'view' => shopPresentation::VIEW_THUMBS,
            'sort' => 0,
        ]);

        $columns = [
            [
                'column_type' => 'image_crop_small',
            ], [
                'column_type' => 'name',
                'width' => 297,
                'data' => ['settings' => ['long_name_format' => '']],
            ], [
                'column_type' => 'visibility',
            ], [
                'column_type' => 'count',
                'width' => 89,
            ], [
                'column_type' => 'purchase_price',
                'width' => 142,
                'data' => ['settings' => ['format' => 'float']],
            ], [
                'column_type' => 'compare_price',
                'width' => 158,
                'data' => ['settings' => ['format' => 'float']],
            ], [
                'column_type' => 'price',
                'width' => 124,
                'data' => ['settings' => ['format' => 'float']],
            ],
        ];

        $this->fillColumns($columns, $id);
        $this->columnsModel()->multipleInsert($columns);

        return $id;
    }

    /**
     * @param int $contact_id
     * @return array
     * @throws waException
     */
    protected function createBaseTemplates($contact_id)
    {
        $prices_id = $this->insert([
            'name' => _w('Prices & availability'),
            'creator_contact_id' => $contact_id,
            'view' => shopPresentation::VIEW_TABLE_EXTENDED,
            'sort' => 1,
        ]);
        $catalog_id = $this->insert([
            'name' => _w('Appearance in the catalog'),
            'creator_contact_id' => $contact_id,
            'view' => shopPresentation::VIEW_TABLE_EXTENDED,
            'sort' => 2,
        ]);
        $publication_id = $this->insert([
            'name' => _w('Publication & update'),
            'creator_contact_id' => $contact_id,
            'view' => shopPresentation::VIEW_TABLE_EXTENDED,
            'sort' => 3,
        ]);

        $prices_columns = [
            [
                'column_type' => 'image_crop_small',
            ], [
                'column_type' => 'name',
                'width' => 297,
                'data' => ['settings' => ['long_name_format' => '']],
            ], [
                'column_type' => 'visibility',
            ], [
                'column_type' => 'count',
                'width' => 89,
            ], [
                'column_type' => 'purchase_price',
                'width' => 142,
                'data' => ['settings' => ['format' => 'float']],
            ], [
                'column_type' => 'compare_price',
                'width' => 158,
                'data' => ['settings' => ['format' => 'float']],
            ], [
                'column_type' => 'price',
                'width' => 124,
                'data' => ['settings' => ['format' => 'float']],
            ],
        ];

        $catalog_columns = [
            [
                'column_type' => 'image_crop_small',
            ], [
                'column_type' => 'name',
                'width' => 264,
                'data' => ['settings' => ['long_name_format' => '']],
            ], [
                'column_type' => 'visibility',
            ], [
                'column_type' => 'url',
                'width' => 177,
            ], [
                'column_type' => 'category_id',
                'width' => 178,
            ], [
                'column_type' => 'categories',
                'width' => 242,
                'data' => ['settings' => ['visible_count' => 3, 'format' => 'origin']],
            ], [
                'column_type' => 'sets',
                'width' => 163,
                'data' => ['settings' => ['visible_count' => 3, 'format' => 'origin']],
            ], [
                'column_type' => 'tags',
                'width' => 163,
                'data' => ['settings' => ['visible_count' => 3, 'format' => 'origin']],
            ],
        ];

        $publication_columns = [
            [
                'column_type' => 'image_crop_small',
            ], [
                'column_type' => 'name',
                'width' => 297,
                'data' => ['settings' => ['long_name_format' => '']],
            ], [
                'column_type' => 'visibility',
            ], [
                'column_type' => 'status',
            ], [
                'column_type' => 'count',
                'width' => 89,
            ], [
                'column_type' => 'create_datetime',
            ], [
                'column_type' => 'edit_datetime',
            ],
        ];

        $this->fillColumns($prices_columns, $prices_id);
        $this->fillColumns($catalog_columns, $catalog_id);
        $this->fillColumns($publication_columns, $publication_id);
        $this->columnsModel()->multipleInsert($prices_columns);
        $this->columnsModel()->multipleInsert($catalog_columns);
        $this->columnsModel()->multipleInsert($publication_columns);

        /**
         * @event backend_presentation_user_init
         *
         * First-time initialization of product presentations for given backend user.
         *
         * @since 10.0.0
         */
        wa('shop')->event('backend_presentation_user_init', ref([
            'contact_id' => $contact_id,
            'presentation_ids' => [$prices_id, $catalog_id, $publication_id],
        ]));

        return [$prices_id, $catalog_id, $publication_id];
    }

    /**
     * @param array $columns
     * @param int $id
     * @return void
     */
    protected function fillColumns(&$columns, $id)
    {
        foreach ($columns as $key => &$column) {
            if (!isset($column['width'])) {
                $column['width'] = null;
            }
            if (!isset($column['data'])) {
                $column['data'] = [];
            }
            $column['presentation_id'] = $id;
            $column['sort'] = $key;
        }
        unset($column);
    }

    /**
     * @return void
     */
    public function correctSort()
    {
        $sort = 0;
        $presentations = $this->select('`id`, `sort`')->where('parent_id IS NULL')->order('sort')->fetchAll();
        foreach ($presentations as $item) {
            if ($item['sort'] != $sort) {
                $this->updateByField('id', $item['id'], ['sort' => $sort]);
                $this->updateByField('parent_id', $item['id'], ['sort' => $sort]);
            }
            $sort++;
        }
    }

    /**
     * @param int $id
     * @param bool $update_datetime
     * @param string $browser
     * @return void
     */
    public function updateLastUsedData($id, $update_datetime = true, $browser = null)
    {
        if ($id > 0) {
            $data = [];
            if ($update_datetime) {
                $data['use_datetime'] = date('Y-m-d H:i:s');
            }
            if (!empty($browser) && is_string($browser) && mb_strlen($browser) <= 64) {
                $data['browser'] = $browser;
            }
            if ($data) {
                $this->updateById($id, $data);
            }
        }
    }

    /**
     * @param int $template_id
     * @param int $presentation_id
     * @return void
     */
    public function deleteObsoletePresentations($template_id, $presentation_id)
    {
        $datetime = new DateTime('-1 month');
        $ids = $this->select('id')->where('`parent_id` = i:id AND `id` != i:pid AND `use_datetime` < s:udt', [
            'id' => $template_id,
            'pid' => $presentation_id,
            'udt' => $datetime->format('Y-m-d H:i:s')
        ])->fetchAll('id');
        $ids = array_keys($ids);
        if ($ids) {
            $this->deleteById($ids);
            $this->columnsModel()->deleteByField('presentation_id', $ids);
        }
    }
}
