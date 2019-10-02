<?php

class shopMarketingPromoSaveController extends waJsonController
{
    /**
     * @var shopPromoModel
     */
    protected $promo_model;

    /**
     * @var shopPromoRoutesModel
     */
    protected $promo_routes_model;

    /**
     * @var shopPromoRulesModel
     */
    protected $promo_rules_model;

    /**
     * @var array
     */
    protected $available_rule_types;

    /**
     * @var array
     */
    protected $promo_data;

    /**
     * @var null|int
     */
    protected $promo_id;

    /**
     * @var array
     */
    protected $storefronts_data;

    /**
     * @var waRequestFileIterator
     */
    protected $file;

    /**
     * Array of rules that the user edited
     * @var array
     */
    protected $edited_rules;

    /**
     * Array of rules that the user created
     * @var array
     */
    protected $new_rules;

    /**
     * @var array
     */
    protected $old_rules = [];

    /**
     * Array of shop_promo_rules identifiers that the user decided to delete.
     * @var array
     */
    protected $delete_rule_ids;

    /**
     * @var array
     */
    protected $promo_rules;

    protected $utm_fields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];

    public function preExecute()
    {
        // Models
        $this->promo_model = new shopPromoModel();
        $this->promo_routes_model = new shopPromoRoutesModel();
        $this->promo_rules_model = new shopPromoRulesModel();

        // Promo
        $this->promo_data = waRequest::post('promo', null, waRequest::TYPE_ARRAY_TRIM);
        $this->promo_id = ifempty($this->promo_data, 'id', null);

        // Rules
        $this->available_rule_types = $this->promo_rules_model->getAvailableTypes();
        if (!empty($this->promo_id)) {
            $this->old_rules = $this->promo_rules_model->getByField('promo_id', $this->promo_id, 'id');
        }
        $this->delete_rule_ids = waRequest::post('delete_rules', [], waRequest::TYPE_ARRAY_TRIM);
        $this->edited_rules = waRequest::post('rules', [], waRequest::TYPE_ARRAY_TRIM);
        $this->new_rules = ifempty($this->edited_rules, 'new', []);
        unset($this->edited_rules['new']);

        // Routes
        $this->storefronts_data = waRequest::post('storefronts', [], waRequest::TYPE_ARRAY_TRIM);

        // Banner
        $this->file = waRequest::file('image');
    }

    public function execute()
    {
        $this->prepareDateTimes();
        $this->validate();
        if (!empty($this->errors)) {
            return $this->errors;
        }

        // Prepare data
        $this->prepare();

        $this->promoBeforeSaveEvent();
        if (!empty($this->errors)) {
            return $this->errors;
        }

        $this->savePromo();
        $this->saveImage();
        $this->saveRules();
        $this->saveRoutes();

        $this->promoSaveEvent();

        $this->response = [
            'id' => $this->promo_id,
        ];
    }

    //
    // Validate
    //

    protected function validate()
    {
        $this->validatePromo();
        $this->validateStorefronts();
        $this->validateImage();
        $this->validateRules();
    }

    protected function validatePromo()
    {
        $required_fields = ['link'];

        foreach ($required_fields as $field) {
            if (empty($this->promo_data[$field]) || (is_scalar($this->promo_data[$field]) && empty(trim($this->promo_data[$field])))) {
                $this->errors[] = [
                    'name' => "promo[{$field}]",
                    'text' => _w('This field is required.'),
                ];
            }
        }
    }

    protected function validateStorefronts()
    {
        $storefronts = $this->getStorefronts(ifempty($this->promo_data, 'id', null));

        if (!empty($this->storefronts_data[shopPromoRoutesModel::FLAG_ALL])) {
            foreach ($storefronts as $s) {
                if ($s['active'] && empty($this->storefronts_data[$s['storefront']])) {
                    $this->storefronts_data[$s['storefront']] = $s['sort'];
                }
            }
        }

        $max_sorts = $this->promo_routes_model->getMaxSorts();
        foreach ($this->storefronts_data as $route => $sort) {
            if ($sort < 0) {
                if (empty($max_sorts[$route])) {
                    $max_sorts[$route] = 0;
                }
                $max_sorts[$route]++;
                $this->storefronts_data[$route] = $max_sorts[$route];
            }
        }
        if (empty($this->storefronts_data)) {
            $this->errors[] = [
                'id'   => 'storefronts',
                'text' => _w('Select at least one storefront.'),
            ];
        }
    }

    protected function validateRules()
    {
        $parse_rule_errors = function ($errors, $type = 'edit') {
            foreach ($errors as $rule_id => $rule_errors) {
                foreach ($rule_errors as $error) {
                    if (!empty($error['field'])) {
                        $error_field = (stripos($error['field'], '[') === 0) ? $error['field'] : "[{$error['field']}]";

                        $field_name = "rules[{$rule_id}][rule_params]{$error_field}";
                        if ($type == 'new') {
                            $field_name = "rules[new][{$rule_id}][rule_params]{$error_field}";
                        }

                        $this->errors[] = [
                            'name' => $field_name,
                            'text' => $error['text'],
                        ];
                    }
                    if (!empty($error['id'])) {
                        $error_rule_name = "rules[{$rule_id}]";
                        if ($type == 'new') {
                            $error_rule_name = "rules[new][{$rule_id}]";
                        }

                        $this->errors[] = [
                            'id'   => (string)$error['id'],
                            'rule' => $error_rule_name,
                            'text' => $error['text'],
                        ];
                    }
                }
            }
        };

        // Edited rules
        $edited_rules_errors = $this->validateRulesList($this->edited_rules);
        $parse_rule_errors($edited_rules_errors, 'edit');

        // New rules
        $new_rules_errors = $this->validateRulesList($this->new_rules);
        $parse_rule_errors($new_rules_errors, 'new');
    }

    protected function validateRulesList($rules)
    {
        $validate_rule = function ($rule_type) {
            // Validate type
            $rule_type_params = ifempty($this->available_rule_types, $rule_type, null);
            if (empty($rule_type_params)) {
                return [
                    'id'    => 'rule_error',
                    'text'  => _w('Unknown tool type'),
                ];
            }

            // Validate limit
            $max_count = (int)ifempty($rule_type_params, 'max_count', 0);
            $pushed_count = (int)ifempty($rule_type_params, 'pushed_count', 0);
            if (!empty($max_count) && !empty($pushed_count) && $pushed_count >= $max_count) {
                return [
                    'id'    => 'rule_error',
                    'text'  => _w('Exceeded allowed number of tools of this type.'),
                ];
            }

            $this->available_rule_types[$rule_type]['pushed_count'] = ++$pushed_count;

            return null;
        };

        $validate_method = function ($rule_type) {
            $part_of_name = '';
            foreach (explode('_', $rule_type) as $part) {
                $part_of_name .= ucfirst($part);
            }

            $method_name = "validate{$part_of_name}Rule";
            return $method_name;
        };

        $errors = [];
        foreach ($rules as $rule_id => $rule) {
            $rule_errors = [];
            $rule_type_error = $validate_rule($rule['rule_type']);
            if (!empty($rule_type_error)) {
                $rule_errors[] = $rule_type_error;
            } else {
                $method_name = $validate_method($rule['rule_type']);
                if (method_exists($this, $method_name)) {
                    $rule_errors = $this->$method_name($rule);
                } else {
                    $params = [
                        'rule'   => $rule,
                        'errors' => &$rule_errors,
                    ];
                    wa('shop')->event('promo_rule_validate', ref($params));
                }
            }

            $errors[$rule_id] = $rule_errors;
        }

        return $errors;
    }

    protected function validateCustomPriceRule($rule)
    {
        $rule_errors = [];

        $custom_prices_is_empty = true;
        if (!empty($rule['rule_params'])) {
            foreach ($rule['rule_params'] as $product_id => $product_data) {
                $product_skus = ifempty($product_data, 'skus', []);
                foreach ($product_skus as $sku_id => $prices) {
                    if (!empty($prices['price']) || !empty($prices['compare_price'])) {
                        $custom_prices_is_empty = false;
                    }
                }
            }
        }

        if ($custom_prices_is_empty) {
            $rule_errors[] = array(
                'id'    => 'rule_error',
                'text'  => _w('Override the price of at least one product.'),
            );
        }

        return $rule_errors;
    }

    protected function validateUtmRule($rule)
    {
        $rule_errors = [];
        $utm_fields = $this->utm_fields;

        $empty_fields = true;
        foreach ($utm_fields as $utm_field) {
            if (!empty($rule['rule_params'][$utm_field])) {
                $empty_fields = false;
                break;
            }
        }

        if ($empty_fields) {
            $rule_errors[] = [
                'field' => $utm_fields[0],
                'text'  => _w('Add at least one UTM tag.'),
            ];
        }

        return $rule_errors;
    }

    protected function validateCouponRule($rule)
    {
        $rule_errors = [];
        $empty_coupon_list = true;
        if (!empty($rule['rule_params'])) {
            $scm = new shopCouponModel();
            $coupons = $scm->getById($rule['rule_params']);
            if (!empty($coupons)) {
                $empty_coupon_list = false;
            }
        }

        if ($empty_coupon_list) {
            $rule_errors[] = [
                'field' => '[]',
                'text'  => _w('Please select at least one coupon.'),
            ];
        }

        return $rule_errors;
    }

    protected function validateImage()
    {
        if (!$this->promo_id && !$this->file->count()) {
            $this->errors[] = [
                'name' => 'image',
                'text' => _w('An image must be uploaded.'),
            ];
            return;
        }

        if (!$this->file->count()) {
            return;
        }

        // Make sure the file has correct extension
        $valid_extension = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower($this->file->extension);

        if (!in_array($ext, $valid_extension)) {
            $this->errors[] = [
                'name' => 'image',
                'text' => _w('Files with extensions *.gif, *.jpg, *.jpeg, *.png are allowed only.'),
            ];

            return;
        }

        // Make sure it's an image
        try {
            $this->file->waImage();
        } catch (Exception $e) {
            $this->errors[] = [
                'name' => 'image',
                'text' =>_ws('Not an image or invalid image:').' '.$this->file->name,
            ];

            return;
        }
    }

    //
    // Prepare
    //

    protected function prepareDateTimes()
    {
        $datetime_format = 'Y-m-d H:i:s';
        if (!empty($this->promo_data['start_date'])) {
            $d = $this->promo_data['start_date'];
            $t = ifempty($this->promo_data, 'start_time', '00:00');
            $start_datetime = "{$d} {$t}:00";
            $start_time = strtotime($start_datetime);
            if ($start_time && $start_time > 0) {
                $this->promo_data['start_datetime'] = waDateTime::parse($datetime_format, date($datetime_format, $start_time));
            } else {
                $this->errors[] = [
                    'name' =>  'promo[start_time]',
                    'text' => _w('Invalid date or time'),
                ];
            }
        }

        if (!empty($this->promo_data['finish_date'])) {
            $d = $this->promo_data['finish_date'];
            $t = ifempty($this->promo_data, 'finish_time', '23:59');
            $finish_datetime = "{$d} {$t}:00";
            $finish_time = strtotime($finish_datetime);
            if ($finish_time && $finish_time > 0) {
                $this->promo_data['finish_datetime'] = waDateTime::parse($datetime_format, date($datetime_format, $finish_time));
            } else {
                $this->errors[] = [
                    'name' => 'promo[finish_time]',
                    'text' => _w('Invalid date or time'),
                ];
            }
        }

        if (!empty($this->promo_data['countdown_datetime'])) {
            $d = ifempty($this->promo_data, 'countdown_datetime', 'date', '0000-00-00');
            $h = ifempty($this->promo_data, 'countdown_datetime', 'hour', '23');
            $m = ifempty($this->promo_data, 'countdown_datetime', 'minute', '59');
            $countdown_datetime = "{$d} {$h}:{$m}:00";
            $countdown_time = strtotime($countdown_datetime);
            $current_time = time();
            if ($countdown_time && $countdown_time > $current_time) {
                $this->promo_data['countdown_datetime'] = waDateTime::parse($datetime_format, date($datetime_format, $countdown_time));
            } else {
                $this->errors[] = [
                    'name' => 'promo[countdown_datetime][date]',
                    'text' => _w('Invalid date or time'),
                ];
            }
        } else {
            $this->promo_data['countdown_datetime'] = null;
        }
    }

    protected function prepare()
    {
        $this->preparePromo();

        $this->prepareOldRules();
        $this->prepareRules();
    }

    protected function preparePromo()
    {
        $this->promo_data['enabled'] = (int)ifset($this->promo_data, 'enabled', 0);

        $this->promo_data['type'] = 'link'; // TODO !!!

        if ($this->file->count()) {
            $this->promo_data['ext'] = $this->file->extension;
        }
    }

    protected function prepareOldRules()
    {
        // Get rid of the rules in memory that the user decided to delete.
        if (!empty($this->delete_rule_ids)) {
            foreach ($this->delete_rule_ids as $delete_rule_id) {
                unset($this->old_rules[$delete_rule_id]);
            }
        }

        if (!empty($this->edited_rules)) {
            foreach ($this->edited_rules as $edited_rule_id => &$edited_rule) {
                // Get rid of the rules in memory that the user edited (the rule will be saved with the updated parameters).
                unset($this->old_rules[$edited_rule_id]);

                // Will be assigned a new ID.
                unset($edited_rule['id']);
            }
            unset($edited_rule);
        }

        // Clear ids from old rules.
        foreach ($this->old_rules as &$old_rule) {
            unset($old_rule['id']);
        }
        unset($old_rule);
    }

    protected function prepareRules()
    {
        $rules = array_merge($this->old_rules, $this->edited_rules, $this->new_rules);

        foreach ($rules as &$rule) {
            $part_of_name = '';
            foreach (explode('_', $rule['rule_type']) as $part) {
                $part_of_name .= ucfirst($part);
            }
            $method_name = "prepare{$part_of_name}Rule";
            if (method_exists($this, $method_name)) {
                $this->$method_name($rule);
            }
        }
        unset($rule);

        $this->promo_rules = $rules;
    }

    protected function prepareCustomPriceRule(&$rule)
    {
        foreach ($rule['rule_params'] as $product_id => $product_data) {
            $product_skus = ifempty($product_data, 'skus', []);
            foreach ($product_skus as $sku_id => $prices) {
                if (
                    (!isset($prices['price']) || !strlen($prices['price'])) &&
                    (!isset($prices['compare_price']) || !strlen($prices['compare_price']))
                ) {
                    unset($rule['rule_params'][$product_id]['skus'][$sku_id]);
                    continue;
                }

                $rule['rule_params'][$product_id]['skus'][$sku_id] = [
                    'price'         => isset($prices['price']) ? (string)(float)$prices['price'] : null,
                    'compare_price' => isset($prices['compare_price']) ? (string)(float)$prices['compare_price'] : null,
                ];
            }

            if (empty($rule['rule_params'][$product_id])) {
                unset($rule['rule_params'][$product_id]);
            }
        }
    }

    protected function prepareUtmRule(&$rule)
    {
        $utm_fields = $this->utm_fields;
        foreach ($utm_fields as $utm_field) {
            $utm_field_tags = ifempty($rule, 'rule_params', $utm_field, null);
            if (is_scalar($utm_field_tags)) {
                $utm_field_tags = explode(',', $utm_field_tags);
            }

            if (!empty($utm_field_tags)) {
                $rule['rule_params'][$utm_field] = $utm_field_tags;
            } else {
                unset($rule['rule_params'][$utm_field]);
            }
        }
    }

    //
    // Save
    //
    protected function savePromo()
    {
        // Save shop_promo row
        if (!empty($this->promo_id)) {
            $this->promo_model->updateById($this->promo_id, $this->promo_data);
        } else {
            $this->promo_id = $this->promo_model->insert($this->promo_data);
        }
    }

    protected function saveImage()
    {
        if (!$this->file->count()) {
            return;
        }

        $path = wa('shop')->getDataPath('promos/', true);
        $filepath = $path.sprintf('%s.%s', $this->promo_id, $this->file->extension);

        $files = waFiles::listdir($path);
        $pattern = sprintf('~^%d\.~', $this->promo_id);
        foreach ($files as $file) {
            if (preg_match($pattern, $file)) {
                waFiles::delete($path.$file);
            }
        }

        $this->file->moveTo($filepath);
    }

    protected function saveRules()
    {
        // Delete old promo rules
        $this->promo_rules_model->deleteByField('promo_id', $this->promo_id);

        // Save promo rules
        if (!empty($this->promo_rules)) {
            $promo_rules = [];
            foreach ($this->promo_rules as $promo_rule) {
                $promo_rule = [
                    'promo_id'    => $this->promo_id,
                    'rule_type'   => $promo_rule['rule_type'],
                    'rule_params' => ifempty($promo_rule, 'rule_params', []),
                ];

                if (is_array($promo_rule['rule_params'])) {
                    $promo_rule['rule_params'] = waUtils::jsonEncode($promo_rule['rule_params']);
                }

                $promo_rules[] = $promo_rule;
            }

            $this->promo_rules_model->multipleInsert($promo_rules);
        }
    }

    protected function saveRoutes()
    {
        // Save promo storefronts
        $routes_values = [];
        foreach ($this->storefronts_data as $route => $sort) {
            $routes_values[] = [
                'sort'       => $sort,
                'promo_id'   => $this->promo_id,
                'storefront' => $route,
            ];
        }

        $this->promo_routes_model->deleteByField('promo_id', $this->promo_id);
        $this->promo_routes_model->multipleInsert($routes_values);
    }

    //
    // Helpers
    //

    protected function getStorefronts($promo_id = null)
    {
        $storefronts = [];
        foreach (shopStorefrontList::getAllStorefronts() as $url) {
            $url = rtrim($url, '/').'/';
            $storefronts[$url] = [
                'storefront' => $url,
                'name'       => $url,
                'active'     => true,
                'sort'       => -1,
            ];
        }

        if ($promo_id) {
            $rows = $this->promo_routes_model->getByField('promo_id', $promo_id, 'storefront');
            foreach ($rows as $url => $row) {
                $url = rtrim($url, '/').'/';
                if (empty($storefronts[$url])) {
                    $storefronts[$url] = [
                        'storefront' => $url,
                        'name'       => $url,
                        'active'     => false,
                    ];
                }
                $storefronts[$url]['sort'] = $row['sort'];
            }
        }

        return $storefronts;
    }

    //
    // Events
    //

    protected function promoBeforeSaveEvent()
    {
        /**
         * Event runs before saving marketing promo to database.
         * Allows to interrupt save by returning a validation error
         * and/or change data about to be saved.
         *
         * @event promo_before_save
         * @param array [string]mixed $params
         * @param array [string]bool $params['is_new']            true if this is a new promo (read only)
         * @param array [string]int|null $params['promo_id']      promo id being saved; null if new one (read only)
         * @param array [string]array $params['promo_data']       basic promo data about to be saved (writable)
         * @param array [string]array $params['storefronts_data'] storefronts this promo is enabled on (writable)
         * @param array [string]array $params['delete_rule_ids']  rules (tools) to remove from promo (writable)
         * @param array [string]array $params['rules']            rules (tools) about to be saved (writable)
         * @param array [string]array $params['edited_rules']     rules that user modified (read only; only for reference)
         * @param array [string]array $params['new_rules']        rules that user just added (read only; only for reference)
         * @param array [string]array $params['old_rules']        rules that user did not touch (read only; only for reference)
         *
         * @return array[string][string]array $return[%plugin_id%]['errors'] list of validation errors
         *
         * @see promo_save
         */
        $event_result = wa()->event('promo_before_save', ref([
            'is_new'           => empty($this->promo_id),
            'promo_id'         => $this->promo_id,
            'promo_data'       => &$this->promo_data,
            'storefronts_data' => &$this->storefronts_data,
            'delete_rule_ids'  => &$this->delete_rule_ids,
            'rules'            => &$this->promo_rules,

            'edited_rules'     => $this->edited_rules,
            'new_rules'        => $this->new_rules,
            'old_rules'        => $this->old_rules,
        ]));

        foreach($event_result as $res) {
            if (!empty($res['errors']) && is_array($res['errors'])) {
                foreach($res['errors'] as $err) {
                    $this->errors[] = $err;
                }
            }
        }
    }

    protected function promoSaveEvent()
    {
        $initial_promo_data = waRequest::post('promo', null, waRequest::TYPE_ARRAY_TRIM);

        /**
         * Event runs after promo is successfully saved.
         * Same parameters as in promo_before_save event, but nothing is writable here.
         *
         * @event promo_save
         * @param array [string]mixed $params
         * @param array [string]bool $params['is_new']            true if this promo is just created
         * @param array [string]int $params['promo_id']           promo id just saved
         * @param array [string]array $params['promo_data']       basic promo data
         * @param array [string]array $params['storefronts_data'] storefronts this promo is enabled on
         * @param array [string]array $params['delete_rule_ids']  rules (tools) removed from promo
         * @param array [string]array $params['rules']            rules (tools) saved to promo
         * @param array [string]array $params['edited_rules']     rules that user modified
         * @param array [string]array $params['new_rules']        rules that user just added
         * @param array [string]array $params['old_rules']        rules that user did not touch
         *
         * @see promo_before_save
         */
        wa()->event('promo_save', ref([
            'is_new'           => empty($initial_promo_data['id']),
            'promo_id'         => $this->promo_id,
            'promo_data'       => $this->promo_data,
            'storefronts_data' => $this->storefronts_data,
            'delete_rule_ids'  => $this->delete_rule_ids,
            'rules'            => $this->promo_rules,

            'edited_rules'     => $this->edited_rules,
            'new_rules'        => $this->new_rules,
            'old_rules'        => $this->old_rules,
        ]));
    }
}