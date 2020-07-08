<?php

class shopMarketingPromoSaveController extends waJsonController
{
    const DATETIME_FORMAT = 'Y-m-d H:i:s';

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

    private $dir_files;

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
        $this->validateRules();
    }

    protected function validatePromo()
    {
        $required_fields = ['name'];

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
        //
        // Before validateStorefronts(), $this->storefronts_data contains `storefront` => anything non-empty.
        // As came from POST.
        // `storefront` key in this array may or may not contain slash at the end, no guarantee.
        //
        // After validateStorefronts(), $this->storefronts_data contains `storefront` => `sort` as int >= 0.
        // Note that `sort` can be zero.
        // `storefront` key will or will contain slash at the end depending on canonic form:
        // how shopStorefrontList::getAllStorefronts() returns it.
        //

        $storefronts = $this->getStorefronts(ifempty($this->promo_data, 'id', null));

        // Make sure all keys in storefronts_data are in canonic form of storefront URLs.
        // Also make sure storefront exists
        $new_storefronts_data = [];
        if (!empty($this->storefronts_data[shopPromoRoutesModel::FLAG_ALL])) {
            $new_storefronts_data[shopPromoRoutesModel::FLAG_ALL] = 1;
        }
        foreach($this->storefronts_data as $input_url => $enabled) {
            if ($enabled && $input_url != shopPromoRoutesModel::FLAG_ALL) {
                $url = rtrim($input_url, '/').'/';
                if (isset($storefronts[$url]['canonic_url'])) {
                    $new_storefronts_data[$storefronts[$url]['canonic_url']] = 1;
                }
            }
        }
        $this->storefronts_data = $new_storefronts_data;

        // Add all storefront records to $this->storefronts_data if FLAG_ALL is set.
        // Keep `sort` ordering intact for all storefronts this promo has previously been enabled on.
        // Mark new storefronts as `sort`=-1 to determine it later below.
        foreach ($storefronts as $s) {
            $enabled_after = !empty($this->storefronts_data[shopPromoRoutesModel::FLAG_ALL])
                            || !empty($this->storefronts_data[$s['canonic_url']]);
            if ($enabled_after) {
                $enabled_before = !empty($s['active']);
                if ($enabled_before) {
                    $this->storefronts_data[$s['canonic_url']] = (int) $s['sort'];
                } else {
                    $this->storefronts_data[$s['canonic_url']] = -1;
                }
            } else {
                unset($this->storefronts_data[$s['canonic_url']]);
            }
        }

        $max_sorts = $this->getMaxSorts();

        foreach ($this->storefronts_data as $canonic_url => $sort) {
            // If storefront has just been enabled, determine proper `sort` ordering
            if ($sort < 0) {
                if (empty($max_sorts[$canonic_url])) {
                    $max_sorts[$canonic_url] = -1;
                }
                $max_sorts[$canonic_url]++;
                $this->storefronts_data[$canonic_url] = $max_sorts[$canonic_url];
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
                foreach ($rule_errors as $rule_error) {
                    if (!empty($rule_error['field'])) {
                        $error_field = (stripos($rule_error['field'], '[') === 0) ? $rule_error['field'] : "[{$rule_error['field']}]";
                        $field_name = "rules[{$rule_id}][rule_params]{$error_field}";
                        if ($type == 'new') {
                            $field_name = "rules[new][{$rule_id}][rule_params]{$error_field}";
                        }

                        $error = [
                            'name' => $field_name,
                            'text' => $rule_error['text'],
                        ];

                        if (!empty($rule_error['data'])) {
                            $error['data'] = $rule_error['data'];
                        }

                        $this->errors[] = $error;
                    }

                    if (!empty($rule_error['id'])) {
                        $error_rule_name = "rules[{$rule_id}]";
                        if ($type == 'new') {
                            $error_rule_name = "rules[new][{$rule_id}]";
                        }

                        $error = [
                            'id'   => (string)$rule_error['id'],
                            'rule' => $error_rule_name,
                            'text' => $rule_error['text'],
                        ];

                        if (!empty($rule_error['rule_data'])) {
                            $error['rule_data'] = $rule_error['rule_data'];
                            $error['rule_data']['rule_id'] = $rule_id;
                            $error['rule_data']['rule_id_type'] = $type;
                        }

                        $this->errors[] = $error;
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

    protected function validateRulesList(&$rules)
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
            $part_of_name = $this->getRuleMethodPart($rule_type);

            /**
             * @uses shopMarketingPromoSaveController::validateBannerRule()
             * @uses shopMarketingPromoSaveController::validateCustomPriceRule()
             * @uses shopMarketingPromoSaveController::validateUtmRule()
             * @uses shopMarketingPromoSaveController::validateCouponRule()
             */
            $method_name = "validate{$part_of_name}Rule";
            return $method_name;
        };

        $errors = [];
        foreach ($rules as $rule_id => &$rule) {
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
        unset($rule);

        return $errors;
    }

    protected function validateBannerRule(&$rule)
    {
        $cache_dir = $this->getPromoBannerCacheDir();
        $rule_errors = [];

        if (shopRepairActions::createPromosRequiredFiles() == false) {
            $rule_errors[] = [
                'id'   => 'rule_error',
                'text' => _w('Image thumbnails cannot be generated because required system files are missing in <em>wa-data/public/shop/promos/</em> directory.')
            ];
        }

        if (empty($rule['rule_params']['banners']) || !is_array($rule['rule_params']['banners'])) {
            $rule_errors[] = [
                'id'    => 'rule_error',
                'text'  => _w('Add at least one banner'),
            ];
            return $rule_errors;
        }

        // Validate countdown datetime
        foreach ($rule['rule_params']['banners'] as $i => &$banner) {
            if (empty($banner['image_filename']) && empty($banner['old_image_filename'])) {
                $rule_errors[] = [
                    'id'        => 'rule_error',
                    'text'      => _w('Upload a banner'),
                    'rule_data' => [
                        'banner_id'  => $i,
                        'error_code' => 'invalid_banner_file'
                    ],
                ];
            }

            if (!empty($banner['image_filename']) && !file_exists($cache_dir.$banner['image_filename'])) {
                $rule_errors[] = [
                    'id'        => 'rule_error',
                    'text'      => _w('Banner has not been uploaded.'),
                    'rule_data' => [
                        'banner_id'  => $i,
                        'error_code' => 'banner_file_invalid'
                    ],
                ];
            }

            if (!empty($banner['countdown_datetime'])) {
                $d = ifempty($banner, 'countdown_datetime', 'date', '0000-00-00');
                $h = ifempty($banner, 'countdown_datetime', 'hour', '23');
                $m = ifempty($banner, 'countdown_datetime', 'minute', '59');
                $countdown_datetime = "{$d} {$h}:{$m}:00";
                $countdown_time = strtotime($countdown_datetime);
                $current_time = time();
                if ($countdown_time && $countdown_time > $current_time) {
                    $banner['countdown_datetime'] = waDateTime::parse(self::DATETIME_FORMAT, date(self::DATETIME_FORMAT, $countdown_time));
                } else {
                    $rule_errors[] = [
                        'id'        => 'rule_error',
                        'text'      => _w('Invalid date or time'),
                        'rule_data' => [
                            'banner_id'  => $i,
                            'error_code' => 'countdown_invalid'
                        ],
                    ];
                }
            } else {
                $banner['countdown_datetime'] = null;
            }
        }
        unset($banner);

        return $rule_errors;
    }

    protected function validateCustomPriceRule($rule)
    {
        $rule_errors = [];

        if (empty($rule['rule_params']) || !is_array($rule['rule_params'])) {
            $rule_errors[] = array(
                'id'    => 'rule_error',
                'text'  => _w('Add at least one product to participate in the promo.'),
            );

            return $rule_errors;
        }

        foreach ($rule['rule_params'] as $product_id => $product_data) {
            if (empty($product_data['skus'])) {
                $rule_errors[] = array(
                    'id'    => 'rule_error',
                    'text'  => _w('Add at least one product SKU to participate in the promo.'),
                );
            }
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

    //
    // Prepare
    //

    protected function prepareDateTimes()
    {
        if (!empty($this->promo_data['start_date'])) {
            $d = $this->promo_data['start_date'];
            $t = ifempty($this->promo_data, 'start_time', '00:00');
            $start_datetime = "{$d} {$t}:00";
            $start_time = strtotime($start_datetime);
            if ($start_time && $start_time > 0) {
                $this->promo_data['start_datetime'] = waDateTime::parse(self::DATETIME_FORMAT, date(self::DATETIME_FORMAT, $start_time));
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
                $this->promo_data['finish_datetime'] = waDateTime::parse(self::DATETIME_FORMAT, date(self::DATETIME_FORMAT, $finish_time));
            } else {
                $this->errors[] = [
                    'name' => 'promo[finish_time]',
                    'text' => _w('Invalid date or time'),
                ];
            }
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

        $this->promo_data['start_datetime'] = ifempty($this->promo_data, 'start_datetime', null);
        $this->promo_data['finish_datetime'] = ifempty($this->promo_data, 'finish_datetime', null);
    }

    protected function prepareOldRules()
    {
        // Get rid of the rules in memory that the user decided to delete.
        if (!empty($this->delete_rule_ids)) {
            foreach ($this->delete_rule_ids as $delete_rule_id) {
                if (!empty($this->old_rules[$delete_rule_id])) {
                    $delete_rule = $this->old_rules[$delete_rule_id];
                    $part_of_name = $this->getRuleMethodPart($delete_rule['rule_type']);
                    $method_name = "delete{$part_of_name}Rule";

                    /**
                     * @uses shopMarketingPromoSaveController::deleteBannerRule();
                     */
                    if (method_exists($this, $method_name)) {
                        $this->$method_name($delete_rule);
                    }

                    unset($this->old_rules[$delete_rule_id]);
                }
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
            $old_rule['is_old'] = true;
            unset($old_rule['id']);
        }
        unset($old_rule);
    }

    protected function prepareRules()
    {
        $rules = array_merge($this->old_rules, $this->edited_rules, $this->new_rules);

        foreach ($rules as &$rule) {
            $part_of_name = $this->getRuleMethodPart($rule['rule_type']);

            /**
             * @uses shopMarketingPromoSaveController::prepareCustomPriceRule();
             * @uses shopMarketingPromoSaveController::prepareUtmRule();
             */
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
                    // Just save empty sku, with out prices;
                    $rule['rule_params'][$product_id]['skus'][$sku_id] = [];
                    continue;
                }

                $rule['rule_params'][$product_id]['skus'][$sku_id] = [
                    'price'         => isset($prices['price']) ? (string)(float)str_replace(',', '.', $prices['price']) : null,
                    'compare_price' => isset($prices['compare_price']) ? (string)(float)str_replace(',', '.', $prices['compare_price']) : null,
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

    protected function saveRules()
    {
        // Delete old promo rules
        $this->promo_rules_model->deleteByField('promo_id', $this->promo_id);

        // Save promo rules
        if (!empty($this->promo_rules)) {
            $promo_rules = [];
            foreach ($this->promo_rules as $i => $promo_rule) {
                $part_of_name = $this->getRuleMethodPart($promo_rule['rule_type']);

                /**
                 * @uses shopMarketingPromoSaveController::saveBannerRule();
                 */
                $method_name = "save{$part_of_name}Rule";
                if (method_exists($this, $method_name)) {
                    $this->$method_name($promo_rule);
                }

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

    /**
     * Saving promo coupon
     *
     * @param int $rule
     * @throws waDbException
     * @throws waException
     */
    private function saveCouponRule($rule)
    {
        $coupon_ids = ifempty($rule, 'rule_params', []);
        $shop_sales_model = new shopSalesModel();
        $model_promo_order_model = new shopPromoOrdersModel();
        $model_promo_order_model->refreshPromoOrders($this->promo_id, $coupon_ids);
        $shop_promo_model = new shopPromoModel();
        $shop_promo = $shop_promo_model->getPromo($this->promo_id);

        /** Deleting the statistics cache */
        if ($shop_promo['start_datetime'] === null || $shop_promo['finish_datetime'] === null) {
            $shop_order_model = new shopOrderModel();
            $shop_order = $shop_order_model->getMinMaxDateByCoupon($coupon_ids);
            if (empty($shop_order['mindate'])) {
                // no need to clear cache when there are no orders with coupons
                return;
            }
            if ($shop_promo['start_datetime'] === null) {
                $shop_promo['start_datetime'] = $shop_order['mindate'];
            }
            if ($shop_promo['finish_datetime'] === null) {
                $shop_promo['finish_datetime'] = $shop_order['maxdate'];
            }
        }
        $shop_sales_model->deletePeriod($shop_promo['start_datetime'], $shop_promo['finish_datetime']);
    }

    protected function saveBannerRule(&$rule)
    {
        // Executable only if the tool was created or edited.
        if (!empty($rule['is_old'])) {
            return;
        }

        $cache_dir = $this->getPromoBannerCacheDir();

        foreach ($rule['rule_params']['banners'] as $i => &$banner) {
            $banner['type'] = 'link'; // TODO !!!
            // Once we’ve uploaded the image, we’ll move it to the directory for the current promo
            if (!empty($banner['image_filename'])) {
                $new_filename = $banner['image_filename'];
                $new_image_path = shopPromoBannerHelper::getPromoBannerPath($this->promo_id, $new_filename);
                waFiles::move($cache_dir.$new_filename, $new_image_path);

                // If earlier there was a different image for the banner - delete it and all its resizes.
                if (!empty($banner['old_image_filename'])) {
                    $this->removeBannerImage($banner['old_image_filename']);
                }
            } elseif (!empty($banner['old_image_filename'])) {
                // If there is no new image for this banner, just keep it up to date.
                $banner['image_filename'] = $banner['old_image_filename'];
            }

            unset($banner['old_image_filename']);
        }
        unset($banner);

        //
        // Remove unused images from disk
        //
        if (!empty($rule['rule_params']['remove_images'])) {
            foreach ($rule['rule_params']['remove_images'] as $filename) {
                $this->removeBannerImage($filename);
            }
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
    // Delete
    //

    protected function deleteBannerRule($rule)
    {
        if (!empty($rule['rule_params']['banners'])) {
            foreach ($rule['rule_params']['banners'] as $banner) {
                $this->removeBannerImage($banner['image_filename']);
            }
        }
    }

    //
    // Helpers
    //

    private function getPromoBannerCacheDir()
    {
        return wa()->getAppCachePath('promo/', 'shop');
    }

    private function getDirFiles($dir_path)
    {
        if (empty($this->dir_files[$dir_path])) {
            $this->dir_files[$dir_path] = waFiles::listdir($dir_path);
        }

        return $this->dir_files[$dir_path];
    }

    private function removeBannerImage($filename)
    {
        $filename_regexp = shopPromoBannerHelper::getFilenameRegexp($filename);

        $promo_folder = shopHelper::getFolderById($this->promo_id);
        $flat_images_dir = wa('shop')->getDataPath('promos/', true);
        $promo_images_dir = $flat_images_dir.$promo_folder;

        $file_folder = shopPromoBannerHelper::getPromoBannerFolder($this->promo_id, $filename);

        $image_dir = !empty($file_folder) ? $promo_images_dir : $flat_images_dir;
        $image_dir_files = $this->getDirFiles($image_dir);

        foreach ($image_dir_files as $file) {
            if (preg_match($filename_regexp, $file)) {
                waFiles::delete($image_dir.$file, true);
            }
        }
    }

    private function getRuleMethodPart($rule_type)
    {
        $part_of_name = '';
        foreach (explode('_', $rule_type) as $part) {
            $part_of_name .= ucfirst($part);
        }
        return $part_of_name;
    }

    protected function getStorefronts($promo_id = null)
    {
        $storefronts = [];
        foreach (shopStorefrontList::getAllStorefronts() as $canonic_url) {
            $url = rtrim($canonic_url, '/').'/';
            $storefronts[$url] = [
                'storefront'  => $url,
                'canonic_url' => $canonic_url,
                'name'        => $url,
                'active'      => true,
                'sort'        => -1,
            ];
        }

        if ($promo_id) {
            $rows = $this->promo_routes_model->getByField('promo_id', $promo_id, 'storefront');
            foreach ($rows as $url => $row) {
                $url = rtrim($url, '/').'/';
                if (empty($storefronts[$url])) {
                    $storefronts[$url] = [
                        'storefront'  => $url,
                        'canonic_url' => $url,
                        'name'        => $url,
                        'active'      => false,
                    ];
                }
                $storefronts[$url]['sort'] = $row['sort'];
            }
        }

        return $storefronts;
    }

    protected function getMaxSorts()
    {
        $max_sorts = $this->promo_routes_model->getMaxSorts();
        $result = [];
        foreach($max_sorts as $url => $sort) {
            $url = rtrim($url, '/').'/';
            $result[$url] = max($sort, ifset($result, $url, $sort));
        }
        return $result;
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