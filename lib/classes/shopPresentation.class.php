<?php
/**
 * Represents a presentation in products section for backend users:
 * allows to set up columns in products table a certain way,
 * save several such presentations and switch between them.
 *
 * This class is read-only. It allows to access data but not change it.
 * In order to change presentations, use models directly.
 *
 * Presentations can be templates or transient. Template presentations are
 * permanent, rarely modified. Transient presentations are created from
 * templates on the fly, and are often changed in place.
 * Each backend user has no more than one transient presentation per template.
 */
class shopPresentation
{
    const VIEW_THUMBS = 'thumbs';
    const VIEW_TABLE = 'table';
    const VIEW_TABLE_EXTENDED = 'table_extended';

    const EDITING_RULE_ONLY_PRODUCT = 'only_product';
    const EDITING_RULE_ONLY_DELETE = 'only_delete';
    const EDITING_RULE_SIMPLE_MODE = 'simple_mode';
    const EDITING_RULE_NO = 'no';

    protected $presentation;
    protected $template;
    protected $models = [];
    protected $units;
    protected $stocks;
    protected $shop_currency;
    protected $template_filter_id;

    public static function getCurrentTransient()
    {
        $id = waRequest::request('presentation', null, waRequest::TYPE_INT);
        $params = [
            'active_presentation' => waRequest::request('active_presentation', null, waRequest::TYPE_INT),
            'open_presentations' => waRequest::request('open_presentations', '', waRequest::TYPE_STRING_TRIM),
            'template_filter_id' => waRequest::request('filter', null, waRequest::TYPE_INT),
        ];
        return new self($id, true, $params);
    }

    /**
     * $id === null: load user's default presentation (create one of not exists)
     *
     * If $transient is false: load presentation data, no matter if transient or template.
     *
     * If $transient is true and $id represents a template (i.e. its parent_id is null),
     * then load its corresponding transient presentation.
     */
    public function __construct($id = null, $transient = false, $params = [])
    {
        $template = $presentation = null;
        $template_id = $presentation_id = null;
        $template_selected = false;
        $reset_presentation_to_template = true;

        $params += [
            'active_presentation' => null,
            'open_presentations' => null,
        ];
        if (is_string($params['open_presentations']) && mb_strlen($params['open_presentations'])) {
            $params['open_presentations'] = explode(',', $params['open_presentations']);
        }
        if (is_array($params['open_presentations']) && !empty($params['open_presentations'])) {
            $params['open_presentations'] = array_map('intval', $params['open_presentations']);
        } else {
            $params['open_presentations'] = [];
        }

        $with_columns = ['columns' => true];
        $browser = self::getBrowserName();
        if (!empty($id)) {
            $row = $this->getModel()->getById($id, $with_columns);
            if ($row) {
                if (empty($row['parent_id'])) {
                    $template = $row;
                    $template_selected = true;
                } else {
                    if ($transient) {
                        if ($row['browser'] != $browser || $row['creator_contact_id'] != wa()->getUser()->getId()) {
                            $presentation_id = $this->getModel()->duplicate($row['id'], shopPresentationModel::DUPLICATE_MODE_PRESENTATION);
                            $presentation = $this->getModel()->getById($presentation_id, $with_columns);
                            $presentation_id = $presentation['id'];
                        } elseif (!in_array($row['id'], $params['open_presentations'])) {
                            $presentation = $row;
                            $presentation_id = $presentation['id'];
                        }
                    } else {
                        $presentation = $row;
                        $presentation_id = $row['id'];
                    }
                    $template = $this->getModel()->getById($row['parent_id'], $with_columns);
                }
                $template_id = $template['id'];
            }
        }

        if (empty($template)) {
            $template = $this->getModel()->getDefaultTemplateByUser(wa()->getUser()->getId(), $with_columns);
            if (empty($template)) {
                // This can not happen
                throw new waException('Error initializing default presentation');
            }
            $template_id = $template['id'];
            $presentation = $presentation_id = null;
            $template_selected = $reset_presentation_to_template = false;
        }

        $view = waRequest::request('view', null, waRequest::TYPE_STRING_TRIM);
        $replace_params = [
            'categories' => waRequest::get('category_id', null, waRequest::TYPE_INT),
            'sets' => waRequest::get('set_id', null, waRequest::TYPE_STRING_TRIM),
            'types' => waRequest::get('type_id', null, waRequest::TYPE_INT),
            'tags' => waRequest::get('tag_name', null, waRequest::TYPE_STRING_TRIM),
        ];
        $open_from_side_section = false;
        foreach ($replace_params as $param) {
            if ($param) {
                $open_from_side_section = true;
                break;
            }
        }

        if (empty($presentation)) {
            if ($transient) {
                // if contacts do not match create a new presentation
                $creator_id = $template['creator_contact_id'] == wa()->getUser()->getId() ? $template['creator_contact_id'] : -1;
                $presentation = $this->getModel()->getTransientByTemplate($template_id, $creator_id, [
                    'use_presentation' => $view || $open_from_side_section,
                    'reset_presentation_to_template' => $reset_presentation_to_template,
                    'columns' => true,
                    'browser' => $browser,
                    'active_presentation_id' => !in_array($params['active_presentation'], $params['open_presentations']) ? $params['active_presentation'] : null,
                    'open_presentation_ids' => $params['open_presentations'],
                ]);
            } else {
                $presentation = $template;
            }
            if (empty($presentation)) {
                // This can not happen
                throw new waException('Error initializing default presentation');
            }
            $presentation_id = $presentation['id'];
        }

        $reset_rules = false;
        if ($transient) {
            if ($template_selected) {
                $this->getModel()->deleteObsoletePresentations($template_id, $presentation_id);
            }
            if (!empty($presentation_id)) {
                $data = ['use_datetime' => date('Y-m-d H:i:s')];
                if ($view) {
                    if ($view == 'skus') {
                        $view = shopPresentation::VIEW_TABLE_EXTENDED;
                    }
                    if (in_array($view, [shopPresentation::VIEW_THUMBS, shopPresentation::VIEW_TABLE, shopPresentation::VIEW_TABLE_EXTENDED])) {
                        $data['view'] = $view;
                        $presentation['view'] = $view;
                        $reset_rules = true;
                    }
                }
                if (!empty($browser) && is_string($browser) && mb_strlen($browser) <= 64) {
                    $data['browser'] = $browser;
                }
                $this->getModel()->updateById($presentation_id, $data);
            }
        }

        $this->template = $template;
        $this->presentation = ifempty($presentation, $template);
        $this->presentation['is_copy_template'] = $this->isCopyTemplate($presentation, $template);
        $this->units = $this->getUnits();
        $this->stocks = shopHelper::getStocks();
        $this->shop_currency = wa('shop')->getConfig()->getCurrency();

        if ($transient) {
            $this->replaceFilterRules($reset_rules, $replace_params);
            if (!empty($params['template_filter_id'])) {
                $this->template_filter_id = $params['template_filter_id'];
                $filter_model = new shopFilterModel();
                try {
                    $filter_model->rewrite($this->template_filter_id, $this->getFilterId());
                } catch (waException $e) {}
            }
        }
    }

    public function getId()
    {
        return $this->presentation['id'];
    }

    public function getFilterId()
    {
        return $this->presentation['filter_id'];
    }

    /**
     * @param string $field
     * @return mixed
     */
    public function getField($field)
    {
        return isset($this->presentation[$field]) ? $this->presentation[$field] : null;
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function isTemplate()
    {
        return empty($this->presentation['parent_id']);
    }

    public function getData()
    {
        $presentation = $this->presentation;
        $presentation['columns'] = $this->getEnabledColumns();
        return $presentation;
    }

    public function getFilter()
    {
        $filter = new shopFilter($this->getFilterId(), true);
        $this->updateFilterId($filter->getId());
        return $filter;
    }

    /**
     * @return null|string
     * @throws waException
     */
    public function getSortColumnType()
    {
        if ($this->presentation['sort_column_id'] !== null) {
            return $this->getModel('column')->select('column_type')->where('id = ?', $this->presentation['sort_column_id'])->fetchField('column_type');
        } else {
            return null;
        }
    }

    public function getEnabledColumns()
    {
        $enabled_columns = $this->presentation['columns'];
        $all_columns = $this->getColumnsList();
        $columns = [];
        foreach ($enabled_columns as $column) {
            if (isset($all_columns[$column['column_type']])) {
                $columns[] = $column;
            }
        }
        $sort = max(array_column($columns, 'sort'), 0);
        $column_names = array_column($columns, 'column_type');
        $required_columns = self::getRequiredColumns($this->getField('view'));
        foreach ($required_columns as $required_column) {
            if (!in_array($required_column, $column_names)) {
                $columns[] = [
                    'id' => null,
                    'presentation_id' => null,
                    'column_type' => $required_column,
                    'width' => null,
                    'data' => null,
                    'sort' => ++$sort,
                ];
            }
        }

        return $columns;
    }

    /**
     * @param shopProductsCollection $collection
     * @param array $options
     * @return mixed
     * @throws waDbException
     * @throws waException
     */
    public function getProducts($collection, $options = [])
    {
        // TODO: hook that allows plugins to add fields to collection
        $additional_fields = [];

        $offset = ifempty($options, 'offset', 0);
        $limit = 50;
        if (array_key_exists('limit', $options) && $options['limit'] === null) {
            $limit = $this->getField('rows_on_page');
            $total_count = $offset + $limit;
        } elseif (isset($options['limit']) && is_numeric($options['limit'])) {
            $total_count = $offset + $options['limit'];
            $limit = $options['limit'];
        } else {
            $total_count = $collection->count();
        }
        if (!empty($options['fields']) && is_array($options['fields'])) {
            $fields = join(',', array_unique(array_merge($additional_fields, $options['fields'])));
        } else {
            $fields = $this->getCollectionFields($additional_fields);
        }

        $all_products = [];
        while ($offset < $total_count) {
            $products = $collection->getProducts($fields, $offset, $limit, false);
            if (!$products) {
                break;
            }
            $all_products += $products;
            $offset += count($products);
        }

        if (!empty($options['format'])) {
            $this->formatProducts($all_products, $collection);
        }

        return $all_products;
    }

    public function getColumnsList()
    {
        $columns = [];

        $cols = [
            // Product columns
            'id' => [
                'name' => _w('Product ID'),
                'editable' => false,
            ],
            'name' => [
                'name' => _w('Product name'),
                'editing_rule' => self::EDITING_RULE_ONLY_PRODUCT
            ],
            'summary' => [
                'name' => _w('Summary'),
            ],
            'meta_title' => [
                'name' => _w('Page <title> tag'),
            ],
            'meta_keywords' => [
                'name' => _w('Meta keywords tag'),
            ],
            'meta_description' => [
                'name' => _w('Page meta description tag'),
            ],
            'description' => [
                'name' => _w('Description'),
            ],
            'create_datetime' => [
                'name' => _w('Date added'),
                'editable' => false,
            ],
            'edit_datetime' => [
                'name' => _w('Last change date'),
                'editable' => false,
            ],
            'status' => [
                'name' => _w('Availability in the storefront'),
                'min_width' => 250
            ],
            'video_url' => [
                'name' => _w('Video URL'),
            ],
            'url' => [
                'name' => _w('Product page'),
            ],
            'price' => [
                'name' => _w('Price'),
                'editing_rule' => self::EDITING_RULE_SIMPLE_MODE,
            ],
            'compare_price' => [
                'name' => _w('Compare at price'),
                'editing_rule' => self::EDITING_RULE_SIMPLE_MODE,
                'sortable' => false,
            ],
            'currency' => [
                'name' => _w('Currency'),
            ],
            'rating' => [
                'name' => _w('Rating'),
                'editable' => false,
                'width' => 90,
                'width_locked' => true
            ],
            'base_price' => [
                'name' => _w('Base price'),
                'editable' => false,
            ],
            'rating_count' => [
                'name' => _w('Number of reviews'),
                'editable' => false,
            ],
            'count' => [
                'name' => _w('In stock'),
                'editing_rule' => self::EDITING_RULE_SIMPLE_MODE,
                'min_width' => 86
            ],
            'tax_id' => [
                'name' => _w('Tax'),
            ],
            'type_id' => [
                'name' => _w('Product type'),
            ],
            'badge' => [
                'name' => _w('Badge'),
                'width' => 170,
                'width_locked' => true
            ],
            'sku_type' => [
                'name' => _w('Product variety selection in the storefront'),
                'editable' => false,
            ],
            'sku_count' => [
                'name' => _w('Variants number'),
                'editable' => false,
            ],
            'total_sales' => [
                'name' => _w('Total sales'),
                'editable' => false,
            ],
            'category_id' => [
                'name' => _w('Main category')
            ]
        ];

        if (shopUnits::stockUnitsEnabled()) {
            $cols += [
                'stock_unit_id' => [
                    'name' => _w('Stock quantity unit'),
                    'editable' => false,
                    'sortable' => false,
                    'nowrap' => true
                ],
            ];
        }
        if (shopUnits::baseUnitsEnabled()) {
            $cols += [
                'base_unit_id' => [
                    'name' => _w('Base quantity unit'),
                    'editable' => false,
                    'sortable' => false,
                    'nowrap' => true
                ],
                'stock_base_ratio' => [
                    'name' => _w('Stock to base quantity units ratio'),
                    'min_width' => 150
                ],
            ];
        }
        if (shopFrac::isEnabled()) {
            $cols += [
                'order_multiplicity_factor' => [
                    'name' => _w('Add-to-cart step'),
                    'editable' => false,
                ],
                'order_count_min' => [
                    'name' => _w('Minimum orderable quantity'),
                    'editable' => false,
                ],
                'order_count_step' => [
                    'name' => _w('Quantity adjustment with “+/-” buttons'),
                    'editable' => false,
                ],
            ];
        }

        // Column for each stock
        $stocks = $this->stocks;
        foreach ($stocks as $stock_id => $stock) {
            $is_virtual = isset($stock['substocks']);
            $column_id = 'stocks_' . $stock_id;
            $cols[$column_id] = [
                'id' => $column_id,
                'stock_id' => $stock_id,
                'name' => $stock['name'],
                'required' => false,
                'editable' => !$is_virtual,
                'editing_rule' => self::EDITING_RULE_SIMPLE_MODE,
                'sortable' => true,
                'type' => 'decimal'
            ];
            if ($is_virtual && is_array($stock['substocks'])) {
                $cols[$column_id]['substocks'] = $stock['substocks'];
            }
        }

        $cols += [
            // related data
            'tags' => [
                'name' => _w('Tags'),
                'editing_rule' => self::EDITING_RULE_ONLY_DELETE,
                'sortable' => false,
            ],
            'sets' => [
                'name' => _w('Sets'),
                'editing_rule' => self::EDITING_RULE_ONLY_DELETE,
                'sortable' => false,
            ],
            'categories' => [
                'name' => _w('Additional categories'),
                'editing_rule' => self::EDITING_RULE_ONLY_DELETE,
                'sortable' => false,
            ],
            'params' => [
                'name' => _w('Custom parameters'),
                'sortable' => false,
            ],
            /**
             * calculated columns
             * @see shopProductsCollection
             */
            'image_crop_small' => [
                'name' => _w('Image'),
                'type' => 'url',
                'editable' => false,
                'sortable' => false,
                'width_locked' => true
            ],
            'image_count' => [
                'name' => _w('Number of images'),
                'editable' => false,
            ],
            'sales_30days' => [
                'name' => _w('Last 30 days sales'),
                'type' => 'decimal',
                'editable' => false,
            ],
            'stock_worth' => [
                'name' => _w('Stock net worth'),
                'type' => 'decimal',
                'editable' => false,
            ],
            // SKU columns
            'sku' => [
                'name' => _w('SKU code'),
                'editable' => false,
                'sortable' => false,
            ],
            'purchase_price' => [
                'name' => _w('Purchase price'),
                'sortable' => false,
            ],
            'visibility' => [
                'name' => _w('Visibility'),
                'editing_rule' => self::EDITING_RULE_SIMPLE_MODE,
                'sortable' => false,
                'width' => 75,
                'width_locked' => true
            ],
        ];

        $product_model = new shopProductModel();
        $product_fields = $product_model->getMetadata();
        $product_skus_model = new shopProductSkusModel();
        $skus_fields = $product_skus_model->getMetadata();

        $required_columns = self::getRequiredColumns($this->presentation['view']);
        foreach ($cols as $column_id => $column_data) {
            if (!isset($column_data['type'])) {
                if (isset($product_fields[$column_id])) {
                    $column_data['type'] = $product_fields[$column_id]['type'];
                } elseif (isset($skus_fields[$column_id])) {
                    $column_data['type'] = $skus_fields[$column_id]['type'];
                } else {
                    $column_data['type'] = 'int';
                }
            }
            $column_data['id'] = $column_id;
            $column_data['disabled'] = in_array($column_id, $required_columns);
            $column_data['editable'] = isset($column_data['editable']) ? $column_data['editable'] : true;
            $column_data['editing_rule'] = isset($column_data['editing_rule']) ? $column_data['editing_rule'] : self::EDITING_RULE_NO;
            $column_data['sortable'] = isset($column_data['sortable']) ? $column_data['sortable'] : true;
            $columns[$column_id] = $column_data;
        }

        // Column for each feature
        $feature_model = new shopFeatureModel();
        $features = $feature_model->select('id, code, name, multiple, type, available_for_sku, status')->where('`parent_id` IS NULL')->fetchAll('id');
        foreach ($features as $id => $feature) {
            if ($feature['type'] != shopFeatureModel::TYPE_DIVIDER) {
                $feature_data = [
                    'id' => 'feature_'.$id,
                    'feature_id' => $id,
                    'feature_code' => $feature['code'],
                    'name' => $feature['name'],
                    'required' => false,
                    'editable' => true,
                    'editing_rule' => self::EDITING_RULE_NO,
                    'sortable' => empty($feature['multiple']) && $feature['type'] != shopFeatureModel::TYPE_COLOR,
                    'multiple' => !empty($feature['multiple']),
                    'type' => $feature['type'],
                    'available_for_sku' => (bool)$feature['available_for_sku'],
                    'visible_in_frontend' => ($feature['status'] === 'public'),
                ];

                if (strpos($feature['type'], '2d.') === 0) {
                    $feature_data['min_width'] = 150;
                } else if (strpos($feature['type'], '3d.') === 0) {
                    $feature_data['min_width'] = 250;
                } else if (strpos($feature['type'], 'range') === 0) {
                    $feature_data['min_width'] = 150;
                } else {
                    switch ($feature['type']) {
                        case 'date':
                            $feature_data['min_width'] = 100;
                            break;
                        case 'color':
                        case 'range.date':
                            $feature_data['min_width'] = 200;
                            break;
                    }
                }

                $columns['feature_'.$id] = $feature_data;
            }
        }

        return $columns;
    }

    /**
     * @param string $view_id
     * @return array[]
     */
    public static function getRequiredColumns($view_id)
    {
        $columns = [
            self::VIEW_TABLE => [
                'name',
            ],
            self::VIEW_TABLE_EXTENDED => [
                'name',
            ],
            self::VIEW_THUMBS => []
        ];

        return isset($columns[$view_id]) ? $columns[$view_id] : [];
    }

    public static function getBrowserName()
    {
        $user_agent = waRequest::getUserAgent();
        if (strpos($user_agent, 'Opera') !== false || strpos($user_agent, 'OPR') !== false) {
            $name = 'opera';
        } elseif (strpos($user_agent, 'Edg') !== false) {
            $name = 'edge';
        } elseif (strpos($user_agent, 'YaBrowser') !== false) {
            $name = 'yandex';
        } elseif (strpos($user_agent, 'Chrome') !== false) {
            $name = 'chrome';
        } elseif (strpos($user_agent, 'Safari') !== false) {
            $name = 'safari';
        } elseif (strpos($user_agent, 'Firefox') !== false) {
            $name = 'firefox';
        } elseif (strpos($user_agent, 'MSIE') !== false) {
            $name = 'ie';
        } else {
            $name = 'other';
        }

        return $name;
    }

    /**
     * @param int $product_id
     * @param int $presentation_id
     * @return array|null[]
     */
    public static function getNearestProducts($product_id, $presentation_id, $with_name = false)
    {
        $nearest_product_ids = [];
        $presentation = new shopPresentation($presentation_id, true);
        if ($presentation->getId() && $product_id) {
            $sort_column_type = $presentation->getSortColumnType();
            $sort_column_type = $sort_column_type == 'price' || $sort_column_type == 'base_price' ? 'min_' . $sort_column_type : $sort_column_type;
            $collection = new shopProductsCollection('', [
                'sort' => $sort_column_type !== null ? array_unique([$sort_column_type, 'name']) : ['name'],
                'order' => strtolower($presentation->getField('sort_order')),
                'prepare_filter' => $presentation->getFilterId(),
            ]);
            $nearest_product_ids = $collection->getPrevNextProductId($product_id, $with_name);
            $page = max(1, ceil($nearest_product_ids['position'] / $presentation->getField('rows_on_page')));
            $nearest_product_ids['page'] = $page;
        }

        return $nearest_product_ids;
    }

    /**
     * @param array $features
     * @return array
     * @throws waException
     */
    public static function addSelectableValues($features)
    {
        $feature_model = new shopFeatureModel();

        // Fetch values for selectable features
        $selectable_features = array();
        foreach ($features as $code => $feature) {
            $features[$code]['feature_id'] = intval($feature['id']);
            if (!empty($feature['selectable'])) {
                $selectable_features[$code] = $feature;
            }
        }
        $selectable_features = $feature_model->getValues($selectable_features);
        foreach ($selectable_features as $code => $feature) {
            if (isset($features[$code]) && isset($feature['values'])) {
                $features[$code]['values'] = $feature['values'];
            }
        }

        return $features;
    }

    /**
     * @param array $products
     * @param shopProductsCollection $collection
     * @return void
     */
    protected function formatProducts(&$products, $collection)
    {
        $collection_info = $collection->getInfo();

        $product_tags = $product_sets = null;

        $product_keys = array_keys($products);

        $product_type_ids = array_unique(array_column($products, 'type_id'));
        $types = $type_feature_ids = [];
        if ($product_type_ids) {
            $type_model = new shopTypeModel();
            $types = $type_model->getByField(['id' => $product_type_ids], 'id');
            $type_features_model = new shopTypeFeaturesModel();
            $type_features = $type_features_model->select('type_id, feature_id')->where('type_id IN (?)', [$product_type_ids])->fetchAll();
            foreach ($type_features as $type_feature) {
                $type_feature_ids[$type_feature['type_id']][$type_feature['feature_id']] = $type_feature['feature_id'];
            }
        }

        $params_model = new shopProductParamsModel();
        $params = $params_model->getByField('product_id', $product_keys, true);
        $formatted_params = [];
        $products_params = [];
        foreach ($params as $param) {
            $products_params[$param['product_id']][$param['name']] = $param['value'];
            if ($param['name'] != 'order' && $param['name'] != 'multiple_sku') {
                $formatted_params[$param['product_id']][] = $param['name'] . '=' . $param['value'];
            }
        }

        $feature_ids = [];
        $columns_list = $this->getColumnsList();
        $enabled_columns = $this->getEnabledColumns();
        foreach ($enabled_columns as $column) {
            if (isset($columns_list[$column['column_type']]['feature_id'])) {
                $feature_ids[] = $columns_list[$column['column_type']]['feature_id'];
            }
        }
        $feature_model = new shopFeatureModel();
        $features = $feature_model->getById($feature_ids);
        $all_features = self::addSelectableValues($features);
        $formatted_features = shopProdSkuAction::formatFeatures($all_features);

        $features_selectable_model = new shopProductFeaturesSelectableModel();
        $features_selectable = [];
        if ($product_keys) {
            $features_selectable = $features_selectable_model->select('product_id, `feature_id`')->where('product_id IN (?)', [$product_keys])->fetchAll('feature_id');
        }

        $sku_formatted_features = [];
        foreach ($formatted_features as $feature) {
            $sku_formatted_features[] = shopProdSkuAction::formatModificationFeature($feature);
        }
        $product_features_model = new shopProductFeaturesModel();
        $limit_precision = !shopFrac::isEnabled() ? 0 : null;

        $product_categories = $this->getRelatedData($product_keys, 'categories');
        $categories_by_product = [];
        foreach ($product_categories as $product_id => $values) {
            $categories_by_product[$product_id] = array_column($values, 'value');
        }

        foreach ($products as &$p) {
            if (!isset($p['skus'][$p['sku_id']])) {
                // fix broken products
                $p['sku_id'] = (string)key($p['skus']);
            }
            if (isset($collection_info['main_filter_type']) && $collection_info['main_filter_type'] == 'category'
                && $collection_info['main_filter_data']['type'] == shopCategoryModel::TYPE_STATIC
                && $collection_info['main_filter_data']['include_sub_categories']
                && isset($categories_by_product[$p['id']]) && !in_array($collection_info['main_filter_data']['id'], $categories_by_product[$p['id']])
            ) {
                $p['product_from_subcategory'] = true;
            } else {
                $p['product_from_subcategory'] = false;
            }

            $product_features_values = $product_features_model->getValues($p['id']);
            $skus_features_values = $product_features_model->getValuesMultiple($all_features, $p['id'], array_keys($p['skus']));
            $p['columns'] = [];
            foreach ($enabled_columns as $col) {
                if (isset($columns_list[$col['column_type']])) {
                    $active_column = $columns_list[$col['column_type']];
                    if (isset($active_column['feature_id'])) {
                        $p['columns'][$active_column['id']] = [];
                        foreach ($formatted_features as $key => $formatted_feature) {
                            if ($formatted_feature['id'] == $active_column['feature_id']) {
                                $active_column['sortable'] = empty($active_column['multiple'])
                                    && $feature['type'] != shopFeatureModel::TYPE_COLOR
                                    && !isset($features_selectable[$active_column['feature_id']]);
                                $sku_features = [];
                                $feature_missing_in_type = !isset($type_feature_ids[$p['type_id']][$formatted_feature['id']]);
                                foreach ($p['skus'] as $sku) {
                                    $can_be_edited = 'yes';
                                    if ($feature_missing_in_type) {
                                        $can_be_edited = isset($skus_features_values[$sku['id']][$formatted_feature['code']]) ? 'partial' : 'no';
                                    }
                                    $sku_features[$sku['id']] = [
                                            'sku_id' => $sku['id'],
                                            'can_be_edited' => $can_be_edited,
                                        ] + self::formatFeaturesValues($sku_formatted_features[$key], $skus_features_values[$sku['id']]);
                                }
                                $can_be_edited = 'yes';
                                if ($feature_missing_in_type) {
                                    if ($formatted_feature['selectable'] && $formatted_feature['available_for_sku'] && count($p['skus']) > 1) {
                                        $can_be_edited = isset($skus_features_values[$sku['id']][$formatted_feature['code']]) ? 'partial' : 'no';
                                    } else {
                                        $can_be_edited = isset($product_features_values[$formatted_feature['code']]) ? 'partial' : 'no';
                                    }
                                }
                                $p['columns'][$active_column['id']] = [
                                        'skus' => $sku_features,
                                        'can_be_edited' => $can_be_edited,
                                    ] + self::formatFeaturesValues($formatted_feature, $product_features_values);
                            }
                        }
                    } elseif (in_array($active_column['id'], ['stock_unit_id', 'base_unit_id', 'order_multiplicity_factor'])) {
                        $p['columns'][$active_column['id']] = [
                            'render_type' => 'text',
                            'value' => $this->getFractionalValue($active_column['id'], $types[$p['type_id']], $p)
                        ];
                    } elseif ($active_column['id'] === 'stock_base_ratio') {
                        $sku_fractional = [];
                        foreach ($p['skus'] as $sku) {
                            $sku_fractional[$sku['id']] = [
                                    'sku_id' => $sku['id'],
                                ] + $this->getFractionalValue($active_column['id'], $types[$p['type_id']], $p, $sku);
                        }

                        $p['columns'][$active_column['id']] = [
                                'render_type' => 'input',
                                'skus' => $sku_fractional,
                            ] + $this->getFractionalValue($active_column['id'], $types[$p['type_id']], $p);

                    } elseif (in_array($active_column['id'], ['order_count_min', 'order_count_step'])) {
                        $sku_fractional = [];
                        foreach ($p['skus'] as $sku) {
                            $sku_fractional[$sku['id']] = [
                                'sku_id' => $sku['id'],
                                'value' => $this->getFractionalValue($active_column['id'], $types[$p['type_id']], $p, $sku)
                            ];
                        }

                        $p['columns'][$active_column['id']] = [
                            'render_type' => 'text',
                            'skus' => $sku_fractional,
                            'value' => $this->getFractionalValue($active_column['id'], $types[$p['type_id']], $p)
                        ];

                    } elseif ($active_column['id'] == 'visibility') {
                        $skus_visibility = [];
                        foreach ($p['skus'] as $sku) {
                            $skus_visibility[$sku['id']] = [
                                'sku_id' => $sku['id'],
                                'available' => $sku['available'],
                                'status' => $sku['status'],
                            ];
                        }
                        $p['columns'][$active_column['id']] = [
                            'render_type' => 'checkbox',
                            'sku_id' => $p['sku_id'],
                            'skus' => $skus_visibility,
                        ];

                    } elseif ($active_column['id'] == 'badge') {
                        $p['columns'][$active_column['id']] = [
                            'render_type' => 'text',
                            'value' => $p[$active_column['id']]
                        ];
                    } else {
                        $render_type = $active_column['editable'] ? 'input' : 'text';
                        switch ($active_column['type']) {
                            case 'char':
                            case 'varchar':
                            case 'mediumtext':
                            case 'text':
                                if ($active_column['id'] == 'currency') { $render_type = 'select'; }
                                $p['columns'][$active_column['id']] = $this->fillDefaultValue($render_type, $active_column['id'], $p);
                                break;
                            case 'datetime':
                            case 'edit_datetime':
                            case 'create_datetime':
                                $p['columns'][$active_column['id']] = [
                                    'render_type' => 'date',
                                    'value' => wa_date('humandatetime', $p[$active_column['id']])
                                ];
                                break;
                            case 'decimal':
                                if (in_array($active_column['id'], ['price', 'compare_price', 'purchase_price', 'total_sales'])) {
                                    $_currency = $p['currency'];
                                    if ($active_column['id'] === 'total_sales') {
                                        $_currency = $this->shop_currency;
                                    }
                                    if ($active_column['id'] == 'total_sales') {
                                        $value = $this->fillProductColumn( $active_column['id'], $p);
                                    } else {
                                        $value = $this->fillProductColumn( $active_column['id'], $p['skus'][$p['sku_id']]);
                                    }
                                    if ($active_column['editable']) {
                                        $p['columns'][ $active_column['id'] ] = [
                                            'render_type' => 'price',
                                            'value'       => shop_number_format($value),
                                            'currency'    => waCurrency::getInfo( $_currency ),
                                            'skus'        => $this->fillSkusColumn( $active_column['id'], $p['skus'] ),
                                        ];
                                    } else {
                                        $skus_data = [];
                                        $skus_values = $this->fillSkusColumn( $active_column['id'], $p['skus']);
                                        foreach ($skus_values as $sku) {
                                            $_sku_price = (!empty($sku['value']) ? $sku['value'] : 0);
                                            $_sku_price_html = shopViewHelper::formatPrice($_sku_price, ['currency' => $_currency]);
                                            $skus_data[$sku['id']] = [
                                                'sku_id' => $sku['id'],
                                                'value' => $_sku_price_html
                                            ];
                                        }

                                        $_price_html = shopViewHelper::formatPrice($value, ['currency' => $_currency]);
                                        $p['columns'][$active_column['id']] = [
                                            'render_type' => 'html',
                                            'value' => $_price_html,
                                            'skus' => $skus_data,
                                        ];
                                    }
                                } elseif (in_array($active_column['id'], ['stock_worth', 'sales_30days'])) {
                                    $_price = $this->fillProductColumn($active_column['id'], $p);
                                    $_price_html = shopViewHelper::formatPrice($_price, ['currency' => $this->shop_currency]);
                                    $p['columns'][$active_column['id']] = [
                                        'render_type' => 'html',
                                        'value' => $_price_html
                                    ];
                                } elseif ($active_column['id'] == 'rating') {
                                    $p['columns'][$active_column['id']] = [
                                        'render_type' => 'rating',
                                        'value' => round($p['rating'] / 0.5) * 0.5,
                                    ];
                                } elseif ($active_column['id'] == 'count') {
                                    if (isset($p['count'])) {
                                        $count_value = 0;
                                        foreach ($p['skus'] as $sku) {
                                            if (isset($sku['count'])) {
                                                $count_value += $sku['count'];
                                            }
                                        }
                                        $count_value = shop_number_format($count_value, $limit_precision, null, null);
                                    } else {
                                        $count_value = '';
                                    }
                                    $p['columns'][$active_column['id']] = [
                                        'render_type' => 'stock',
                                        'value' => $count_value,
                                    ];
                                    if ($this->getField('view') == self::VIEW_TABLE_EXTENDED) {
                                        $skus_data = [];
                                        foreach ($p['skus'] as $sku) {
                                            $sku_stock_value = '';
                                            if ($sku['stock']) {
                                                foreach ($sku['stock'] as $stock_value) {
                                                    if (!isset($stock_value)) {
                                                        $sku_stock_value = '';
                                                        break;
                                                    }
                                                    if ($sku_stock_value === '') {
                                                        $sku_stock_value = 0;
                                                    }
                                                    $sku_stock_value += shopFrac::discardZeros($stock_value);
                                                }
                                            } elseif (isset($sku['count'])) {
                                                $sku_stock_value = shopFrac::discardZeros($sku['count']);
                                            }
                                            if ($sku_stock_value !== '') {
                                                $sku_stock_value = shop_number_format($sku_stock_value, $limit_precision, null, null);
                                            }
                                            $skus_data[$sku['id']] = [
                                                'sku_id' => $sku['id'],
                                                'value' => $sku_stock_value
                                            ];
                                        }
                                        $p['columns'][$active_column['id']]['skus'] = $skus_data;
                                    }
                                } elseif (strpos($active_column['id'], 'stocks_') === 0) {
                                    $skus_data = [];
                                    $stock_sum = 0;
                                    foreach ($p['skus'] as $sku) {
                                        $sku_stock_value = '';
                                        if ($sku['stock']) {
                                            if (isset($active_column['substocks'])) {
                                                // if it is a virtual stock
                                                foreach ($active_column['substocks'] as $stock_id) {
                                                    if (isset($sku['stock'][$stock_id])) {
                                                        if ($sku_stock_value === '') {
                                                            $sku_stock_value = 0;
                                                        }
                                                        $sku_stock_value += shopFrac::discardZeros($sku['stock'][$stock_id]);
                                                    } else {
                                                        $sku_stock_value = '';
                                                        break;
                                                    }
                                                }
                                            } elseif (isset($sku['stock'][$active_column['stock_id']])) {
                                                $sku_stock_value = shopFrac::discardZeros($sku['stock'][$active_column['stock_id']]);
                                            }
                                        }
                                        if ($sku_stock_value !== '' && $stock_sum !== null) {
                                            $stock_sum += $sku_stock_value;
                                        } else {
                                            $stock_sum = null;
                                        }
                                        if ($sku_stock_value !== '') {
                                            if ($active_column['editable']) {
                                                $sku_stock_value = shop_number_format($sku_stock_value, $limit_precision, null, null);
                                            } else {
                                                $sku_stock_value = shop_number_format($sku_stock_value, $limit_precision);
                                            }
                                        }
                                        $skus_data[$sku['id']] = [
                                            'sku_id' => $sku['id'],
                                            'value' => $sku_stock_value
                                        ];
                                    }

                                    $p['columns'][$active_column['id']] = [
                                        'render_type' => 'stock',
                                        'value' => $stock_sum,
                                        'stock_id' => $active_column['stock_id'],
                                        'skus' => $skus_data,
                                    ];
                                } elseif ($active_column['id'] === 'base_price') {

                                    $_format = null;
                                    $_setting_value = ifset($col, 'data', 'settings', 'format', 'origin');
                                    switch($_setting_value) {
                                        case 'int':
                                            $_format = '%0';
                                            break;
                                        case 'float':
                                            $_format = '%2';
                                            break;
                                        case 'origin':
                                            $_format = '%4t';
                                            break;
                                    }

                                    $skus_data = [];
                                    $_product_value = '';

                                    $base_prices = [];
                                    foreach ($p['skus'] as $sku) {
                                        $sku_base_price = $sku['price'] / ifempty($sku['stock_base_ratio'], $p['stock_base_ratio']);
                                        if (!empty($p['base_unit_id']) && ($p['stock_unit_id'] !== $p['base_unit_id']) && !empty($this->units[$p['base_unit_id']])) {
                                            $base_prices[] = $sku_base_price;
                                            $base_unit = $this->units[$p['base_unit_id']];
                                            $_sku_value = shopViewHelper::formatPrice($sku_base_price, ['currency' => $p['currency'], 'unit' => $base_unit['name_short'], 'format' => $_format]);
                                        } else {
                                            $_sku_value = '';
                                        }

                                        $skus_data[$sku['id']] = [
                                            'sku_id' => $sku['id'],
                                            'value' => $_sku_value
                                        ];
                                    }

                                    if ($base_prices) {
                                        $min_base_price = min($base_prices);
                                        $max_base_price = max($base_prices);
                                        if ($min_base_price == $max_base_price) {
                                            $_product_value = shopViewHelper::formatPrice($min_base_price, ['currency' => $p['currency'], 'unit' => $base_unit['name_short'], 'format' => $_format]);
                                        } else {
                                            $_product_value = sprintf('<span class="price-wrapper"><span class="price">%s</span> ... %s</span>', waCurrency::format($_format, $min_base_price, $p['currency']),
                                                shopViewHelper::formatPrice($max_base_price, ['currency' => $p['currency'], 'unit' => $base_unit['name_short'], 'format' => $_format, 'wrap' => false]));
                                        }
                                    }
                                    $p['columns'][$active_column['id']] = [
                                        'render_type' => 'html',
                                        'value' => $_product_value,
                                        'skus' => $skus_data
                                    ];
                                } else {
                                    $p['columns'][$active_column['id']] = $this->fillDefaultValue($render_type, $active_column['id'], $p);
                                }
                                break;
                            case 'int':
                            case 'tinyint':
                                if ($active_column['id'] == 'status') {
                                    $redirect_code = isset($products_params[$p['id']]['redirect_code']) ? $products_params[$p['id']]['redirect_code'] : null;
                                    $redirect_category_id = isset($products_params[$p['id']]['redirect_category_id']) ? $products_params[$p['id']]['redirect_category_id'] : null;
                                    $redirect_url = isset($products_params[$p['id']]['redirect_url']) ? $products_params[$p['id']]['redirect_url'] : null;

                                    if (empty($redirect_code)) {
                                        $redirect_type = '404';
                                    } else if (empty($redirect_url) && empty($redirect_category_id)) {
                                        $redirect_type = 'home';
                                    } else if (!empty($redirect_category_id)) {
                                        $redirect_type = 'category';
                                    } else {
                                        $redirect_type = 'url';
                                    }

                                    $p['columns'][$active_column['id']] = [
                                        'render_type' => 'select',
                                        'value' => $p[$active_column['id']],
                                        'options' => [],
                                        'category_id' => isset($products_params[$p['id']]['redirect_category_id']) ? $products_params[$p['id']]['redirect_category_id'] : null,
                                        'redirect' => [
                                            'type' => $redirect_type,
                                            'code' => !empty($redirect_code) ? $redirect_code : '302',
                                            'url' => !empty($redirect_url) ? $redirect_url : ''
                                        ]
                                    ];
                                } elseif ($active_column['id'] == 'tax_id') {
                                    $p['columns'][$active_column['id']] = [
                                        'render_type' => 'select',
                                        'value' => $p['tax_id'],
                                    ];
                                } elseif ($active_column['id'] == 'type_id') {
                                    $p['columns'][$active_column['id']] = [
                                        'render_type' => 'select',
                                        'value' => $p['type_id'],
                                    ];
                                } elseif ($active_column['id'] == 'category_id') {
                                    $p['columns'][$active_column['id']] = [
                                        'render_type' => 'select',
                                        'value' => $p['category_id'],
                                    ];
                                } elseif ($active_column['id'] == 'tags') {
                                    if ($product_tags === null) {
                                        $product_tags = $this->getRelatedData($product_keys, 'tags');
                                    }
                                    $p['columns'][$active_column['id']] = [
                                        'render_type' => 'tags',
                                        'options' => isset($product_tags[$p['id']]) ? $product_tags[$p['id']] : [],
                                    ];
                                } elseif ($active_column['id'] == 'sets') {
                                    if ($product_sets === null) {
                                        $product_sets = $this->getRelatedData($product_keys, 'sets');
                                    }
                                    $p['columns'][$active_column['id']] = [
                                        'render_type' => 'tags',
                                        'options' => isset($product_sets[$p['id']]) ? $product_sets[$p['id']] : [],
                                    ];
                                } elseif ($active_column['id'] == 'categories') {
                                    $additional_categories = [];
                                    if (isset($product_categories[$p['id']])) {
                                        foreach ($product_categories[$p['id']] as $data) {
                                            if ($data['value'] != $p['category_id']) {
                                                $additional_categories[] = $data;
                                            }
                                        }
                                    }
                                    $p['columns'][$active_column['id']] = [
                                        'render_type' => 'tags',
                                        'options' => $additional_categories,
                                    ];
                                } elseif ($active_column['id'] == 'params') {
                                    $product_params = isset($formatted_params[$p['id']]) ? $formatted_params[$p['id']] : [];
                                    $p['columns'][$active_column['id']] = [
                                        'render_type' => 'textarea',
                                        'value' => implode(PHP_EOL, $product_params),
                                    ];
                                } elseif ($active_column['id'] == 'sku_type') {
                                    $p['columns'][$active_column['id']] = [
                                        'render_type' => 'text',
                                        'value' => $p[$active_column['id']],
                                    ];
                                } else {
                                    $p['columns'][$active_column['id']] = $this->fillDefaultValue($render_type, $active_column['id'], $p);
                                }
                                break;
                            default:
                                $p['columns'][$active_column['id']] = $this->fillDefaultValue('text', $active_column['id'], $p);
                                break;
                        }
                    }
                    $p['columns'][$active_column['id']]['editable'] = $active_column['editable'];
                    $p['columns'][$active_column['id']]['settings'] = ifset($col, 'data', 'settings', []);
                    if (!isset($p['columns'][$active_column['id']]['id'])) {
                        $p['columns'][$active_column['id']]['id'] = $active_column['id'];
                    }
                    if (!isset($p['columns'][$active_column['id']]['original_type'])) {
                        $p['columns'][$active_column['id']]['original_type'] = $active_column['type'];
                    }

                    /**
                     * Удаляем неиспользуемые на фронте ключи, чтобы уменьшить кол-во данных, передаваемых в JS (оптимизация)
                     */
                    $delete_names = ['selectable', 'multiple', 'units', 'type', 'original_type', 'available_for_sku', 'visible_in_frontend'];
                    $data = $p['columns'][$active_column['id']];
                    foreach ($delete_names as $name) {
                        if (isset($data[$name])) {
                            unset($data[$name]);
                        }
                    }
                    if (!empty($data['skus'])) {
                        foreach ($data['skus'] as &$sku) {
                            foreach ($delete_names as $name) {
                                if (isset($sku[$name])) {
                                    unset($sku[$name]);
                                }
                            }
                        }
                    }
                    unset($delete_names);
                    unset($data);
                    unset($name);
                    unset($sku);
                }
            }
        }
        unset($p);

        // TODO: hook that allows plugins to modify $products
    }

    /**
     * @param int $filter_id
     * @return void
     * @throws waException
     */
    protected function updateFilterId($filter_id)
    {
        if (!$this->isTemplate() && $this->getFilterId() != $filter_id) {
            $this->presentation['filter_id'] = $filter_id;
            $this->getModel()->updateById($this->getId(), ['filter_id' => $filter_id]);
        }
    }

    /**
     * @param bool $reset_rules
     * @param array $data
     * @return void
     * @throws waException
     */
    protected function replaceFilterRules($reset_rules, $data)
    {
        $replaced = false;
        foreach ($data as $type => $id) {
            if ($id !== null) {
                $value = null;
                if ($type != 'tags') {
                    $value = shopFilter::validateValue([$id], $type);
                } elseif (mb_strlen($id)) {
                    $value = $id;
                }
                if ($value) {
                    if ($type == 'categories') {
                        $data_model = new shopCategoryModel();
                    } elseif ($type == 'sets') {
                        $data_model = new shopSetModel();
                    } elseif ($type == 'types') {
                        $data_model = new shopTypeModel();
                    } else {
                        $data_model = new shopTagModel();
                    }
                    if ($type != 'tags') {
                        $row = $data_model->getById($value[0]);
                    } else {
                        $row = $data_model->getByField('name', $value);
                        if (isset($row['id'])) {
                            $value = [$row['id']];
                        }
                    }
                    if ($row) {
                        $filter = $this->getFilter();
                        $rules_model = new shopFilterRulesModel();
                        $rules_model->deleteByField('filter_id', $filter->getId());
                        $rules_model->insert([
                            'filter_id' => $filter->getId(),
                            'rule_type' => $type,
                            'rule_params' => $value[0],
                        ]);
                        $replaced = true;
                    }
                }
                break;
            }
        }

        if (!$replaced && $reset_rules) {
            $filter = $this->getFilter();
            $rules_model = new shopFilterRulesModel();
            $rules_model->deleteByField('filter_id', $filter->getId());
        }
    }


    /**
     * @param array $presentation
     * @param array $template
     * @return bool
     */
    protected function isCopyTemplate($presentation, $template)
    {
        $is_copy_template = true;

        if ($presentation && $template) {
            if ($presentation['rows_on_page'] != $template['rows_on_page']
                || count($presentation['columns']) != count($template['columns'])
            ) {
                $is_copy_template = false;
            } else {
                foreach ($presentation['columns'] as $key => $column) {
                    if (!isset($template['columns'][$key])) {
                        $is_copy_template = false;
                        break;
                    } else {
                        foreach (['column_type', 'width', 'data', 'sort'] as $column_field) {
                            if ($column[$column_field] != $template['columns'][$key][$column_field]
                            ) {
                                $is_copy_template = false;
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        return $is_copy_template;
    }

    protected function fillDefaultValue($type, $field_id, $product)
    {
        return [
            'render_type' => $type,
            'value' => $this->fillProductColumn($field_id, $product),
            'skus' => $this->fillSkusColumn($field_id, $product['skus']),
        ];
    }

    protected function fillProductColumn($field_id, $product)
    {
        return isset($product[$field_id]) ? $product[$field_id] : null;
    }

    protected function fillSkusColumn($field_id, $skus)
    {
        if (strpos($field_id, 'skus_') === 0) {
            $field_id = substr($field_id, 5);
        }
        $data = [];
        foreach ($skus as $sku) {
            $value = null;
            if ($field_id === 'sku' || $field_id === 'name') {
                $value = $sku[$field_id];
            } elseif (isset($sku[$field_id])) {
                $value = shop_number_format($sku[$field_id]);
            }

            $data[$sku['id']] = [
                'id' => $sku['id'],
                'value' => $value
            ];
        }
        return $data;
    }

    /**
     * @param string $field
     * @param array $type
     * @param array $product
     * @param array $sku
     * @return string
     */
    protected function getFractionalValue($field, $type, $product, $sku = [])
    {
        $units = $this->units;

        $result = '';

        $stock_unit = null;
        $stock_unit_id = $product['stock_unit_id'];
        if (!empty($units[$stock_unit_id])) {
            $stock_unit = $units[$stock_unit_id];
        }

        $type_fractional = shopSettingsTypefeatTypeEditAction::getTypeFractional($type);

        switch ($field) {
            case 'stock_unit_id':
                if (!empty($stock_unit)) {
                    $result = $stock_unit['name'];
                } else {
                    $result = _w('Not specified');
                }
                break;

            case 'base_unit_id':
                $base_unit_id = $product['base_unit_id'];
                if ($product['stock_unit_id'] !== $product['base_unit_id'] && !empty($units[$base_unit_id])) {
                    $base_unit = $units[$base_unit_id];
                    $result = $base_unit['name'];
                } else {
                    $result = _w('Not specified');
                }
                break;

            case 'stock_base_ratio':
                $base_unit_id = $product['base_unit_id'];

                $stock_base_ratio = '';
                if (!empty($sku)) {
                    if (!empty($sku['stock_base_ratio'])) {
                        $stock_base_ratio = $sku['stock_base_ratio'];
                    }
                } else {
                    if (!empty($product['stock_base_ratio'])) {
                        $stock_base_ratio = $product['stock_base_ratio'];
                    } else {
                        $stock_base_ratio = $type_fractional['stock_base_ratio']['value'];
                    }
                }

                if ($product['stock_unit_id'] !== $product['base_unit_id'] &&
                    !empty($product['stock_base_ratio']) && !empty($units[$base_unit_id])
                ) {

                    $base_unit = $units[$base_unit_id];
                    if ($stock_base_ratio !== '') {
                        $stock_base_ratio = shop_number_format($stock_base_ratio);
                        if ($stock_base_ratio <= 0) {
                            $stock_base_ratio = 1;
                        }
                    }
                    $stock_base_ratio_text = shop_number_format($stock_base_ratio, [
                        'limit_precision' => 3,
                        'thousands_separator' => ' ',
                        'decimal_separator' => null
                    ]);

                    $result = [
                        'text' => sprintf(_w('1 %s'), ifset($stock_unit, 'name_short', '')) . ' = '. $stock_base_ratio_text . ' ' . $base_unit['name_short'],
                        'text_left_part' => sprintf(_w('1 %s'), ifset($stock_unit, 'name_short', '')). ' = ',
                        'text_right_part' => $base_unit['name_short'],
                        'value' => $stock_base_ratio
                    ];
                } else {
                    $result = [
                        'value' => null
                    ];
                }
                break;

            case 'order_multiplicity_factor':
                $order_multiplicity_factor = $type_fractional['order_multiplicity_factor']['value'];
                if (!empty($product['order_multiplicity_factor'])) {
                    $order_multiplicity_factor = shop_number_format($product['order_multiplicity_factor'], 3, null, null);
                }
                $result = $order_multiplicity_factor . ' ' . ifset($stock_unit, 'name_short', '');
                break;

            case 'order_count_min':
                $order_count_min = '';
                if (!empty($sku)) {
                    if (!empty($sku['order_count_min'])) {
                        $order_count_min = shop_number_format($sku['order_count_min'], 3, null, null);
                    }
                } else {
                    if (!empty($product['order_count_min'])) {
                        $order_count_min = shop_number_format($product['order_count_min'], 3,  null, null);
                    } else {
                        $order_count_min = $type_fractional['order_count_min']['value'];
                    }
                }
                $result = $order_count_min !== '' ? $order_count_min . ' ' . ifset($stock_unit, 'name_short', '') : '';
                break;

            case 'order_count_step':
                $order_count_step = '';
                if (!empty($sku)) {
                    if (!empty($sku['order_count_step'])) {
                        $order_count_step = shop_number_format($sku['order_count_step'], 3, null, null);
                    }
                } else {
                    if (!empty($product['order_count_step'])) {
                        $order_count_step = shop_number_format($product['order_count_step'], 3, null, null);
                    } else {
                        $order_count_step = $type_fractional['order_count_step']['value'];
                    }
                }
                $result = $order_count_step !== '' ? $order_count_step . ' ' . ifset($stock_unit, 'name_short', '') : '';
                break;
        }

        return $result;
    }

    /**
     * @param array $feature
     * @param array $values
     * @return array
     */
    protected static function formatFeaturesValues($feature, $values)
    {
        $format = 'number';
        if (in_array($feature['type'], ['varchar', 'text', 'boolean', 'color', 'date', 'range.date'])) {
            $format = 'text';
        }
        $feature['format'] = $format;

        switch ($feature['render_type']) {
            case 'select':
                if (isset($values[$feature['code']])) {
                    $_feature_value = $values[$feature['code']];

                    if ($_feature_value instanceof shopBooleanValue) {
                        $_active_value = (string)$values[$feature['code']]['value'];
                    } else {
                        $_active_value = (string)$_feature_value;
                    }

                    foreach ($feature['options'] as $_option) {
                        if ($_option['value'] === $_active_value) {
                            $feature['value'] = $_active_value;
                            $feature['active_option'] = $_option;
                            break;
                        }
                    }
                }
                unset($feature['options']);
                break;

            case 'checkbox':
                $_active_array = [];
                $_is_array = false;
                if (!empty($values[$feature['code']])) {
                    $_feature_value = $values[$feature['code']];
                    if (is_array($_feature_value)) {
                        foreach ($_feature_value as $_value) {
                            $_active_array[] = (string)$_value;
                        }
                        $_is_array = true;
                    } else {
                        $_active_array[] = (string)$_feature_value;
                    }
                }

                $feature['value'] = $_active_array;
                /*
                $_active_option = null;

                foreach ($feature['options'] as &$option) {
                    $_is_active = in_array($option['value'], $_active_array);
                    $option['active'] = $_is_active;
                    if (!$_is_array && $_is_active) {
                        $_active_option = $option;
                    }
                }

                if (!$_is_array) {
                    $feature['active_option'] = ($_active_option ? $_active_option : reset( $feature['options'] ) );
                }
                */
                unset($feature['units']);
                unset($feature['options']);
                unset($feature['active_option']);
                break;

            case 'textarea':
                $_feature_value = ifset($values, $feature['code'], '');
                $feature['value'] = $_feature_value;
                break;

            case 'field':
                if (isset($values[$feature['code']])) {
                    $_feature_value = $values[$feature['code']];

                    if ($_feature_value instanceof shopDimensionValue) {
                        // dimension: one value with measurement unit
                        $feature['options'][0]['value'] = (string)$_feature_value['value'];
                        $_unit_value = (string)$_feature_value['unit'];
                        foreach ($feature['units'] as $_unit) {
                            if ($_unit['value'] === $_unit_value) {
                                $feature['active_unit'] = $_unit;
                                break;
                            }
                        }

                    } else if ($_feature_value instanceof shopCompositeValue) {
                        // composite dimension (N x N x N): several values with measurement unit
                        $fields_count = 3;
                        if ('2d' === substr($feature['type'], 0, 2)) {
                            $fields_count = 2;
                        }
                        for ($i = 0; $i < $fields_count; $i++) {
                            if (isset($_feature_value[$i])) {
                                $_subvalue = $_feature_value[$i];
                                if ($_subvalue instanceof shopDimensionValue) {
                                    $feature['options'][$i]['value'] = (string)$_subvalue['value'];
                                } else {
                                    $feature['options'][$i]['value'] = (string)$_subvalue;
                                }
                            }
                        }

                        if (!empty($_feature_value['0']['unit'])) {
                            $_unit_value = (string)$_feature_value[0]['unit'];
                            foreach ($feature['units'] as $_unit) {
                                if ($_unit['value'] === $_unit_value) {
                                    $feature['active_unit'] = $_unit;
                                    break;
                                }
                            }
                        }

                    } else {
                        // single value without measurement unit
                        $feature['options'][0]['value'] = (string)$_feature_value;
                    }
                }
                unset($feature['units']);
                break;

            case 'field.date':
                if (!empty($values[$feature['code']])) {
                    $_feature_value = $values[ $feature['code'] ];
                    if ( $_feature_value instanceof shopDateValue ) {
                        if ( !empty($_feature_value['timestamp']) ) {
                            $_date = date( 'Y-m-d', $_feature_value['timestamp'] );
                            $feature['options'][0]['value'] = (string) $_date;
                        }
                    }
                }
                break;

            case 'color':
                if (!empty($values[$feature['code']])) {
                    $_feature_value = $values[ $feature['code'] ];
                    if ($_feature_value instanceof shopColorValue) {
                        if ( !empty($_feature_value['value']) ) {
                            $feature['options'][0]['value'] = (string)$_feature_value['value'];
                        }
                        if ( !empty($_feature_value['code']) ) {
                            $feature['options'][0]['code'] = $_feature_value['hex'];
                        } else {
                            $feature['options'][0]['code'] = '#000000';
                        }
                    }
                }
                break;

            case 'range':
                if (!empty($values[$feature['code']])) {
                    $_feature_value = $values[$feature['code']];
                    $_unit_value = null;

                    if ($_feature_value instanceof shopRangeValue) {
                        if ( !empty($_feature_value['begin']) ) {
                            if ($_feature_value['begin'] instanceof shopDimensionValue) {
                                $feature['options'][0]['value'] = (string)$_feature_value['begin']['value'];
                                $_unit_value = (string)$_feature_value['begin']['unit'];
                            } else {
                                $feature['options'][0]['value'] = (string)$_feature_value['begin'];
                            }
                        }

                        if ( !empty($_feature_value['end']) ) {
                            if ($_feature_value['end'] instanceof shopDimensionValue) {
                                $feature['options'][1]['value'] = (string)$_feature_value['end']['value'];
                                $_unit_value = (string)$_feature_value['end']['unit'];
                            } else {
                                $feature['options'][1]['value'] = (string)$_feature_value['end'];
                            }
                        }
                    }

                    if (!empty($_unit_value)) {
                        foreach ($feature['units'] as $_unit) {
                            if ($_unit['value'] === $_unit_value) {
                                $feature['active_unit'] = $_unit;
                                break;
                            }
                        }
                    }
                }
                unset($feature['units']);
                break;

            case 'range.date':
                if (!empty($values[$feature['code']])) {
                    $_feature_value = $values[$feature['code']];
                    if ($_feature_value instanceof shopRangeValue) {
                        if (!empty($_feature_value['begin']['timestamp'])) {
                            $_start_date = date('Y-m-d', $_feature_value['begin']['timestamp']);
                            $feature['options'][0]['value'] = (string)$_start_date;
                        }
                        if (!empty($_feature_value['end']['timestamp'])) {
                            $_end_date = date( 'Y-m-d', $_feature_value['end']['timestamp'] );
                            $feature['options'][1]['value'] = (string) $_end_date;
                        }
                    }
                }
                break;
        }
        return $feature;
    }

    /**
     * @param $additional_fields
     * @return string
     */
    protected function getCollectionFields($additional_fields = [])
    {
        $fields = ['*', 'skus', 'image', 'images', 'skus_image', 'stock_counts'];
        foreach ($this->getEnabledColumns() as $col) {
            if (in_array($col['column_type'],
                array_merge(['image_crop_small', 'image_count', 'sales_30days', 'stock_worth'], $additional_fields))
            ) {
                $fields[] = $col['column_type'];
            }
        }

        return join(',', array_unique($fields));
    }

    protected function getUnits()
    {
        $unit_model = new shopUnitModel();
        $units = $unit_model->getAll('id');
        $enabled_units = [];
        foreach ($units as $unit) {
            if ($unit['status'] !== '0') {
                $short_name = (!empty($unit['storefront_name']) ? $unit['storefront_name'] : $unit['short_name']);
                $enabled_units[$unit['id']] = [
                    'value' => (string)$unit['id'],
                    'name' => $unit['name'],
                    'name_short' => $short_name
                ];
            }
        }

        return $enabled_units;
    }

    /**
     * Get tags, sets and categories
     * @param int|array $product_id
     * @param string $table
     * @return array
     */
    protected function getRelatedData($product_id, $table)
    {
        if (!$product_id) {
            return [];
        }
        $model = new waModel();
        if ($table == 'categories') {
            $general_table = 'shop_category_products';
            $additional_table = 'shop_category';
            $field = 'category_id';
        } elseif ($table == 'sets') {
            $general_table = 'shop_set_products';
            $additional_table = 'shop_set';
            $field = 'set_id';
        } else {
            $general_table = 'shop_product_tags';
            $additional_table = 'shop_tag';
            $field = 'tag_id';
        }
        $sql = "
            SELECT general_table.product_id, additional_table.id, additional_table.name
            FROM $general_table general_table
            JOIN $additional_table additional_table ON general_table.$field = additional_table.id
            WHERE general_table.product_id IN (i:id)
        ";
        $data = $model->query($sql, ['id' => $product_id])->fetchAll();
        $formatted_data = [];
        foreach ($data as $row) {
            $formatted_data[$row['product_id']][] = [
                'value' => $row['id'],
                'name' => $row['name'],
            ];
        }
        return $formatted_data;
    }

    /**
     * @param $entity = 'presentation' | 'column'
     * @return mixed|shopPresentationColumnsModel|shopPresentationModel
     * @throws waException
     */
    protected function getModel($entity = 'presentation')
    {
        if (empty($models[$entity])) {
            switch ($entity) {
                case 'presentation';
                    $models[$entity] = new shopPresentationModel();
                    break;
                case 'column';
                    $models[$entity] = new shopPresentationColumnsModel();
                    break;
                default:
                    throw new waException('Unknown entity '.$entity);
            }
        }

        return $models[$entity];
    }
}
