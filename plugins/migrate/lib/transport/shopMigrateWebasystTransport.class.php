<?php
abstract class shopMigrateWebasystTransport extends shopMigrateTransport
{
    const STAGE_CATEGORY = 'category';
    const STAGE_CATEGORY_REBUILD = 'categoryRebuild';
    const STAGE_TAX = 'tax';
    const STAGE_PRODUCT = 'product';
    const STAGE_CUSTOMER = 'customer';
    const STAGE_CUSTOMER_CATEGORY = 'customerCategory';
    const STAGE_OPTIONS = 'options';
    const STAGE_OPTION_VALUES = 'optionValues';
    const STAGE_PRODUCT_REVIEW = 'productReview';
    const STAGE_PRODUCT_FILE = 'productFile';
    const STAGE_PRODUCT_IMAGE = 'productImage';
    const STAGE_PRODUCT_IMAGE_RESIZE = 'productImageResize';
    const STAGE_PRODUCT_SET = 'productSet';
    const STAGE_COUPON = 'coupon';
    const STAGE_ORDER = 'order';
    const STAGE_PAGES = 'pages';

    public function getStageName($stage)
    {
        $name = '';
        switch ($stage) {
            case self::STAGE_TAX:
                $name = _wp('Importing taxes...');
                break;
            case self::STAGE_CATEGORY:
                $name = _wp('Importing categories...');
                break;
            case self::STAGE_CATEGORY_REBUILD:
                $name = _wp('Updating category hierarchy...');
                break;
            case self::STAGE_PRODUCT:
                $name = _wp('Importing products...');
                break;
            case self::STAGE_CUSTOMER:
                $name = _wp('Importing customers...');
                break;
            case self::STAGE_CUSTOMER_CATEGORY:
                $name = _wp('Importing customer categories...');
                break;
            case self::STAGE_OPTIONS:
                $name = _wp('Importing product custom options...');
                break;
            case self::STAGE_OPTION_VALUES:
                $name = _wp('Importing product custom option values...');
                break;
            case self::STAGE_PRODUCT_FILE:
                $name = _wp('Importing product downloadable files...');
                break;
            case self::STAGE_PRODUCT_REVIEW:
                $name = _wp('Importing product reviews...');
                break;
            case self::STAGE_PRODUCT_IMAGE:
                $name = _wp('Importing product images (this is the longest part, please be patient)...');
                break;
            case self::STAGE_ORDER:
                $name = _wp('Importing orders...');
                break;
            case self::STAGE_COUPON:
                $name = _wp('Importing coupons...');
                break;
            case self::STAGE_PRODUCT_IMAGE_RESIZE:
                $name = _wp('Creating product thumbnails...');
                break;
            case self::STAGE_PRODUCT_SET:
                $name = _wp('Creating product sets...');
                break;
            case self::STAGE_PAGES:
                $name = _wp('Importing pages...');
                break;
        }
        return $name;
    }

    public function getStageReport($stage, $data)
    {
        $report = '';
        if (!empty($data[$stage])) {
            $count = $data[$stage];
            switch ($stage) {
                case self::STAGE_TAX:
                    $report = _wp('%d tax', '%d taxes', $count);
                    break;
                case self::STAGE_CATEGORY:
                    $report = _wp('%d category', '%d categories', $count);
                    break;
                case self::STAGE_PRODUCT:
                    $report = _wp('%d product', '%d products', $count);
                    break;
                case self::STAGE_PRODUCT_REVIEW:
                    $report = _wp("%d product review", "%d product reviews", $count);
                    break;
                case self::STAGE_CUSTOMER:
                    $report = _wp('%d customer', '%d customers', $count);
                    break;
                case self::STAGE_CUSTOMER_CATEGORY:
                    $report = _wp('%d customer category', '%d customer categories', $count);
                    break;
                case self::STAGE_PRODUCT_IMAGE:
                    $report = _wp('%d image', '%d images', $count);
                    break;
                case self::STAGE_PRODUCT_FILE:
                    $report = _wp('%d product file', '%d product files', $count);
                    break;
                case self::STAGE_ORDER:
                    $report = _wp('%d order', '%d orders', $count);
                    break;
                case self::STAGE_COUPON:
                    $report = _wp('%d coupon', '%d coupons', $count);
                    break;
                case self::STAGE_PAGES:
                    $report = _wp('%d page', '%d pages', $count);
                    break;
            }
        }
        return $report;
    }

    protected function initOptions()
    {
        waHtmlControl::registerControl('OptionsControl', array(&$this, "settingOptionsControl"));
        waHtmlControl::registerControl('OptionMapControl', array(&$this, "settingOptionMapControl"));
        waHtmlControl::registerControl('CustomersControl', array(&$this, "settingCustomersControl"));
        waHtmlControl::registerControl('StatusControl', array(&$this, "settingStatusControl"));

        parent::initOptions();
    }

    public function validate($result, &$errors)
    {
        if ($result) {

#settlement
            $routing = wa()->getRouting();
            $option = array(
                'value'        => false,
                'control_type' => waHtmlControl::SELECT,
                'title'        => _wp('Storefront'),
                'description'  => _wp('Shop-Script settlement for static info pages'),
                'options'      => array(),
            );
            $domain_routes = $routing->getByApp('shop');
            foreach ($domain_routes as $domain => $routes) {
                foreach ($routes as $route) {
                    $option['options'][] = array(
                        'value' => $domain.':'.$route['url'],
                        'title' => $domain.'/'.$route['url'],
                    );
                }
            }

            if (count($option['options']) == 1) {
                $option['control_type'] = waHtmlControl::HIDDEN;
                $value = reset($option['options']);
                $option['value'] = $value['value'];
            } else {
                $sort_callback = create_function('$a,$b', 'return strcasecmp($a["value"],$b["value"]);');
                usort($option['options'], $sort_callback);
            }
            $this->addOption('domain', $option);


            $option = array(
                'value'        => false,
                'control_type' => waHtmlControl::CHECKBOX,
                'title'        => _wp('Preserve IDs'),
                'description'  => _wp('If product (or category) with a particular ID already exists in your new store, delete it and replace with the imported data.'),
            );
            $this->addOption('preserve', $option);
            $settings = array();

            if ($setting_rows = $this->query('SELECT `settings_constant_name` `name`,`settings_value` `value` FROM `SC_settings` WHERE (`settings_constant_name` LIKE "CONF\_DEFAULT%") OR (`settings_constant_name` = "CONF_SHOP_URL")', false)) {
                foreach ($setting_rows as $row) {
                    $settings[strtolower(str_replace('CONF_SHOP_', '', str_replace('CONF_DEFAULT_', '', $row['name'])))] = $row['value'];
                }
            }

            #default_language
            $option = array(
                'control_type' => waHtmlControl::SELECT,
                'title'        => _wp('Source lanuage'),
                'description'  => _wp('Shop-Script 5 allows storing product info and database content in one language. Select primary language of your WebAsyst Shop-Script-based online store.'),
                'options'      => array(),
            );
            $sql = 'SELECT `iso2`,`name` FROM `SC_language` ORDER BY `priority`';
            if ($languages = $this->query($sql, false)) {
                while ($language = array_shift($languages)) {
                    $option['options'][$language['iso2']] = $language['name'];
                }
            }

            $this->setOption('storefront', ifset($settings['url']));

            if (!$this->getOption('locale') && !empty($settings['lang'])) {
                $sql = 'SELECT `iso2` FROM `SC_language` WHERE `id` = %d';
                if ($language = $this->query(sprintf($sql, $settings['lang']))) {
                    $option['value'] = $language['iso2'];
                } elseif ($language = $this->query('SELECT `iso2` FROM `SC_language` ORDER BY `priority` LIMIT 1')) {
                    $option['value'] = $language['iso2'];
                } else {
                    $option['value'] = false;
                }
            } else { //TODO validate selected language
                ;
            }
            $this->addOption('locale', $option);

            #default currency
            $option = array(
                'control_type' => waHtmlControl::SELECT,
                'title'        => _wp('Currency'),
                'options'      => array(),
            );

            //TODO add hint for 1C plugin

            $option['description_wrapper'] = '%s &nbsp;→&nbsp;';

            $option['control_wrapper'] = '
<div class="field">
%s
<div class="value no-shift">%3$s%2$s</div>
</div>';

            $currency_model = new shopCurrencyModel();
            if ($currencies = $currency_model->getAll()) {
                foreach ($currencies as $currency) {
                    $option['options'][$currency['code']] = $currency['code'];
                }
            }

            $currency_value = false;
            if (!empty($settings['currency'])) {
                $sql = 'SELECT * FROM `SC_currency_types` WHERE `CID` = %d';
                if ($currency = $this->query(sprintf($sql, $settings['currency']))) {
                    $currency_value = $currency['currency_iso_3'];
                    if (false && ($locale = $this->getOption('locale')) && isset($currency['Name_'.$locale])) {
                        $currency_name = $currency['Name_'.$locale]." ({$currency_value})";
                    } else {
                        $currency_name = $currency_value;
                    }
                    $option['description'] = $currency_name;
                } elseif ($currency = $this->query('SELECT `currency_iso_3` FROM `SC_currency_types` ORDER BY `sort_order` LIMIT 1')) {
                    $currency_value = $currency['currency_iso_3'];
                }
            }

            if (!$this->getOption('currency')) {
                if (in_array($currency_value, array('RUB', 'RUR'))) {
                    if ($currency = $currency_model->getById(array('RUB', 'RUR'))) {
                        reset($currency);
                        $currency_value = key($currency);
                    } else {
                        $option['class'] = 'error';
                        $option['description'] = sprintf(_wp("Unknown default currency %s"), $currency_value);
                        $currency_value = false;

                    }
                } elseif ($currency_value && !$currency_model->getById($currency_value)) {
                    $option['class'] = 'error';
                    $option['description'] = sprintf(_wp("Unknown default currency %s"), $currency_value);
                    $currency_value = false;

                }
                $option['value'] = $currency_value;
            }

            $this->addOption('currency', $option);

            #type map
            $option = array(
                'control_type' => waHtmlControl::SELECT,
                'title'        => _wp('Product type'),
                'description'  => _wp('Selected product type will be applied to all imported products'),
                'options'      => array(),
            );
            $option['options'][] = array(
                'value' => -1,
                'title' => _wp('Add as new product type'),
            );
            $type_model = new shopTypeModel();
            if ($types = $type_model->getAll()) {

                foreach ($types as $type) {
                    $option['options'][] = array(
                        'value' => $type['id'],
                        'title' => $type['name'],
                    );
                }
                $this->addOption('type', $option);
            } else {
                $type = array(
                    'name' => _wp('Default product type'),
                    'icon' => 'box',
                );
                $option = array(
                    'control_type' => waHtmlControl::HIDDEN,
                    'value'        => $type_model->insert($type),
                );
                $this->addOption('type', $option);
            }

            #weight control
            $suggests_features = array();
            $option = array(
                'value'        => '',
                'control_type' => waHtmlControl::SELECT,
                'title'        => _wp('Weight'),
                'description'  => _wp('Select feature defining product weight.'),
                'checked'      => 'true',
            );
            $suggests = array(
                'weight',
                mb_strtolower(_wp('Weight')),
            );
            $feature_options_model = new shopFeatureModel();
            if ($feature = $feature_options_model->getByCode('weight')) {
                array_unshift($suggests, mb_strtolower($feature['name']));
            }
            $feature_options = $this->getFeaturesOptions($suggests_features, true, false);

            $option['options'] = $feature_options;
            foreach ($suggests as $suggest) {
                if ($option['value'] = array_search($suggest, $suggests_features)) {
                    break;
                }
            }


            $row = $this->query('SELECT `settings_value` `value` FROM `SC_settings` WHERE `settings_constant_name` = "CONF_WEIGHT_UNIT"');
            if (!$row) {
                $row = array('value' => '');
            }

            if ($option['value']) {
                $option['value'] = $row['value'].':'.$option['value'];
            }
            foreach ($option['options'] as &$o) {
                $o['value'] = $row['value'].':'.$o['value'];
            }
            unset($o);
            if (!empty($row['value'])) {
                $option['description'] .= " ".sprintf(_wp('Source weight unit: <strong>%s</strong>'), $row['value']);
            }
            $this->addOption('weight', $option);

            #options map
            $option = array(
                'control_type' => 'OptionsControl',
                'title'        => _wp('Custom parameters'),
                'options'      => array(),
            );
            $this->addOption('options', $option);

            #customer fields map
            $option = array(
                'control_type' => 'CustomersControl',
                'title'        => _wp('Contact fields map'),
                'options'      => array(),
            );

            $this->addOption('customer', $option);

            #orders state map
            $option = array(
                'control_type' => 'StatusControl',
                'title'        => _wp('Status map'),
                'options'      => array(),
            );

            $sql = 'SELECT * FROM `SC_order_status` ORDER BY `sort_order`';
            if ($statuses = $this->query($sql, false)) {
                $locale = $this->getOption('locale');
                while ($status = array_shift($statuses)) {
                    $wrapper = '';
                    if (!empty($status['color'])) {
                        $wrapper .= 'color: #'.$status['color'].';';
                    }
                    if (!empty($status['italic'])) {
                        $wrapper .= 'font-style: italic;';
                    }
                    if (!empty($status['bold'])) {
                        $wrapper .= 'font-weight: bold;';
                    }
                    $option['options'][$status['statusID']] = array(
                        'name'    => $status['status_name_'.$locale],
                        'wrapper' => $wrapper ? '<span style="'.$wrapper.'">%s</span>' : '%s',
                    );
                }
            }

            $this->addOption('status', $option);

        } else {
            $this->addOption('preserve', false);
            $this->addOption('locale', false);
            $this->addOption('type', false);
            $this->addOption('currency', false);
            $this->addOption('options', false);

            $this->addOption('customer', false);

            $this->addOption('status', false);
        }

        return parent::validate($result, $errors);
    }

    public function count()
    {
        $setting_rows = $this->query('SELECT `settings_constant_name` `name`,`settings_value` `value` FROM `SC_settings` WHERE `settings_constant_name` LIKE "CONF\_DEFAULT%"', false);
        $settings = array();
        foreach ($setting_rows as $row) {
            $settings[strtolower(str_replace('CONF_DEFAULT_', '', $row['name']))] = $row['value'];
        }
        #default_language
        if (!empty($settings['lang'])) {
            $sql = 'SELECT `iso2` FROM `SC_language` WHERE `id` = %d';
            if ($language = $this->query(sprintf($sql, $settings['lang']))) {
                $this->setOption('locale', $language['iso2']);
                $this->setOption('locale_id', $settings['lang']);
            } elseif ($language = $this->query('SELECT `iso2` FROM `SC_language` ORDER BY `priority` LIMIT 1')) {
                $this->setOption('locale', $language['iso2']);
                $this->setOption('locale_id', $settings['lang']);
            }
        }

        $type = $this->getOption('type');
        if ($type == -1) {
            $type_model = new shopTypeModel();
            $type = array(
                'name' => 'WebAsyst Shop-Script',
                'icon' => 'box',
            );
            $this->setOption('type', $type_model->insert($type));
        }

        if (!$this->getOption('locale')) {
            throw new waException(_wp("Undefined default langunage"));
        }

        #default currency
        if (!$this->getOption('currency') && !empty($settings['currency'])) {
            $sql = 'SELECT `currency_iso_3` FROM `SC_currency_types` WHERE `CID` = %d';
            if ($language = $this->query(sprintf($sql, $settings['currency']))) {
                $this->setOption('currency', $language['currency_iso_3']);
            } elseif ($language = $this->query('SELECT `currency_iso_3` FROM `SC_currency_types` ORDER BY `sort_order` LIMIT 1')) {
                $this->setOption('currency', $language['currency_iso_3']);
            }
        }

        if (!$this->getOption('currency')) {
            throw new waException(_wp("Undefined default currency"));
        } else {
            $currency_model = new shopCurrencyModel();
            $currency_value = $this->getOption('currency');
            if (in_array($currency_value, array('RUB', 'RUR'))) {
                if ($currency = $currency_model->getById(array('RUB', 'RUR'))) {
                    reset($currency);
                    $this->setOption('currency', key($currency));
                } else {
                    throw new waException(sprintf(_wp("Unknown default currency %s"), $currency_value));
                }
            } elseif (!$currency_model->getById($currency_value)) {
                throw new waException(sprintf(_wp("Unknown default currency %s"), $currency_value));
            }
        }

        $counts = array();
        $count_sqls = array(
            self::STAGE_PAGES                => '`SC_aux_pages`',
            self::STAGE_TAX                  => '`SC_tax_classes`',
            self::STAGE_CATEGORY             => '`SC_categories` WHERE `categoryID`>1',
            self::STAGE_CATEGORY_REBUILD     => 0,

            self::STAGE_OPTIONS              => '`SC_product_options`',
            self::STAGE_OPTION_VALUES        => 0,
            self::STAGE_CUSTOMER_CATEGORY    => '`SC_custgroups`',
            self::STAGE_CUSTOMER             => '`SC_customers`',
            self::STAGE_PRODUCT              => '`SC_products`',
            self::STAGE_PRODUCT_REVIEW       => '`SC_discussions` WHERE `productID`>0',
            self::STAGE_PRODUCT_SET          => '`SC_product_list` WHERE `id`="specialoffers"',
            self::STAGE_COUPON               => '`SC_discount_coupons`',
            self::STAGE_ORDER                => '`SC_orders`',
            self::STAGE_PRODUCT_IMAGE        => '`SC_product_pictures` `i` JOIN `SC_products` `p` ON (`p`.`productID` = `i`.`productID`)',
            self::STAGE_PRODUCT_FILE         => '`SC_products` WHERE (`eproduct_filename` != "")',
            self::STAGE_PRODUCT_IMAGE_RESIZE => 0,
        );

        if ($this->getConfig()->getOption('image_thumbs_on_demand')) {
            unset($count_sqls[self::STAGE_PRODUCT_IMAGE_RESIZE]);
        }

        foreach ($count_sqls as $stage => $sqls) {
            if (!is_int($sqls)) {
                $counts[$stage] = 0;
                if (!is_array($sqls)) {
                    $sqls = $sqls ? array($sqls) : array();
                }
                foreach ($sqls as $sql) {
                    $query_result = $this->query('SELECT DISTINCT COUNT(1) AS `cnt` FROM '.$sql);
                    $counts[$stage] += intval($query_result['cnt']);
                }
            } else {
                $counts[$stage] = $sqls;
            }
        }
        return $counts;
    }

    public function step(&$current, &$count, &$processed, $stage, &$error)
    {
        $method_name = 'step'.ucfirst($stage);
        $result = false;
        try {
            if (method_exists($this, $method_name)) {
                $result = $this->$method_name($current[$stage], $count, $processed[$stage]);
                if ($result && ($processed[$stage] > 10) && ($current[$stage] == $count[$stage])) {
                    $result = false;
                }
            } else {
                $this->log(sprintf("Unsupported stage [%s]", $stage), self::LOG_ERROR);
                $current[$stage] = $count[$stage];
            }
        } catch (waDbException $ex) {
            sleep(5);
            $this->log($stage.': '.$ex->getMessage().(empty($error) ? 'first' : 'repeat')."\n".$ex->getTraceAsString(), self::LOG_ERROR);
            if (!empty($error)) {
                if (($error['stage'] == $stage) && ($error['iteration'] == $current[$stage]) && ($error['code'] == $ex->getCode()) && ($error['message'] == $ex->getMessage())) {
                    $this->log('BREAK ON '.$ex->getMessage(), self::LOG_ERROR);
                    throw $ex;
                }
            }
            $error = array(
                'stage'     => $stage,
                'iteration' => $current[$stage],
                'code'      => $ex->getCode(),
                'message'   => $ex->getMessage(),
                'counter'   => 0,

            );
        } catch (Exception $ex) {
            sleep(5);
            $this->log($stage.': '.$ex->getMessage().(empty($error) ? 'first' : 'repeat')."\n".$ex->getTraceAsString(), self::LOG_ERROR);
            if (!empty($error)) {
                if (($error['stage'] == $stage) && ($error['iteration'] == $current[$stage]) && ($error['code'] == $ex->getCode()) && ($error['message'] == $ex->getMessage())) {
                    if (++$error['counter'] > 5) {
                        $this->log('BREAK ON '.$ex->getMessage(), self::LOG_ERROR);
                        throw $ex;
                    }
                } else {
                    $error = null;
                }
            }
            if (empty($error)) {
                $error = array(
                    'stage'     => $stage,
                    'iteration' => $current[$stage],
                    'code'      => $ex->getCode(),
                    'message'   => $ex->getMessage(),
                    'counter'   => 0,

                );
            }
        }
        return $result;
    }

    private function stepCategory(&$current_stage, &$count, &$processed)
    {
        static $category_data_cache = array();
        if (!isset($this->map[self::STAGE_CATEGORY])) {
            $this->offset[self::STAGE_CATEGORY] = 1;
            $this->map[self::STAGE_CATEGORY] = array();
            $this->map[self::STAGE_CATEGORY][1] = 0;

            $this->map[self::STAGE_CATEGORY_REBUILD] = array();
        }
        $resave =& $this->map[self::STAGE_CATEGORY_REBUILD];
        $category_map =& $this->map[self::STAGE_CATEGORY];
        $category = new shopCategoryModel();

        if (!$category_data_cache) {
            $sql = 'SELECT * FROM `SC_categories` WHERE (`categoryID` > %d) ORDER BY `categoryID` LIMIT 10';
            $category_data_cache = $this->query(sprintf($sql, intval($this->offset[self::STAGE_CATEGORY])), false);
        }
        if ($data = reset($category_data_cache)) {

            $parent = intval($data['parent']);
            $parent_id = 0;
            if (isset($category_map[$parent])) {
                $parent_id = $category_map[$parent];
            }
            $locale = $this->getOption('locale');

            $category_data = array(
                'name'             => $data['name_'.$locale],
                'meta_title'       => $data['meta_title_'.$locale],
                'meta_keywords'    => $data['meta_keywords_'.$locale],
                'meta_description' => $data['meta_description_'.$locale],

                'description'      => $data['description_'.$locale],
                'type'             => shopCategoryModel::TYPE_STATIC,
                'parent_id'        => $parent_id,

                'id'               => intval($data['categoryID']),
            );
            if (!empty($data['id_1c'])) {
                $category_data['id_1c'] = mb_substr($data['id_1c'], 0, 36);
            }
            if ($category->countByField('id', $category_data['id'])) {
                if ($this->getOption('preserve')) {
                    $category->delete($category_data['id']);
                } else {
                    unset($category_data['id']);
                }
            }

            $category_data['url'] = $category->suggestUniqueUrl(ifempty($data['slug'], $data['categoryID']), ifset($category_data['id']), $parent_id);

            $id = $category->add($category_data, $parent_id);

            $this->offset[self::STAGE_CATEGORY] = intval($data['categoryID']);

            //fill categories map
            $category_map[$this->offset[self::STAGE_CATEGORY]] = $id;

            if ($parent && !isset($category_map[$parent])) {
                if (!isset($resave[$parent])) {
                    $resave[$parent] = array();
                }
                $resave[$parent][] = $id;
                $count[self::STAGE_CATEGORY_REBUILD] = count($resave);
            }
            ++$current_stage;
            array_shift($category_data_cache);
            ++$processed;
        }
        return true;
    }

    private function stepCategoryRebuild(&$current_stage, &$count)
    {
        $result = false;
        if ($resave = reset($this->map[self::STAGE_CATEGORY_REBUILD])) {
            $parent = key($this->map[self::STAGE_CATEGORY_REBUILD]);
            if (!empty($this->map[self::STAGE_CATEGORY][$parent])) {
                $category_id = $this->map[self::STAGE_CATEGORY][$parent];
                $category = new shopCategoryModel();
                foreach ($resave as $id) {
                    $category->move($id, null, $category_id);
                }
                $item = $category->getById($category_id);
                // update full_url of all descendant
                $category->correctFullUrlOfDescendants($item['id'], trim($item['full_url'], '/'));
            }

            unset($this->map[self::STAGE_CATEGORY_REBUILD][$parent]);
            ++$current_stage;
            $result = true;
        }

        return $result;
    }

    private function stepTax(&$current_stage, &$count, &$processed)
    {
        static $data_cache = array();
        $result = false;

        if (!$current_stage) {
            $this->map[self::STAGE_TAX] = array();
            $this->offset[self::STAGE_TAX] = 0;
        }
        if (!$data_cache) {
            $sql = 'SELECT `t`.*, `r`.`value` `global_rate`
            FROM `SC_tax_classes` `t`
            LEFT JOIN `SC_tax_rates` `r`
            ON
            (`r`.`isGrouped`=1) AND (`r`.`classID` = `t`.`classID`)
            WHERE (`t`.`classID` > %d)
            ORDER BY `t`.`classID`
            LIMIT 20';
            $data_cache = $this->query(sprintf($sql, intval($this->offset[self::STAGE_TAX])), false);
        }
        if ($data = reset($data_cache)) {
            $id = intval($data['classID']);
            $this->log('Import tax', self::LOG_DEBUG, $data);

            $tax_data = array(
                'name'         => $data['name'],
                'included'     => false,
                'address_type' => empty($data['address_type']) ? 'billing' : 'shipping',
                'countries'    => array( //                    'rus' => array(
                    //                        'global_rate' => 18, // %
                    //                        'regions'     => array(
                    //                            'code' => 'value',
                    //                       ),
                    //                    ),

                    // Use special codes instead of country ISO3 for country groups:
                    // '%AL' = All countries
                    // '%EU' = All european countries
                    // '%RW' = Rest of the world

                ),
                'zip_codes'    => array(),
            );

            #by counties
            $sql = 'SELECT `t`.`classID`, LOWER(`c`.`country_iso_3`) `country`, `t`.`value`, `t`.`isByZone`
            FROM `SC_tax_rates` `t`
            LEFT JOIN `SC_countries` `c`
            ON (`c`.`countryID` = `t`.`countryID`)
            WHERE
            (classID IN (%s))
            AND
            (isGrouped=0)';
            $rates_per_country = $this->query(sprintf($sql, $id), false);
            foreach ($rates_per_country as $rate) {
                $country_tax = array();
                if (empty($rate['isByZone'])) {
                    $country_tax['global_rate'] = $rate['value'];
                } else {
                    $country_tax['regions'] = array();
                }
                $tax_data['countries'][$rate['country']] = $country_tax;
            }

            #'global_rate';
            $sql = 'SELECT `t`.`classID`, `t`.`value`,
            FROM `SC_tax_rates` `t`
            WHERE
            (`classID` IN (%s))
            AND
            (`isGrouped`=1)';

            #by zip
            $sql = 'SELECT `t`.`zip_template` `code`, `t`.`value`
            FROM `SC_tax_zip` `t`
            WHERE (`classID` IN (%s))';

            $rates_per_zip = $this->query(sprintf($sql, $id), false);
            foreach ($rates_per_zip as $rate) {
                $tax_data['zip_codes'][$rate['code']] = $rate['value'];
            }

            #by region

            $sql = 'SELECT LOWER(`c`.`country_iso_3`) `country`,`t`.`classID`, `z`.`zone_code` `region`, LOWER(`z`.`zone_name_%s`) `region_name`, `t`.`value`
            FROM `SC_tax_rates__zones` `t`
            LEFT JOIN `SC_zones` `z`
            ON (`z`.`zoneID`=`t`.`zoneID`)
            LEFT JOIN `SC_countries` `c`
            ON (`c`.`countryID` = `z`.`countryID`)
            WHERE
            (`t`.`classID` IN (%s))
            AND
            `t`.`isGrouped`=0
            ORDER BY  `z`.`countryID`';

            $rates_per_region = $this->query(sprintf($sql, $this->getOption('locale'), $id), false);
            $regions = array();
            foreach ($rates_per_region as $rate) {
                if (!isset($tax_data['countries'][$rate['country']])) {
                    $tax_data['countries'][$rate['country']] = array();
                }
                $country_tax =& $tax_data['countries'][$rate['country']];
                ifempty($country_tax['regions'], array());
                if (empty($rate['region'])) {
                    if (!isset($regions[$rate['country']])) {
                        $regions[$rate['country']] = array();
                        $m = new waRegionModel();
                        foreach ($m->getByCountry($rate['country']) as $code => $r) {
                            $regions[$rate['country']][mb_strtolower($r['name'], 'utf-8')] = $code;
                        }
                    }
                    $rate['region'] = isset($regions[$rate['country']][$rate['region_name']]) ? $regions[$rate['country']][$rate['region_name']] : false;
                    if (!$rate['region']) {
                        $like = array();
                        foreach ($regions[$rate['country']] as $name => $code) {
                            $like[$code] = 0.0;
                            similar_text($name, $rate['region_name'], $like[$code]);
                        }
                        //array_map('floatval', $like);
                        arsort($like, SORT_NUMERIC);
                        reset($like);
                        $rate['region'] = key($like);
                        $this->log("Error while import tax: remap", self::LOG_INFO, array($rate, $like, $regions[$rate['country']]));
                    }
                }
                if (!empty($rate['region'])) {
                    $country_tax['regions'][$rate['region']] = $rate['value'];
                } else {
                    if (empty($country_tax['global_rate'])) {
                        $country_tax['global_rate'] = $rate['value'];
                    }
                    $this->log("Error while import tax: not found region", self::LOG_ERROR, array($rate, ifempty($regions[$rate['country']], array())));
                }
                unset($country_tax);
            }

            $sql = 'SELECT `t`.`classID`, `t`.`value`
            FROM `SC_tax_rates__zones` `t`
            WHERE
            (classID IN (%s))
            AND
            `t`.`isGrouped`=1';

            $this->log('taxes', self::LOG_ERROR, $tax_data);

            $tax = shopTaxes::save($tax_data);

            if (ifset($tax['id'])) {
                $this->map[self::STAGE_TAX][$id] = $tax['id'];
            } else {
                $this->log("Error while import tax", self::LOG_ERROR);
            }

            // update internal offset
            $this->offset[self::STAGE_TAX] = $id;
            $result = true;
            array_shift($data_cache);
            ++$current_stage;
            ++$processed;
        }
        return $result;
    }

    private function stepCustomerCategory(&$current_stage, &$count, &$processed)
    {
        static $customer_data_cache = array();
        $result = false;

        if (!$current_stage) {
            $this->map[self::STAGE_CUSTOMER_CATEGORY] = array();
            $this->offset[self::STAGE_CUSTOMER_CATEGORY] = 0;
        }
        if (!$customer_data_cache) {

            $sql = 'SELECT *, `custgroup_name_%s` `name`
            FROM `SC_custgroups` `c`
            WHERE (`c`.`custgroupID` > %d)
            ORDER BY `c`.`custgroupID`
            LIMIT 100';
            $customer_data_cache = $this->query(sprintf($sql, $this->getOption('locale'), intval($this->offset[self::STAGE_CUSTOMER_CATEGORY])), false);
        }
        if ($data = reset($customer_data_cache)) {
            $id = intval($data['custgroupID']);
            $this->log('Import customer category', self::LOG_DEBUG, $data);
            $category_model = new waContactCategoryModel();
            $category_id = $category_model->insert(array(
                'name'   => $data['name'],
                'icon'   => 'contact',
                'app_id' => 'shop',
            ));
            if ($category_id) {
                $this->map[self::STAGE_CUSTOMER_CATEGORY][$id] = $category_id;
                if (!empty($data['custgroup_discount'])) {
                    $ccdm = new shopContactCategoryDiscountModel();
                    $ccdm->insert(array(
                        'category_id' => $category_id,
                        'discount'    => $data['custgroup_discount'],
                    ));
                }
            } else {
                $this->log("Error while import customer", self::LOG_ERROR);
            }

            // update internal offset
            $this->offset[self::STAGE_CUSTOMER_CATEGORY] = $id;
            $result = true;
            array_shift($customer_data_cache);
            ++$current_stage;
            ++$processed;
        }
        return $result;
    }

    private function stepCustomer(&$current_stage, &$count, &$processed)
    {
        static $customer_data_cache = array();
        static $customer_fields_cache = array();
        static $customer_address_cache = array();
        static $map;
        $result = false;

        if (!$current_stage) {
            $this->map[self::STAGE_CUSTOMER] = array();
            $this->offset[self::STAGE_CUSTOMER] = 0;
        }
        if (!$customer_data_cache) {

            $sql = 'SELECT  `c`.`customerID`  `id` ,  `c` . * ,  `c`.`addressID` `default_addressID`
FROM  `SC_customers`  `c`
WHERE (`c`.`customerID` >%d)
ORDER BY  `c`.`customerID`
LIMIT 100';
            $customer_data_cache = $this->query(sprintf($sql, intval($this->offset[self::STAGE_CUSTOMER])), false);
            $ids = array();
            $adress_ids = array();
            $missed_address_ids = array();
            foreach ($customer_data_cache as $data) {
                $ids[] = intval($data['id']);
                if ($address_id = intval($data['default_addressID'])) {
                    $adress_ids[] = $address_id;
                } else {
                    $missed_address_ids[] = intval($data['id']);
                }
            }

            $sql = 'SELECT DISTINCT `customerID`, `addressID` `default_addressID`
FROM   `SC_customer_addresses`
WHERE `customerID` IN (%s)
ORDER BY  `addressID`
LIMIT 100';

            $customer_adress_ids = $this->query(sprintf($sql, implode(',', $ids)), false);
            $default_address = array();
            foreach ($customer_adress_ids as $row) {
                if ($address_id = intval($row['default_addressID'])) {
                    $adress_ids[] = $address_id;
                    $default_address[$row['customerID']] = $address_id;
                }
            }

            foreach ($customer_data_cache as & $data) {
                if (empty($data['default_addressID'])) {
                    $data['default_addressID'] = ifset($default_address[$data['id']]);
                }
            }
            unset($data);
            if (!isset($map)) {
                $map = array_filter($this->getOption('customer', array()), 'strlen');
                $map_ids = array();
                foreach ($map as $map_id => $target) {
                    if ($target == '::new') {
                        $map_ids[] = $map_id;
                    }
                }
                if ($map_ids) {
                    $sql = 'SELECT `reg_field_ID`,`reg_field_required`, `reg_field_name_%s` `name` FROM `SC_customer_reg_fields`
            WHERE `reg_field_ID` IN (%s)
            ORDER BY `sort_order`

            ';
                    $exists_contact_fields = array_keys(waContactFields::getAll());
                    if ($fields = $this->query(sprintf($sql, $this->getOption('locale'), implode(',', $map_ids)), false)) {
                        $checkout_config_path = wa()->getConfig()->getConfigPath('checkout.php', true, 'shop');
                        $checkout_config = false;
                        if (file_exists($checkout_config_path)) {
                            $checkout_config = include($checkout_config_path);
                        }

                        while ($field = array_shift($fields)) {

                            $field_id = waLocale::transliterate(mb_strtolower($field['name']), 'ru_RU');
                            $field_id = preg_replace('([^a-z_])', '_', $field_id);
                            $field_id = substr(preg_replace('([_]{2,})', '_', $field_id), 0, 64);


                            $counter = 0;
                            while (in_array($field_id, $exists_contact_fields)) {
                                $field_id = substr($field_id, 0, 60);
                                $field_id .= $counter;
                                $field_id = preg_replace("@{$counter}\$@", $counter++, $field_id);
                            }

                            $options = array(
                                'app_id' => 'shop',
                            );
                            if (!empty($field['reg_field_required'])) {
                                $options['required'] = true;
                            }

                            $new_field = new waContactStringField($field_id, $field['name'], $options);
                            waContactFields::updateField($new_field);
                            waContactFields::enableField($new_field, 'person');
                            $map[$field['reg_field_ID']] = $field_id;
                            $exists_contact_fields[] = $field_id;
                            if (!empty($checkout_config)) {
                                $checkout_config['contactinfo']['fields'][$field_id] = array(
                                    'localized_names' => $field['name'],
                                    'required'        => !empty($options['required']),
                                );
                            }
                        }
                        if (!empty($checkout_config)) {
                            waUtils::varExportToFile($checkout_config, $checkout_config_path);
                        }
                    }
                }

                $this->setOption('customer', $map);
            }
            //TODO add new customer text fields
            $customer_fields_cache = array();
            $customer_address_cache = array();
            if ($ids && $map) {
                $map_ids = array_map('intval', array_keys($map));

                $sql = 'SELECT * FROM `SC_customer_reg_fields_values`
            WHERE (`customerID` IN (%s)) AND (`reg_field_ID` IN (%s))
            ORDER BY `customerID`
            ';
                if ($fields = $this->query(sprintf($sql, implode(',', $ids), implode(',', $map_ids)), false)) {
                    while ($field = array_shift($fields)) {
                        $id = intval($field['customerID']);
                        if (!isset($customer_fields_cache[$id])) {
                            $customer_fields_cache[$id] = array();
                        }
                        if (!empty($map[$field['reg_field_ID']])) {
                            $customer_fields_cache[$id][$map[$field['reg_field_ID']]] = $field['reg_field_value'];
                        }
                    }
                }
            }
            if ($adress_ids) {

                $sql = "SELECT `a`.*,`a`.`address` `street`, LOWER(`c`.`country_iso_3`) `country`,
                IFNULL(IF(`z`.`zone_code`='',`zone_name_%s`,`z`.`zone_code`),`a`.`state`) `region`
                FROM `SC_customer_addresses` `a`
                LEFT JOIN `SC_countries` `c` ON (`c`.`countryID`= `a`.`countryID`)
                LEFT JOIN `SC_zones` `z` ON (`z`.`countryID`= `a`.`countryID`) AND (`z`.`zoneID`= `a`.`zoneID`)
            WHERE (`addressID` IN (%s))
            ORDER BY `customerID`
            ";
                if ($addresses = $this->query(sprintf($sql, $this->getOption('locale'), implode(',', $adress_ids)), false)) {
                    while ($address = array_shift($addresses)) {
                        $id = intval($address['customerID']);
                        $customer_address_cache[$id] = array();
                        $address_fields = array('country', 'region', 'zip', 'city', 'street');
                        foreach ($address_fields as $field) {
                            if (!empty($address[$field])) {
                                $customer_address_cache[$id][$field] = $address[$field];
                            }
                        }

                    }
                }
            }
        }
        if ($data = reset($customer_data_cache)) {
            $id = intval($data['customerID']);
            $this->log('Import customer', self::LOG_DEBUG, $data);
            $customer = new waContact();
            $customer['firstname'] = $data['first_name'];
            $customer['lastname'] = $data['last_name'];
            $customer['email'] = $data['Email'];
            $customer['create_datetime'] = $data['reg_datetime'];
            $customer['create_app_id'] = 'shop';

            if (!empty($data['Login']) && !empty($data['cust_password'])) {
                $customer['password'] = base64_decode($data['cust_password']);
            }
            if (!empty($customer_fields_cache[$id])) {
                foreach ($customer_fields_cache[$id] as $field => $value) {
                    $customer->set($field, $value);
                }
            }
            if (!empty($customer_address_cache[$id])) {
                $customer->set('address', $customer_address_cache[$id]);
            }
            if ($errors = $customer->save()) {
                $this->log("Error while import customer", self::LOG_ERROR, $errors);
            } else {
                $customer->addToCategory('shop');
                $this->map[self::STAGE_CUSTOMER][intval($data['customerID'])] = $customer->getId();

                if (!empty($data['custgroupID']) && !empty($this->map[self::STAGE_CUSTOMER_CATEGORY][$data['custgroupID']])) {
                    $customer->addToCategory($this->map[self::STAGE_CUSTOMER_CATEGORY][$data['custgroupID']]);
                }
            }

            // update internal offset
            $this->offset[self::STAGE_CUSTOMER] = intval($data['customerID']);
            $result = true;
            array_shift($customer_data_cache);
            ++$current_stage;
            ++$processed;
        }
        return $result;
    }

    private function stepProduct(&$current_stage, &$count, &$processed)
    {
        static $product_data_cache = array();
        static $product_options_cache = array();
        static $product_tags_cache = array();

        static $services_model;
        $result = false;

        if (!$current_stage) {
            $this->map[self::STAGE_PRODUCT] = array();
            $this->offset[self::STAGE_PRODUCT] = 0;
        }
        $product_model = new shopProductModel();
        if (!$product_data_cache) {

            $sql = 'SELECT `p`.* , GROUP_CONCAT(`c`.`categoryID`) `extra_category`
            FROM `SC_products` `p`
            LEFT JOIN `SC_category_product` `c`
            ON (`c`.`productID` = `p`.`productID`)
            WHERE (`p`.`productID` > %d)
            GROUP BY `p`.`productID`
            ORDER BY `p`.`productID`
            LIMIT 20';
            $product_data_cache = $this->query(sprintf($sql, intval($this->offset[self::STAGE_PRODUCT])), false);
            $ids = array();
            foreach ($product_data_cache as $data) {
                $ids[] = intval($data['productID']);
            }
            $sql = 'SELECT
            `o`.`productID`,`o`.`optionID`,  IF(`o`.`option_type`, `v`.`option_value_%1$s`,`o`.`option_value_%1$s`) `value`,
            `s`.`price_surplus` `price`,  IF(`o`.`option_type` AND `o`.`variantID` = `s`.`variantID`, 1, 0) `is_default`
            FROM `SC_product_options_values` `o`
            LEFT JOIN `SC_product_options_set` `s`
            ON (`o`.`productID` = `s`.`productID`) AND (`o`.`optionID` = `s`.`optionID`)
            LEFT JOIN `SC_products_opt_val_variants` `v`
            ON (`o`.`optionID` = `v`.`optionID`) AND (`s`.`variantID` = `v`.`variantID`)
            WHERE (`o`.`productID` IN (%2$s))
            ORDER BY `o`.`productID`,`o`.`optionID`, `v`.`sort_order`, `value`
            ';

            $product_options_cache = array();
            if ($ids && ($options = $this->query(sprintf($sql, $this->getOption('locale'), implode(',', $ids)), false))) {
                while ($option = array_shift($options)) {
                    $id = intval($option['productID']);
                    if (!isset($product_options_cache[$id])) {
                        $product_options_cache[$id] = array();
                    }
                    unset($option['productID']);
                    $product_options_cache[$id][] = $option;
                }
            }

            $sql = <<<SQL
SELECT `o`.`object_id` `productID`, `t`.`name` `tag`
            FROM SC_tagged_objects o
            JOIN SC_tags t
            ON (t.id = o.tag_id)
            WHERE
             o.object_type='product'
             AND
             o.language_id = %1\$d
             AND
              o.object_id IN (%2\$s)
SQL;

            $product_tags_cache = array();
            if ($ids && ($tags = $this->query($s = sprintf($sql, $this->getOption('locale_id'), implode(',', $ids)), false))) {
                while ($tag = array_shift($tags)) {
                    $id = intval($tag['productID']);
                    if (!isset($product_tags_cache[$id])) {
                        $product_tags_cache[$id] = array();
                    }
                    $product_tags_cache[$id][] = $tag['tag'];
                }
            }


            if ($ids && $this->getOption('preserve')) {
                $product_model->delete($ids);
            }
        }
        if ($data = reset($product_data_cache)) {
            $product = new shopProduct();

            if ($this->getOption('preserve') || !$product_model->countByField('id', $data['productID'])) {
                $product->id = $data['productID'];
            }

            $locale = $this->getOption('locale');
            $product->type_id = $this->getOption('type');

            $product->name = $data['name_'.$locale];
            $product->summary = $data['brief_description_'.$locale];

            if (!empty($data['categoryID']) && isset($this->map[self::STAGE_CATEGORY][$data['categoryID']])) {
                $product->category_id = $this->map[self::STAGE_CATEGORY][$data['categoryID']];
            }

            $product->meta_title = $data['meta_title_'.$locale];
            $product->meta_keywords = $data['meta_keywords_'.$locale];
            $product->meta_description = $data['meta_description_'.$locale];

            $product->description = $data['description_'.$locale];
            $product->url = ifempty($data['slug'], $data['productID']);
            if (!empty($data['date_added']) && ($data['date_added'] != '0000-00-00 00:00:00')) {
                $product->create_datetime = $data['date_added'];
            }
            if (!empty($data['date_modified']) && ($data['date_modified'] != '0000-00-00 00:00:00')) {
                $product->edit_datetime = $data['date_modified'];
            }

            if (!empty($data['id_1c'])) {
                $product->setData('id_1c', mb_substr($data['id_1c'], 0, 36));
            }
            $categories = array_map('intval', explode(',', $data['extra_category']));
            //insert primary category first
            if ($category_id = intval($data['categoryID'])) {
                $id = array_search($category_id, $categories);
                if ($id !== false) {
                    unset($categories[$category_id]);
                }
                array_unshift($categories, $category_id);
            }

            foreach ($categories as $id => & $category_id) {
                if (isset($this->map[self::STAGE_CATEGORY][$category_id])) {
                    $category_id = $this->map[self::STAGE_CATEGORY][$category_id];
                } else {
                    unset($categories[$id]);
                }
                unset($category_id);
            }
            $product->categories = $categories;
            if (!empty($product_tags_cache[$data['productID']])) {
                $product->tags = $product_tags_cache[$data['productID']];
            }
            $product->currency = $this->getOption('currency');

            $product->tax_id = ifempty($this->map[self::STAGE_TAX][$data['classID']], null);

            $product->status = empty($data['enabled']) ? 0 : 1;

            $features = array();
            if (($weight = $this->getOption('weight')) && !empty($data['weight'])) {
                $f = null;
                @list($weight, $f, $code) = explode(':', $weight, 3);
                if ($code) {
                    $features[$code] = $data['weight'].' '.$weight;
                }
            }
            $services = array();

            //options -> features & services

            $sku_options = array();
            if (!empty($product_options_cache[$data['productID']])) {
                $options = $product_options_cache[$data['productID']];
                $this->log('Import product options', self::LOG_INFO, $options);
                while ($option = array_shift($options)) {
                    $option_id = $option['optionID'];
                    $option['value'] = trim($option['value']);

                    if (isset($this->map[self::STAGE_OPTIONS][$option_id])) {
                        $target = explode(':', $this->map[self::STAGE_OPTIONS][$option_id], 2);
                        switch ($target[0]) {
                            case 'f':
                                if ($option['value'] !== '') {
                                    $code = $target[1];
                                    if (strpos($code, ':')) {
                                        @list($code, $dimension) = explode(':', $code, 2);
                                        if ($dimension && !preg_match('@\d\s+\w+$@', $option['value'])) {
                                            $option['value'] = doubleval($option['value']).' '.$dimension;
                                        }
                                    }

                                    if (!isset($features[$code])) {
                                        $features[$code] = array();
                                    }

                                    if (intval($option['is_default'])) {
                                        array_unshift($features[$code], $option['value']);
                                    } else {
                                        $features[$code][] = $option['value'];
                                    }

                                }
                                break;
                            case 's':
                                if (($option['value'] !== null) && ($option['value'] !== '')) {
                                    $service_id = $target[1];
                                    $service_variants_model = new shopServiceVariantsModel();
                                    //TODO check currency
                                    $variant_field = array(
                                        'service_id' => $service_id,
                                        'name'       => $option['value'],
                                    );
                                    $variant = $service_variants_model->getByField($variant_field);
                                    if (!$variant) {
                                        $variant = $variant_field;
                                        $variant['price'] = $option['price'];
                                        $variant['id'] = $service_variants_model->insert($variant_field);
                                        $service_variants_model->move($service_id, $variant['id']);
                                    }


                                    if (!isset($services[$service_id])) {
                                        $services[$service_id] = array();
                                    }

                                    $services[$service_id][$variant['id']] = array(
                                        'price'  => ($variant['price'] == $option['price']) ? null : $option['price'],
                                        'status' => intval($option['is_default']) ? shopProductServicesModel::STATUS_DEFAULT : shopProductServicesModel::STATUS_PERMITTED,
                                        'skus'   => array(),
                                    );
                                }
                                break;
                            case 'sku':
                                if (!empty($option['value'])) {
                                    if (!isset($sku_options[$option_id])) {
                                        $sku_options[$option_id] = array();
                                    }
                                    $option['name'] = $target[1];
                                    unset($option['optionID']);
                                    $sku_options[$option_id][] = $option;
                                }
                                break;
                        }
                    }
                }

            }

            //skus
            $in_stock = sprintf('%d', intval($data['in_stock']));
            $skus = array(-1 => array(
                'name'          => $sku_options ? $data['name_'.$locale] : '',
                'sku'           => ifempty($data['product_code'], ''),
                'stock'         => array(
                    0 => $in_stock,
                ),
                //TODO convert price and currency
                'price'         => $data['Price'],
                'available'     => $data['enabled'] ? 1 : 0,
                'compare_price' => $data['list_price'],
            )
            );
            $product->sku_id = -1;
            if ($sku_options) {
                $sku_level = 0;
                foreach ($sku_options as $sku_option) {
                    $id = 0;
                    $sku_buffer = $skus;
                    $skus = array();

                    $updated = false;
                    $_sku_id = $product->sku_id;

                    foreach ($sku_option as $option) {
                        foreach ($sku_buffer as $sku_id => $sku) {
                            $sku['name'] = ($sku_level && $sku['name'] ? $sku['name'].', ' : '').$option['value'];
                            $sku['price'] += doubleval($option['price']);

                            $skus[--$id] = $sku;

                            if (!$updated && ($sku_id == $_sku_id) && intval($option['is_default'])) {
                                $updated = true;
                                $product->sku_id = $id;
                            }
                        }
                    }
                    ++$sku_level;
                }
            }
            if (count($skus) > 1) {
                $sku_instock = floor($in_stock / count($skus));
                foreach ($skus as $sku_id => & $sku) {
                    if ($product->sku_id != $sku_id) {
                        $sku['stock'] = array(
                            0 => $sku_instock,
                        );
                    } else {
                        $sku['stock'] = array(
                            0 => ($in_stock - (count($skus) - 1) * $sku_instock),
                        );
                    }
                }
                unset($sku);
            }

            $product->skus = $skus;

            if ($features) {
                $product->features = $features;
            }

            $product->save();
            if ($features) {
                $this->log('Import product features', self::LOG_INFO, array('product_id' => $product->getId(), 'features' => $features));
            }

            if ($services) {
                if (empty($services_model)) {
                    $services_model = new shopProductServicesModel();
                }
                foreach ($services as $service_id => $variants) {
                    $this->log('Import product services', self::LOG_INFO, array('product_id' => $product->getId(), 'name' => $product->name, 'service_id' => $service_id, 'data' => $variants,));
                    //TODO add services for SKUs
                    $services_model->save($product->getId(), $service_id, $variants);
                }
            }

            // update internal offset
            $this->offset[self::STAGE_PRODUCT] = intval($data['productID']);

            $this->map[self::STAGE_PRODUCT][$this->offset[self::STAGE_PRODUCT]] = array(
                'id'     => $product->getId(),
                'sku_id' => array_keys($product->skus),
            );
            $result = true;
            array_shift($product_data_cache);
            ++$current_stage;
            ++$processed;
        }
        return $result;
    }

    private function stepProductReview(&$current_stage, &$count, &$processed)
    {
        static $cache;
        static $model;
        $result = false;
        if (!$current_stage) {
            $this->offset[self::STAGE_PRODUCT_REVIEW] = 0;
        }
        $offset =& $this->offset[self::STAGE_PRODUCT_REVIEW];
        if (!$cache) {
            //TODO use options instead offset
            $sql = 'SELECT * FROM `SC_discussions` WHERE (`DID`> %d) AND (`productID`>0) ORDER BY `DID` LIMIT 100';
            $cache = $this->query(sprintf($sql, $offset), false);
        }
        if ($review = reset($cache)) {
            $id = intval($review['DID']);
            $product_id = intval($review['productID']);
            if (!empty($this->map[self::STAGE_PRODUCT][$product_id])) {
                $product = $this->map[self::STAGE_PRODUCT][$product_id];
                try {
                    if (!$model) {
                        $model = new shopProductReviewsModel();
                    }
                    $data = array(
                        'rate'          => null,
                        'product_id'    => $product['id'],
                        'title'         => $review['Topic'],
                        'text'          => $review['Body'],
                        'name'          => $review['Author'],
                        'datetime'      => date('Y-m-d H:i:s', strtotime($review['add_time'])),
                        'status'        => shopProductReviewsModel::STATUS_PUBLISHED,
                        'auth_provider' => shopProductReviewsModel::AUTH_GUEST,
                        'contact_d'     => 0,
                    );
                    if ($model->add($data)) {
                        ++$processed;
                    } else {
                        $this->log("Error while import product review", self::LOG_ERROR, $data);
                    }
                } catch (Exception $ex) {
                    $this->log($ex->getMessage(), self::LOG_ERROR);
                }
            } else {
                $this->log("Skip product review - related product not found", self::LOG_WARNING, $review);
            }

            $offset = $id;
            $result = true;
            array_shift($cache);
            ++$current_stage;
        }
        return $result;
    }

    private function stepProductFile(&$current_stage, &$count, &$processed)
    {
        $result = false;
        if (!$current_stage) {
            $this->offset[self::STAGE_PRODUCT] = 0;
        }
        $sql = 'SELECT `productID`, `eproduct_filename` FROM `SC_products` WHERE (`productID`> %d) AND (`eproduct_filename` != "") ORDER BY `productID` LIMIT 1';
        if ($product_data = $this->query(sprintf($sql, $this->offset[self::STAGE_PRODUCT]), true)) {
            $product_id = intval($product_data['productID']);
            if (!empty($this->map[self::STAGE_PRODUCT][$product_id])) {
                $product = $this->map[self::STAGE_PRODUCT][$product_id];
                try {
                    $file = $product_data['eproduct_filename'];
                    $model = new shopProductSkusModel();
                    $exists_file = null;
                    foreach ((array)$product['sku_id'] as $sku_id) {
                        $file_path = shopProduct::getPath($product['id'], "sku_file/{$sku_id}.".pathinfo($file, PATHINFO_EXTENSION));
                        if (!$exists_file) {
                            $exists_file = $file_path;
                        }
                        if (file_exists($exists_file)) {
                            if ($exists_file != $file_path) {
                                waFiles::copy($exists_file, $file_path);
                            }
                        } else {
                            $this->moveFile('products_files/'.$file, $file_path, false);
                        }

                        $data = array(
                            'file_size' => filesize($file_path),
                            'file_name' => $file,
                        );
                        $model->updateById($sku_id, $data);
                    }
                    ++$processed;
                } catch (Exception $ex) {
                    $this->log($ex->getMessage(), self::LOG_ERROR);
                }
            }
            ++$current_stage;
            $this->offset[self::STAGE_PRODUCT] = intval($product_data['productID']);
            $result = true;
        }
        return $result;
    }

    private function stepProductImage(&$current_stage, &$count, &$processed)
    {
        static $picture_data;
        $sql = 'SELECT
    `p`.`productID`,
    `p`.`default_picture`,
    `i`.`photoID`,
    `i`.`filename`,
    `i`.`enlarged`
FROM
    `SC_products` `p`
JOIN
    `SC_product_pictures` `i`
    ON
        (`p`.`productID`=`i`.`productID`)
WHERE
    (`photoID` > %d)
ORDER BY `i`.`PhotoID` LIMIT 10';
        $result = false;
        if (!$current_stage) {
            $this->offset[self::STAGE_PRODUCT_IMAGE] = 0;
            $this->map[self::STAGE_PRODUCT_IMAGE_RESIZE] = array();
        }

        if (!$picture_data) {

            $picture_data = $this->query(sprintf($sql, intval($this->offset[self::STAGE_PRODUCT_IMAGE])), false);
        }
        if (!$picture_data && ($current_stage < $count[self::STAGE_PRODUCT_IMAGE])) {
            $this->log(array('message' => 'Empty data for export picture', 'picture_id' => $this->offset[self::STAGE_PRODUCT_IMAGE], $current_stage, $count[self::STAGE_PRODUCT_IMAGE]));
        }
        if ($data = reset($picture_data)) {
            $data['counter'] = $current_stage;
            $product_id = intval($data['productID']);

            if (!empty($this->map[self::STAGE_PRODUCT][$product_id])) {
                if (empty($model)) {
                    $model = new shopProductImagesModel();
                }
                $product = $this->map[self::STAGE_PRODUCT][$product_id];
                $image_id = null;
                try {
                    $original_names = array();
                    if (!empty($data['enlarged'])) {
                        $original_names[] = $data['enlarged'];
                    }
                    if (!empty($data['filename'])) {
                        $original_names[] = $data['filename'];
                    }

                    $original_names = array_unique($original_names);
                    $path = $this->getTempPath('pi');
                    $ex = null;
                    while ($original_name = array_shift($original_names)) {
                        try {
                            $this->moveFile('products_pictures/'.$original_name, $path);
                            $ex = null;
                            break;
                        } catch (Exception $e) {
                            $ex = $e;
                        }
                    }
                    if ($ex) {
                        throw $ex;
                    }
                    if (empty($original_name)) {
                        throw new waException('Missed image');
                    }

                    if (!($image = new waImage($path))) {
                        throw new waException('Incorrect image');
                    }

                    $image_data = array(
                        'product_id'        => $product['id'],
                        'upload_datetime'   => date('Y-m-d H:i:s'),
                        'width'             => $image->width,
                        'height'            => $image->height,
                        'size'              => filesize($path),
                        'original_filename' => empty($data['filename']) ? $data['enlarged'] : $data['filename'],
                        'ext'               => pathinfo($original_name, PATHINFO_EXTENSION),
                        'description'       => basename(empty($data['filename']) ? $data['enlarged'] : $data['filename']),
                    );
                    $image_id = $image_data['id'] = $model->add($image_data, $data['default_picture'] == $data['photoID']);
                    if (!$image_id) {
                        throw new waException("Database error");
                    }

                    $image_path = shopImage::getPath($image_data);
                    if ((file_exists($image_path) && !is_writable($image_path)) || (!file_exists($image_path) && !waFiles::create($image_path))) {
                        throw new waException(sprintf("The insufficient file write permissions for the %s folder.", substr($image_path, strlen($this->getConfig()->getRootPath()))));
                    }

                    waFiles::move($path, $image_path);
                    if (!$this->getConfig()->getOption('image_thumbs_on_demand')) {
                        $this->map[self::STAGE_PRODUCT_IMAGE_RESIZE][] = $image_id;
                        $count[self::STAGE_PRODUCT_IMAGE_RESIZE] = count($this->map[self::STAGE_PRODUCT_IMAGE_RESIZE]);
                    }
                    ++$processed;

                } catch (Exception $ex) {
                    if ($image_id) {
                        $model->deleteById($image_id);
                    }
                    $this->log($ex->getMessage()." ('productID'={$product_id}, 'photoID'={$data['photoID']})", self::LOG_ERROR);
                }

            }
            $this->offset[self::STAGE_PRODUCT_IMAGE] = intval($data['photoID']);
            array_shift($picture_data);
            ++$current_stage;
            $result = true;
        }

        return $result;
    }

    private function stepProductImageResize(&$current_stage, &$count)
    {
        $result = false;
        if ($id = reset($this->map[self::STAGE_PRODUCT_IMAGE_RESIZE])) {

            $image = new shopProductImagesModel();
            if ($image_data = $image->getById($id)) {
                shopImage::generateThumbs($image_data, $this->getConfig()->getImageSizes(), false);
            }
            ++$current_stage;
            array_shift($this->map[self::STAGE_PRODUCT_IMAGE_RESIZE]);
            $result = true;
        }
        return $result;
    }

    private function stepProductSet(&$current_stage, $count)
    {
        $set_data = array(
            'name' => _w('Featured on homepage'),
            'id'   => 'promo',
            'sort' => 0,
        );

        $sets_model = new shopSetModel();
        $sets_model->insert($set_data, 2);

        $set_products = array();
        $sql = 'SELECT `productID` FROM `SC_product_list_item` WHERE `list_id`="%s" ORDER BY `priority`';
        if ($products = $this->query(sprintf($sql, 'specialoffers'), false)) {
            foreach ($products as $product_data) {
                if (isset($this->map[self::STAGE_PRODUCT][$product_data['productID']])) {
                    $product = $this->map[self::STAGE_PRODUCT][$product_data['productID']];
                    $set_products[] = $product['id'];
                }
            }

            if ($set_products) {
                $product_set_model = new shopSetProductsModel();
                $product_set_model->add($set_products, $set_data['id']);
                $sets_model->recount($set_data['id']);
            }

        }
        ++$current_stage;
        return false;
    }

    private function stepOptions(&$current_stage, &$count)
    {
        static $cache = null;
        $result = false;
        if (!$current_stage) {
            $this->offset[self::STAGE_OPTIONS] = 0;
            $this->map[self::STAGE_OPTION_VALUES] = array();
            $this->map[self::STAGE_OPTIONS] = array();
        }
        $offset =& $this->offset[self::STAGE_OPTIONS];
        if (!$cache) {
            //TODO use options instead offset
            $sql = 'SELECT * FROM `SC_product_options` WHERE (`optionID`> %d) ORDER BY `optionID` LIMIT 30';
            $cache = $this->query(sprintf($sql, $offset), false);
        }
        static $feature_model;
        static $type_features_model;
        static $service_model;


        if ($weight = $this->getOption('weight')) {
            @list($base, $target, $code) = explode(':', $weight, 3);
            if ($target == 'f+') {
                $feature = array(
                    'name'       => _wp('Weight'),
                    'code'       => 'weight',
                    'type'       => shopFeatureModel::TYPE_VARCHAR,
                    'multiple'   => 0,
                    'selectable' => 0,
                );
                @list($feature['type'], $feature['multiple'], $feature['selectable']) = explode(':', $code);
                if (empty($feature_model)) {
                    $feature_model = new shopFeatureModel();
                }
                if (empty($type_features_model)) {
                    $type_features_model = new shopTypeFeaturesModel();
                }
                $feature['id'] = $feature_model->save($feature);
                $target = 'f';
                $type_features_model->insert(array('feature_id' => $feature['id'], 'type_id' => $this->getOption('type', 0)), 2);
                $this->setOption('weight', implode(':', array($base, $target, $feature['code'])));
                $this->log('Import weight as feature', self::LOG_INFO, $feature);
            }
        }


        if ($option = reset($cache)) {
            $id = intval($option['optionID']);
            $options = $this->getOption('options');
            $map = null;
            if (isset($options[$id])) {
                $target = $options[$id]['target'];
                if (isset($options[$id][$target])) {
                    $target = $options[$id][$target];
                }
                $target = explode(':', $target, 2);
                switch ($target[0]) {

                    case 'f+':
                        $feature = array(
                            'name'       => $option['name_'.$this->getOption('locale')],
                            'type'       => shopFeatureModel::TYPE_VARCHAR,
                            'multiple'   => 0,
                            'selectable' => 0,
                        );
                        list($feature['type'], $feature['multiple'], $feature['selectable']) = explode(':', $target[1]);
                        if (empty($feature_model)) {
                            $feature_model = new shopFeatureModel();
                        }
                        if (empty($type_features_model)) {
                            $type_features_model = new shopTypeFeaturesModel();
                        }
                        $feature['id'] = $feature_model->save($feature);

                        $type_features_model->insert(array('feature_id' => $feature['id'], 'type_id' => $this->getOption('type', 0)), 2);
                        $map = 'f:'.$feature['code'].':'.ifempty($options[$id]['dimension']);

                        $this->log('Import option as feature', self::LOG_INFO, $feature);
                        break;
                    case 'f':
                        $map = 'f:'.$target[1].':'.ifempty($options[$id]['dimension']);
                        break;

                    case 's+':
                        $service = array(
                            'name'     => $option['name_'.$this->getOption('locale')],
                            'currency' => $this->getOption('currency'),
                            'variants' => array(),
                            'types'    => array(),
                            'products' => array(),
                        );
                        if (empty($service_model)) {
                            $service_model = new shopServiceModel();
                        }
                        $service['id'] = $service_model->save($service);
                        $map = 's:'.$service['id'];

                        $this->log('Import option as service', self::LOG_INFO, $service);
                        break;
                    case 's':
                        $map = 's:'.$target[1];
                        break;
                    case 'sku':
                        $map = 'sku:'.$option['name_'.$this->getOption('locale')];
                        break;
                    default:
                        $this->log('Option ignored', self::LOG_INFO, $options[$id]);
                        break;
                }
                if ($map) {

                    $this->map[self::STAGE_OPTIONS][$id] = $map;
                    if ((strpos($map, 'sku:') !== 0) && (strpos($map, 'type:') !== 0)) {
                        $this->map[self::STAGE_OPTION_VALUES][$id] = $map;
                        $count[self::STAGE_OPTION_VALUES] = count($this->map[self::STAGE_OPTION_VALUES]);
                        $this->log('Add option values map', self::LOG_INFO, array($id => $map));
                    }
                } else {
                    unset($this->map[self::STAGE_OPTIONS][$id]);
                }
            }
            $offset = $id;
            $result = true;
            array_shift($cache);
            ++$current_stage;
        }

        return $result;
    }

    private function stepOptionValues(&$current_stage, $count)
    {
        $result = false;
        if ($map = reset($this->map[self::STAGE_OPTION_VALUES])) {
            $option_id = key($this->map[self::STAGE_OPTION_VALUES]);
            $this->log('Import option values', self::LOG_INFO, array($option_id => $map));
            @list($target, $id) = explode(':', $map, 2);
            switch ($target) {
                case 'f':
                    $dimension = '';
                    if (strpos($id, ':')) {
                        @list($id, $dimension) = explode(':', $id, 2);
                    }
                    $sql = 'SELECT DISTINCT `option_value_%s` `value`
                    FROM `SC_products_opt_val_variants`
                    WHERE `optionID`=%d ORDER BY `sort_order`, `value`';
                    if ($raw_values = $this->query(sprintf($sql, $this->getOption('locale'), $option_id), false)) {
                        $feature_model = new shopFeatureModel();
                        if ($feature = $feature_model->getByField('code', $id)) {
                            $values = array();
                            foreach ($raw_values as $value) {
                                $value = trim($value['value']);
                                if ($dimension && !preg_match('@\d\s+\w+$@u', $value)) {
                                    $value = doubleval($value).' '.$dimension;
                                }
                                $values[] = $value;
                            }
                            $feature_model->setValues($feature, array_unique($values), false, true);
                        } else {
                            $this->log("Feature not found by code ".$id);
                        }
                    }
                    break;
                case 's':
                    $sql = 'SELECT
                    `v`.`option_value_%s` `value`,
                    MAX(`s`.`price_surplus`) `price`
                    FROM `SC_products_opt_val_variants` `v`
                    LEFT JOIN `SC_product_options_set` `s`
                    ON (`v`.`optionID` = `s`.`optionID`) AND (`v`.`variantID` = `s`.`variantID`)
                    WHERE `v`.`optionID`=%d
                    GROUP BY `v`.`variantID`
                    ORDER BY `v`.`sort_order`';
                    if ($raw_values = $this->query(sprintf($sql, $this->getOption('locale'), $option_id), false)) {
                        $service_variants_model = new shopServiceVariantsModel();
                        $service_model = new shopServiceModel();
                        if ($service = $service_model->getById($id)) {
                            $service['variants'] = array();

                            while ($values = array_shift($raw_values)) {
                                $values['value'] = trim($values['value']);
                                if ($variant = $service_variants_model->getByField(array('service_id' => $id, 'name' => $values['value']))) {
                                    if (empty($service['variant_id'])) {
                                        $variant['default'] = true;
                                        $service['variant_id'] = $variant['id'];
                                        $service['variants'][] = $variant;
                                    }
                                } else {
                                    $service['variants'][] = array(
                                        'name'       => $values['value'],
                                        'service_id' => $id,
                                        'price'      => $values['price']
                                    );
                                }
                            }
                            if ($service['variants']) {
                                if (empty($service['variant_id'])) {
                                    $variants[0]['default'] = true;
                                }
                                $service_model->save($service, $id);
                            }
                        } else {
                            $this->log("Service not found by id ".$id);
                        }
                    }
                    break;
            }

            unset($this->map[self::STAGE_OPTION_VALUES][$option_id]);
            ++$current_stage;
            $result = true;
        }
        return $result;
    }

    private function stepCoupon(&$current_stage, &$count, &$processed)
    {
        static $cache;
        static $model;
        static $contact_id;
        $result = false;
        if (!$current_stage) {
            $this->offset[self::STAGE_COUPON] = 0;
            $this->map[self::STAGE_COUPON] = array();

        }
        $offset =& $this->offset[self::STAGE_COUPON];
        if (!$cache) {
            $sql = 'SELECT * FROM `SC_discount_coupons` WHERE (`coupon_id`> %d) ORDER BY `coupon_id` LIMIT 100';
            $cache = $this->query(sprintf($sql, $offset), false);
        }
        if ($coupon_data = reset($cache)) {
            $id = intval($coupon_data['coupon_id']);
            if (!$model) {
                $model = new shopCouponModel();
            }
            if (empty($contact_id)) {
                $contact_id = wa()->getUser()->getId();
            }

            $coupon = array(
                'code'              => $coupon_data['coupon_code'],
                'used'              => 0,
                'type'              => ($coupon_data['discount_type'] == 'P') ? '%' : $this->getOption('currency'),
                'value'             => ($coupon_data['discount_type'] == 'P') ? $coupon_data['discount_percent'] : $coupon_data['discount_absolute'],
                'comment'           => ifempty($coupon_data['comment']),
                'create_datetime'   => date("Y-m-d H:i:s"),
                'create_contact_id' => $contact_id,

            );

            switch ($coupon_data['coupon_type']) {
                case 'SU':
                    $coupon['limit'] = 1;
                    break;
                case 'MX':
                    $coupon['expire_datetime'] = ifempty($coupon_data['expire_date']) ? date("Y-m-d H:i:s", $coupon_data['expire_date']) : null;
                    break;
                case 'MN':
                    break;
            }

            if ($res = $model->insert($coupon, true)) {
                if ($res !== true) {
                    $this->map[self::STAGE_COUPON][$id] = $res;
                }
                ++$processed;
            }

            $offset = $id;
            $result = true;
            array_shift($cache);

            ++$current_stage;
        }

        return false && $result;
    }

    private function stepOrder(&$current_stage, &$count, &$processed)
    {
        static $order_data_cache = array();
        static $order_changelog_cache = array();
        static $order_content_cache = array();
        static $model;
        if (!$model) {
            $model = new shopOrderModel();
        }
        $result = false;

        if (!$current_stage) {
            $this->map[self::STAGE_ORDER] = array(
                'state'         => array(),
                'currency_rate' => 1.0,
                'currency_map'  => array(),
                'address_map'   => array(),
            );
            $currency_model = new shopCurrencyModel();
            if (($rate = $currency_model->getById($currency_value = $this->getOption('currency'))) && ($rate['rate'])) {
                $this->map[self::STAGE_ORDER]['currency_rate'] = doubleval($rate['rate']);
            }
            //color bold italic
            $sql = 'SELECT `status_name_%s` `name`,`color`,`bold`,`italic`,`statusID` FROM `SC_order_status` ORDER BY `sort_order`';
            $state_map =& $this->map[self::STAGE_ORDER]['state'];


            $workflow_config = shopWorkflow::getConfig();
            $states = $this->getOption('status');
            if ($status_names = $this->query(sprintf($sql, $this->getOption('locale')), false)) {
                foreach ($status_names as $status) {
                    if (!empty($states[$status['statusID']])) {
                        $status_id = $states[$status['statusID']];
                        $style = array();

                        if (!empty($status['color'])) {
                            $style['color'] = '#'.$status['color'];
                        }
                        if (!empty($status['bold'])) {
                            $style['font-weight'] = 'bold';
                        }
                        if (!empty($status['italic'])) {
                            $style['font-style'] = 'italic';
                        }
                        if ($status_id === '::new') {
                            $workflow_status = array(
                                'name'              => $status['name'],
                                'options'           => array(
                                    'icon'  => 'icon16 ss flag-white',
                                    'style' => $style,
                                ),
                                'available_actions' => array(),

                            );
                            $status_id = waLocale::transliterate(mb_strtolower($status['name']), 'ru_RU');
                            $status_id = preg_replace('([^a-z_])', '_', $status_id);
                            $status_id = substr(preg_replace('([_]{2,})', '_', $status_id), 0, 16);
                            while (isset($workflow_config['states'][$status_id])) {
                                $status_id = substr(uniqid(substr($status_id, 0, 10)), 0, 16);
                            }

                            $states[$status['statusID']] = $status_id;
                            $workflow_config['states'][$status_id] = $workflow_status;
                        } else {
                            $workflow_config['states'][$status_id]['options']['style'] = $style;
                        }
                    }
                    $this->setOption('status', $states);
                    shopWorkflow::setConfig($workflow_config);
                }
                foreach ($status_names as $status) {

                    if (!empty($states[$status['statusID']])) {
                        $state_map[$status['name']] = $states[$status['statusID']];
                    }
                }

            }

            $this->log('state_name_map', self::LOG_INFO, $state_map);
            unset($state_map);

            $sql = 'SELECT
            LOWER(`c`.`country_name_%1$s`) `country_name`, LOWER(`c`.`country_iso_3`) `country`,
            LOWER(`z`.`zone_name_%1$s`) `region_name`, LOWER(`z`.`zone_code`) `region`
            FROM  `SC_countries` `c`
                LEFT JOIN `SC_zones` `z` ON (`z`.`countryID`= `c`.`countryID`)';
            $address_map =& $this->map[self::STAGE_ORDER]['address_map'];
            if ($address_names = $this->query(sprintf($sql, $this->getOption('locale')), false)) {
                foreach ($address_names as $a) {
                    if (!empty($a['country_name'])) {
                        if (empty($address_map[$a['country_name']])) {
                            $address_map[$a['country_name']] = array(
                                'country' => $a['country'],
                                'regions' => array(),
                            );
                        }
                        if (!empty($a['region_name'])) {
                            $address_map[$a['country_name']]['regions'][$a['region_name']] = $a['region'];
                        }
                    }

                }
            }

            $this->log('addressname_map', self::LOG_INFO, $address_map);
            unset($address_map);

            $currency_map =& $this->map[self::STAGE_ORDER]['currency_map'];
            $currency_map['*'] = $currency_value;
            $currency_map[$currency_value] = $currency_value;
            if (in_array($currency_value, array('RUB', 'RUR'))) {
                $currency_map['RUB'] = $currency_value;
                $currency_map['RUR'] = $currency_value;
            }
            $sql = 'SELECT `currency_value` `rate`,`currency_iso_3` `code` FROM `SC_currency_types`';
            if ($currencies = $this->query($sql, false)) {
                foreach ($currencies as $currency) {
                    $code = $currency['code'];
                    if (!isset($currency_map[$code])) {
                        if ($currency_model->getById($code)) {
                            $currency_map[$code] = $code;
                        } elseif ($currency_model->add($code)) {
                            if (doubleval($currency['rate'])) {
                                $currency_model->updateById($code, array('rate' => $this->map[self::STAGE_ORDER]['currency_rate'] / doubleval($currency['rate'])));
                            } else {
                                $this->log('Currency mapping error: invalid rate', self::LOG_ERROR, $currency);
                            }
                            $currency_map[$code] = $code;
                        } else {
                            $this->log('Currency mapping error: invalid code', self::LOG_ERROR, $currency);
                            $currency_map[$code] = 1.0 / doubleval($currency['rate']);
                        }
                    }
                }
            }
            if (isset($currency_map['RUB'])) {
                $currency_map['RUR'] = $currency_map['RUB'];
            } elseif (isset($currency_map['RUR'])) {
                $currency_map['RUB'] = $currency_map['RUR'];
            }
            $this->log('currency_map', self::LOG_INFO, $currency_map);
            unset($currency_map);
            $this->offset[self::STAGE_ORDER] = 0;
        }
        if (!$order_data_cache) {

            $sql = 'SELECT `o`.*,`c`.`coupon_id`
            FROM `SC_orders` `o`
            LEFT JOIN `SC_orders_discount_coupons` `c` ON (`o`.`orderID` = `c`.`order_id`)
            WHERE (`o`.`orderID` > %d)
            ORDER BY `o`.`orderID`
            LIMIT 20';
            $order_data_cache = $this->query(sprintf($sql, intval($this->offset[self::STAGE_ORDER])), false);
            $ids = array();
            foreach ($order_data_cache as $data) {
                $ids[] = intval($data['orderID']);
            }

            $sql = 'SELECT `s`.*
            FROM `SC_order_status_changelog` `s`
            WHERE (`s`.`orderID` IN (%s))
            ORDER BY `s`.`orderID`,`s`.`status_change_time`
            ';
            $order_changelog_cache = array();
            if ($ids && ($logs = $this->query(sprintf($sql, implode(',', $ids)), false))) {
                while ($log = array_shift($logs)) {
                    $id = intval($log['orderID']);
                    if (!isset($order_changelog_cache[$id])) {
                        $order_changelog_cache[$id] = array();
                    }
                    unset($log['orderID']);
                    $order_changelog_cache[$id][] = $log;
                }
            }

            $sql = 'SELECT `c`.*,`i`.`productID`
            FROM `SC_ordered_carts` `c`
            LEFT JOIN `SC_shopping_cart_items` `i`
            ON (`i`.`itemID` = `c`.`itemID`)
            WHERE (`c`.`orderID` IN (%s))
            ORDER BY `c`.`orderID`
            ';
            $order_content_cache = array();
            if ($ids && ($items = $this->query(sprintf($sql, implode(',', $ids)), false))) {
                while ($item = array_shift($items)) {
                    $id = intval($item['orderID']);
                    if (!isset($order_content_cache[$id])) {
                        $order_content_cache[$id] = array();
                    }
                    unset($item['orderID']);
                    $order_content_cache[$id][] = $item;
                }
            }

        }
        if ($data = reset($order_data_cache)) {

            #orders

            #attach to customer

            #order content
            #link to exists sku and products

            #order changelog

            #map order statuses
            $customer_id = intval($data['customerID']);
            $id = intval($data['orderID']);

            $map = array(
                'orderID'              => 'id',
                'customerID'           => '',
                'order_time'           => 'create_datetime',
                'customer_ip'          => 'params:ip',
                'shipping_type'        => 'params:shipping_name',
                'shipping_module_id'   => '',
                'payment_type'         => 'params:payment_name',
                'payment_module_id'    => '',
                'customers_comment'    => 'comment',
                'statusID'             => '',
                'shipping_cost'        => 'shipping',
                'order_discount'       => 'discount',
                'discount_description' => 'params:discount_description',
                'order_amount'         => 'total',
                'currency_code'        => 'currency',
                'currency_value'       => 'rate',
                'customer_firstname'   => 'params:contact_name',
                'customer_lastname'    => 'params:contact_name',
                'customer_email'       => 'params:contact_email',
                'shipping_firstname'   => 'params:shipping_contact_name',
                'shipping_lastname'    => 'params:shipping_contact_name',
                'shipping_country'     => 'params:shipping_address.country',
                'shipping_state'       => 'params:shipping_address.region',
                'shipping_zip'         => 'params:shipping_address.zip',
                'shipping_city'        => 'params:shipping_address.city',
                'shipping_address'     => 'params:shipping_address.street',
                'billing_firstname'    => 'params:billing_contact_name',
                'billing_lastname'     => 'params:billing_contact_name',
                'billing_country'      => 'params:billing_address.country',
                'billing_state'        => 'params:billing_address.region',
                'billing_zip'          => 'params:billing_address.zip',
                'billing_city'         => 'params:billing_address.city',
                'billing_address'      => 'params:billing_address.street',
                'cc_number'            => '',
                'cc_holdername'        => '',
                'cc_expires'           => '',
                'cc_cvv'               => '',
                'affiliateID'          => '',
                'shippingServiceInfo'  => 'params:shipping_serice',
                'google_order_number'  => '',
                'source'               => '',

                'coupon_id'            => 'params:coupon_id',
            );

            $order = array(
                'params' => array(),
            );
            if ($data['source'] == 'storefront') {
                $data['source'] = $this->getOption('storefront');
                $map['source'] = 'params:storefront';
            } else {
                $map['source'] = '';
            }

            self::dataMap($order, $data, $map);

            if (!empty($this->map[self::STAGE_CUSTOMER][$customer_id])) {
                $order['contact_id'] = $customer_id = $this->map[self::STAGE_CUSTOMER][$customer_id];
            } else {
                $customer_id = null;
            }
            $status = $this->getOption('status');
            if (!empty($status[$data['statusID']])) {
                $order['state_id'] = $status[$data['statusID']];
            }
            //convert rate
            $order['rate'] *= $this->map[self::STAGE_ORDER]['currency_rate'];
            $rate = 1.0;

            $currency_map = $this->map[self::STAGE_ORDER]['currency_map'];
            if (isset($currency_map[$order['currency']])) {
                if (is_double($currency_map[$order['currency']])) {
                    $rate = $currency_map[$order['currency']];
                    $order['total'] *= $rate;
                    $order['shipping'] *= $rate;
                    $order['discount'] *= $rate;
                    $order['rate'] = 1.0;
                    $order['currency'] = $currency_map['*'];
                } else {
                    $order['currency'] = $currency_map[$order['currency']];
                }
            } else {
                $order['currency'] = $this->getOption('currency');
            }
            if ($model->countByField('id', $order['id'])) {
                $tables = array(
                    'shop_order_items',
                    'shop_order_log',
                    'shop_order_log_params',
                    'shop_order_params',
                );
                foreach ($tables as $table) {
                    $model->query(sprintf("DELETE FROM `%s` WHERE `order_id`=%d", $table, $order['id']));
                }
                $model->query(sprintf("UPDATE `shop_customer` SET `last_order_id`=NULL WHERE `last_order_id`=%d", $order['id']));
                $model->deleteById($order['id']);
            }
            $model->insert($order);

            //check it
            $items_model = new shopOrderItemsModel();

            foreach ($order_content_cache[$id] as $item) {
                $product = ifset($this->map[self::STAGE_PRODUCT][$item['productID']], array());
                $items_model->insert(array(
                    'order_id'   => $order['id'],
                    'type'       => 'product',
                    'name'       => $item['name'],
                    'quantity'   => $item['Quantity'],
                    'price'      => doubleval($item['Price']) * $rate,
                    'currency'   => $data['currency_code'],
                    'product_id' => ifset($product['id']),
                    'sku_id'     => ifset($product['sku_id']),
                ));

            }

            //order params

            $order['params']['auth_code'] = shopWorkflowCreateAction::generateAuthCode($order['id']);
            $order['params']['auth_pin'] = shopWorkflowCreateAction::generateAuthPin();
            if (!empty($order['params'])) {

                $params = array_map('trim', $order['params']);

                if (!empty($params['coupon_id'])) {
                    if (!empty($this->map[self::STAGE_COUPON][$params['coupon_id']])) {
                        $params['coupon_id'] = $this->map[self::STAGE_COUPON][$params['coupon_id']];
                        $cm = new shopCouponModel();
                        $cm->useOne($params['coupon_id']);
                    } else {
                        unset($params['coupon_id']);
                    }
                }

                $address_map = $this->map[self::STAGE_ORDER]['address_map'];
                if (!empty($params['shipping_address.country'])) {
                    $country = mb_strtolower($params['shipping_address.country'], 'utf-8');
                    if (!empty($address_map[$country])) {
                        $map = $address_map[$country];

                        if (!empty($params['shipping_address.region'])) {
                            $region = mb_strtolower($params['shipping_address.region'], 'utf-8');
                            if (!empty($map['regions'][$region])) {
                                $params['shipping_address.region'] = $map['regions'][$region];
                            }
                        }
                        $params['shipping_address.country'] = $map['country'];
                    }
                }

                if (!empty($params['billing_address.country'])) {
                    $country = mb_strtolower($params['billing_address.country'], 'utf-8');
                    if (!empty($address_map[$country])) {
                        $map = $address_map[$country];

                        if (!empty($params['billing_address.region'])) {
                            $region = mb_strtolower($params['billing_address.region'], 'utf-8');
                            if (!empty($map['regions'][$region])) {
                                $params['billing_address.region'] = $map['regions'][$region];
                            }
                        }
                        $params['billing_address.country'] = $map['country'];
                    }
                }

                $params_model = new shopOrderParamsModel();
                $params_model->set($order['id'], $params);
            }

            //changelog
            if (!empty($order_changelog_cache[$id])) {
                $log_model = new shopOrderLogModel();
                $state = '';
                $payd = false;
                $first = true;
                foreach ($order_changelog_cache[$id] as $log) {
                    $after_state = null;
                    if (!empty($this->map[self::STAGE_ORDER]['state'][$log['status_name']])) {
                        $after_state = $this->map[self::STAGE_ORDER]['state'][$log['status_name']];
                    }
                    $log_model->insert(array(
                        'order_id'        => $order['id'],
                        'contact_id'      => $first ? $order['contact_id'] : null,
                        'action_id'       => '',
                        'datetime'        => $log['status_change_time'],
                        'text'            => ifset($log['status_comment']),
                        'before_state_id' => $state,
                        'after_state_id'  => $after_state,
                    ));
                    //XXX hardcode
                    //TODO add settings
                    if (!$payd && in_array($after_state, array('completed', 'paid'))) {
                        $timestamp = strtotime($log['status_change_time']);
                        $model->updateById($order['id'], array(
                            'paid_year'    => date('Y', $timestamp),
                            'paid_quarter' => date('n', $timestamp),
                            'paid_month'   => floor((date('n', $timestamp) - 1) / 3) + 1,
                            'paid_date'    => date('Y-m-d', $timestamp),
                        ));
                        $payd = true;
                    }
                    $first = false;
                    $state = $after_state;
                }
            }

            if ($customer_id) {
                $scm = new shopCustomerModel();
                $scm->updateFromNewOrder($customer_id, $order['id']);
                shopCustomers::recalculateTotalSpent($customer_id);
            }
            $model->recalculateProductsTotalSales();
            // update internal offset
            $this->offset[self::STAGE_ORDER] = $id;
            $result = true;
            array_shift($order_data_cache);
            ++$current_stage;
            ++$processed;
            if ($current_stage == $count[self::STAGE_ORDER]) {
                $model->recalculateProductsTotalSales();
                $model->query('UPDATE shop_order o
        JOIN (SELECT contact_id, MIN(id) id FROM `shop_order` WHERE paid_date IS NOT NULL GROUP BY contact_id) AS f
        ON o.id = f.id
        SET o.is_first = 1');
            }
        } else {

            $model->query('UPDATE shop_order o
        JOIN (SELECT contact_id, MIN(id) id FROM `shop_order` WHERE paid_date IS NOT NULL GROUP BY contact_id) AS f
        ON o.id = f.id
        SET o.is_first = 1');
        }
        return $result;
    }

    private function stepPages(&$current_stage, &$count, &$processed)
    {
        static $data_cache = array();
        static $pages_model;
        $result = false;

        if (!$data_cache) {
            $sql = <<<SQL
SELECT * FROM SC_aux_pages
WHERE `aux_page_ID`>%d
ORDER BY `aux_page_ID`
LIMIT 20
SQL;
            if (!isset($this->offset[self::STAGE_PAGES])) {
                $this->offset[self::STAGE_PAGES] = -1;
            }
            $data_cache = $this->query(sprintf($sql, intval($this->offset[self::STAGE_PAGES])), false);
        }
        if ($row = reset($data_cache)) {
            $locale = $this->getOption('locale');

            $params = array(
                'keywords'    => ifset($row['meta_keywords_'.$locale]),
                'description' => ifset($row['meta_description_'.$locale]),
            );


            $data = array(
                'domain'   => 'localhost',
                'route'    => '*',
                'name'     => ifset($row['aux_page_name_'.$locale]),
                'url'      => $row['aux_page_slug'].'/',
                'full_url' => $row['aux_page_slug'].'/',
                'content'  => ifset($row['aux_page_text_'.$locale]),
                'status'   => !empty($row['aux_page_enabled']),

            );
            @list($data['domain'], $data['route']) = explode(':', $this->getOption('domain', 'localhost:*'));
            if (empty($pages_model)) {
                $pages_model = new shopPageModel();
            }
            $data['id'] = $pages_model->add($data);
            if ($params = array_filter($params)) {
                $pages_model->setParams($data['id'], $params);
            }
            $this->offset[self::STAGE_PAGES] = intval($row['aux_page_priority']);

            $result = true;
            $this->offset[self::STAGE_PAGES] = $row['aux_page_ID'];
            array_shift($data_cache);
            ++$current_stage;
            ++$processed;
        }
        return $result;
    }

    public function settingOptionsControl($name, $params = array())
    {
        $control = '';
        $options = $this->query('SELECT * FROM `SC_product_options` ORDER BY `sort_order`', false);
        if ($options) {
            foreach ($params as $field => $param) {
                if (strpos($field, 'wrapper')) {
                    unset($params[$field]);
                }
            }
            if (!isset($params['value']) || !is_array($params['value'])) {
                $params['value'] = array();
            }

            waHtmlControl::addNamespace($params, $name);

            $params['control_wrapper'] = '<tr><td>%s</td><td>&rarr;</td><td>%s</td></tr>';
            $params['control_separator'] = '</td></tr>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>';
            $params['title_wrapper'] = '%s';

            $control .= "<table class = \"zebra\"><tbody>";
            $locale = $this->getOption('locale');
            while ($option = array_shift($options)) {
                $name = $option['optionID'];
                $option_params = $params;
                $option_params['title'] = $option['name_'.$locale];
                $option_params['value'] = isset($params['value'][$name]) ? $params['value'][$name] : array('target' => null,);
                $control .= waHtmlControl::getControl('OptionMapControl', $name, $option_params);
            }
            $control .= "</tbody>";
            $control .= "</table>";
        } else {
            $control .= _wp('There no options to import');
        }
        return $control;
    }

    public function settingCustomersControl($name, $params = array())
    {
        $control = '';

        $sql = 'SELECT
            `reg_field_ID` `id`, `reg_field_name_%s` `name`
            FROM `SC_customer_reg_fields`
            ORDER BY `sort_order`, `name`';
        $fields = $this->query(sprintf($sql, $this->getOption('locale')), false);

        if ($fields) {
            foreach ($params as $field => $param) {
                if (strpos($field, 'wrapper')) {
                    unset($params[$field]);
                }
            }
            if (!isset($params['value']) || !is_array($params['value'])) {
                $params['value'] = array();
            }

            waHtmlControl::addNamespace($params, $name);

            $params['control_wrapper'] = '<tr><td>%s</td><td>&rarr;</td><td>%s</td></tr>';
            $params['title_wrapper'] = '%s';

            $params['options'] = array();
            $params['options'][] = array(
                'value' => '',
                'title' => _wp('Ignore this field'),
            );


            $params['options'][] = array(
                'value' => '::new',
                'title' => _wp('Add as a new contact field'),
            );


            foreach (waContactFields::getAll() as $contact_field) {

                if ($contact_field instanceof waContactCompositeField) {
                    /**
                     * @var waContactCompositeField $contact_field
                     */
                    foreach ($contact_field->getFields() as $contact_subfield) {
                        /**
                         * @var waContactField $contact_subfield
                         */
                        $field = array(
                            'group' => $contact_field->getName(),
                            'value' => $contact_field->getId().':'.$contact_subfield->getId(),
                            'title' => $contact_subfield->getName(),

                        );
                        $field['suggestion'] = mb_strtolower($field['title'], 'utf-8');
                        $params['options'][] = $field;

                    }

                } else {
                    /**
                     * @var waContactField $contact_field
                     */
                    $field = array(
                        'value' => $contact_field->getId(),
                        'title' => $contact_field->getName(),

                    );

                    $field['suggestion'] = mb_strtolower($field['title'], 'utf-8');
                    $params['options'][] = $field;
                }
            }

            $control .= "<table class = \"zebra\"><tbody>";
            while ($field = array_shift($fields)) {
                $name = $field['id'];
                $field_params = $params;
                $field_params['title'] = $field['name'];

                if (isset($params['value'][$name])) {
                    $field_params['value'] = $params['value'][$name];
                } else {
                    $field_params['value'] = '';
                    $field['name'] = mb_strtolower($field['name'], 'utf-8');
                    foreach ($params['options'] as $option) {
                        if (!empty($option['suggestion']) && ($option['suggestion'] == $field['name'])) {
                            $field_params['value'] = $option['value'];
                            break;
                        }
                    }
                }

                $control .= waHtmlControl::getControl(waHtmlControl::SELECT, $name, $field_params);
            }
            $control .= "</tbody>";
            $control .= "</table>";
        } else {
            $control .= _wp('There no customer fields to import');
        }
        return $control;
    }

    public function settingOptionMapControl($name, $params = array())
    {
        static $suggests_features = array();
        static $suggests_services = array();
        $control = '';

        foreach ($params as $field => $param) {
            if (strpos($field, 'wrapper')) {
                unset($params[$field]);
            }
        }
        if (!isset($params['value']) || !is_array($params['value'])) {
            $params['value'] = array();
        }
        $suggest = mb_strtolower($params['title']);
        unset($params['title']);
        waHtmlControl::addNamespace($params, $name);
        $params['description_wrapper'] = '<br><span class="hint">%s</span>';

        $params['title_wrapper'] = '%s';
        $params['options'] = array();

        $target_params = $params;
        $target_options = array(
            array(
                'value'       => 'feature',
                'title'       => _wp('Feature'),
                'description' => _wp('Content will be imported as a fixed descriptive product field'),
            ),
            array(
                'value'       => 'service',
                'title'       => _wp('Service'),
                'description' => _wp('Services feature allows customers to customize product when adding it to shopping cart (either select or unselect particular service with the product)'),
            ),
            array(
                'value'       => 'sku',
                'title'       => _wp('SKU'),
                'description' => _wp('SKUs feature allows tracking inventory by multiple stocks. Multiple product SKUs (purchase options) will be created according to this custom option value set'),
            ),
            array(
                'value' => '',
                'title' => _wp("Don't import"),
            ),
        );
        $target_params['value'] = $params['value']['target'];
        $suggested = !empty($target_params['value']);

        $feature_params = $params;
        $feature_options = $this->getFeaturesOptions($suggests_features, true);
        if (count($feature_options) > 1) {

            $feature_params['options'] = $feature_options;
            if (empty($params['value']['feature'])) {
                if (($feature_params['value'] = array_search($suggest, $suggests_features)) && !$suggested) {
                    $suggested = true;
                    $target_params['value'] = 'feature';
                }
            } else {
                $feature_params['value'] = $params['value']['feature'];
            }
            $feature_control = waHtmlControl::SELECT;
            $service_params['description'] = $target_options[0]['description'];
            $target_options[0]['description'] = '';

        } else {
            $value = reset($feature_options);
            $feature_params['value'] = $value['value'];
            $feature_control = waHtmlControl::HIDDEN;
        }

        $dimension_params = $params;
        $dimension_params['options'] = self::getFeatureDimensions();

        $service_options = $this->getServicesOptions($suggests_services);

        $service_params = $params;
        if (count($service_options) > 1) {

            $service_params['options'] = $service_options;
            if (empty($params['value']['service'])) {
                if (($service_params['value'] = array_search($suggest, $suggests_services)) && !$suggested) {
                    $suggested = true;
                    $target_params['value'] = 'service';
                }
            } else {
                $service_params['value'] = $params['value']['service'];
            }
            $service_params['description'] = $target_options[1]['description'];
            $target_options[1]['description'] = '';
            $service_control = waHtmlControl::SELECT;

        } else {
            $value = reset($service_options);
            $service_params['value'] = $value['value'];
            $service_control = waHtmlControl::HIDDEN;
        }

        if (!$suggested) {
            $target_params['value'] = 'feature';
        }

        $target_params['options'] = array_slice($target_options, 0, 1);
        $control .= waHtmlControl::getControl(waHtmlControl::RADIOGROUP, 'target', $target_params);
        $control .= waHtmlControl::getControl($feature_control, 'feature', $feature_params);
        $control .= waHtmlControl::getControl(waHtmlControl::SELECT, 'dimension', $dimension_params);
        $control .= $params['control_separator'];

        $target_params['options'] = array_slice($target_options, 1, 1);
        $control .= waHtmlControl::getControl(waHtmlControl::RADIOGROUP, 'target', $target_params);
        $control .= waHtmlControl::getControl($service_control, 'service', $service_params);
        $control .= $params['control_separator'];
        $target_params['options'] = array_slice($target_options, 2);
        $control .= waHtmlControl::getControl(waHtmlControl::RADIOGROUP, 'target', $target_params);

        $feature_name = preg_replace("@([\[\]])@", '\\\\$1', waHtmlControl::getName($feature_params, 'feature'));
        $dimension_name = preg_replace("@([\[\]])@", '\\\\$1', waHtmlControl::getName($dimension_params, 'dimension'));

        $control .= <<<HTML
<script type="text/javascript">
if(typeof(\$) == 'function') {

$(':input[name="{$feature_name}"]:first').unbind('change.migrate').bind('change.migrate',function(){
    var input = $(this);
    var type = input.val().match(/(dimension\.){1,}([^:]+)/);
    var option = input.find('option:selected:first');
    if(type && type[2]){
        type = type[2]
    } else if (option.length && (type = (''+option.prop('class')).match(/\bjs-type-dimension\.([\w]+)\b/)) && type[1]) {
        type = type[1];
    } else {
        type = 'none';
    }


    var dimension = $(':input[name="{$dimension_name}"]:first');
    dimension.val('');
    dimension.find('option').each(function(){
        var option =$(this);
        var disabled = (option.hasClass('js-type-null') || option.hasClass('js-type-'+type))?null:true;
        option.attr('disabled',disabled);
        if(disabled){
            option.hide();
        } else {
            option.show();
            if(option.hasClass('js-base-type')){
                dimension.val(option.val());
            }
        }
    })

}).trigger('change');
}
</script>
HTML;


        return $control;
    }

    public function settingStatusControl($name, $params = array())
    {
        foreach ($params as $field => $param) {
            if (strpos($field, 'wrapper')) {
                unset($params[$field]);
            }
        }
        $control = '';
        if (!isset($params['value']) || !is_array($params['value'])) {
            $params['value'] = array();
        }

        waHtmlControl::addNamespace($params, $name);
        $control .= '<table class="zebra">';
        $params['description_wrapper'] = '%s';
        $params['title_wrapper'] = '%s';
        $params['control_wrapper'] = '<tr title="%3$s"><td>%1$s</td><td>&rarr;</td><td>%2$s</td></tr>';
        $params['size'] = 6;
        $workflow = new shopWorkflow();
        $states = $workflow->getAvailableStates();
        $source_states = $params['options'];
        $params['options'] = array();
        $params['options'][] = _wp('Select target order state');

        $params['options']['::new'] = _wp('Add as new order state');

        foreach ($states as $id => $state) {
            $params['options'][$id] = $state['name'];
        }

        $predefined = array(
            2  => 'new',
            3  => 'processing',
            1  => 'deleted',
            14 => 'paid',
            5  => 'shipped',
            15 => 'refunded',
        );
        foreach ($source_states as $id => $state) {
            $control_params = $params;
            $control_params['value'] = (isset($predefined[$id]) && isset($states[$predefined[$id]])) ? $predefined[$id] : null;
            $control_params['title'] = $state['name'];
            if (!empty($state['wrapper'])) {
                $control_params['title_wrapper'] = $state['wrapper'];
            } else {
                $control_params['title_wrapper'] = '%s';
            }
            $control_params['value'] = self::findSimilar($control_params, null, array('similar' => true));
            $control .= waHtmlControl::getControl(waHtmlControl::SELECT, $id, $control_params);
        }
        $control .= "</table>";

        return $control;

    }

    private function getFeaturesOptions(&$suggests, $full = false, $multiple = true)
    {
        static $features_options = null;
        if ($features_options === null) {
            $translates = array();
            $translates['Add as new feature'] = _wp('Add as new feature');
            $translates['Feature'] = _wp('Add to existing');

            $features_options = array();
            if ($full) {
                $z = shopFeatureModel::getTypes();
                foreach ($z as $f) {
                    if ($f['available']) {
                        if (empty($f['subtype'])) {
                            if ($multiple || (empty($f['multiple']) && !preg_match('@^(range|2d|3d)\.@', $f['type']))) {
                                $features_options[] = array(
                                    'group' => & $translates['Add as new feature'],
                                    'value' => sprintf("f+:%s:%d:%d", $f['type'], $f['multiple'], $f['selectable']),
                                    'title' => empty($f['group']) ? $f['name'] : ($f['group'].': '.$f['name']),
                                );
                            }
                        } else {
                            foreach ($f['subtype'] as $sf) {
                                if ($sf['available']) {
                                    $type = str_replace('*', $sf['type'], $f['type']);
                                    if ($multiple || (empty($f['multiple']) && !preg_match('@^(range|2d|3d)\.@', $type))) {
                                        $features_options[] = array(
                                            'group' => & $translates['Add as new feature'],
                                            'value' => sprintf("f+:%s:%d:%d", $type, $f['multiple'], $f['selectable']),
                                            'title' => (empty($f['group']) ? $f['name'] : ($f['group'].': '.$f['name']))." — {$sf['name']}",

                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                $features_options = array(
                    array(
                        'value' => 'f+:varchar:0:1',
                        'title' => & $translates['Add as new feature'],
                    )
                );
            }

            $features_model = new shopFeatureModel();
            $features = $features_model->getAll();
            $suggests = array();
            foreach ($features as $feature) {
                if (empty($feature['parent_id']) && ($multiple || (empty($feature['multiple']) && !preg_match('@^(range|2d|3d)\.@', $feature['type'])))) {
                    $features_options[] = array(
                        'group'       => & $translates['Feature'],
                        'value'       => sprintf('f:%s', $feature['code']),
                        'title'       => $feature['name'],
                        'description' => $feature['code'],
                        'class'       => 'js-type-'.$feature['type'],
                    );
                }
                $suggests[sprintf('f:%s', $feature['code'])] = mb_strtolower($feature['name']);
            }
        }
        return $features_options;
    }

    private static function getFeatureDimensions()
    {
        static $options = null;
        if ($options === null) {
            $options = array();
            $options[] = array(
                'title' => _wp('Without dimension'),
                'value' => '',
                'class' => 'js-type-null',
            );
            $dimension = shopDimension::getInstance();
            foreach ($dimension->getList() as $type => $info) {
                foreach ($info['units'] as $code => $unit) {
                    $options[] = array(
                        //          'group' => $info['name'],
                        'value' => $code,
                        'title' => $unit['name'],
                        'class' => 'js-type-'.$type.(($info['base_unit'] == $code) ? ' js-base-type' : ''),
                        'style' => ($info['base_unit'] == $code) ? 'font-weight:bold;' : '',
                    );
                }
            }
        }
        return $options;
    }

    private static function dataMap(&$result, $data, $map)
    {
        foreach ($map as $field => $target) {
            if ($target && isset($data[$field])) {
                if (strpos($target, ':')) {
                    if (!empty($data[$field])) {
                        list($target, $sub_target) = explode(':', $target, 2);
                        if (empty($result[$target][$sub_target])) {
                            $result[$target][$sub_target] = '';
                        } else {
                            $result[$target][$sub_target] .= ' ';
                        }
                        $result[$target][$sub_target] .= $data[$field];
                    }
                } else {
                    $result[$target] = $data[$field];
                }
            }
        }
    }

    private function getServicesOptions(&$suggests)
    {
        static $service_options = null;
        if ($service_options === null) {
            $service_options = array();
            $service_options[] = array(
                'value' => "s+:0",
                'title' => _wp('Add as new service'),
            );
            $services_model = new shopServiceModel();
            $services = $services_model->getAll();
            foreach ($services as $service) {
                $service_options[] = array(
                    'group' => _wp('Add to existing'),
                    'value' => sprintf('s:%s', $service['id']),
                    'title' => $service['name'],
                );
                $suggests[sprintf('s:%s', $service['id'])] = mb_strtolower($service['name']);
            }
        }
        return $service_options;
    }


    private static function findSimilar(&$params, $target = null, $options = array())
    {
        if ($target === null) {
            $target = empty($params['title']) ? ifset($params['description']) : $params['title'];
        }
        $selected = null;
        if ($target && empty($params['value'])) {
            $max = $p = 0;
            //init data structure
            foreach ($params['options'] as $id => & $column) {
                if (!is_array($column)) {
                    $column = array(
                        'title' => $column,
                        'value' => $id,
                    );
                }
                $column['like'] = 0;
            }

            if (!empty($options['similar'])) {
                foreach ($params['options'] as & $column) {
                    similar_text($column['title'], $target, $column['like']);
                    if ($column['like'] >= 90) {
                        $max = $column['like'];
                        $selected =& $column;
                    } else {
                        $column['like'] = 0;
                    }
                    unset($column);
                }
            }

            if ($max < 90) {
                unset($selected);
                $max = 0;
                $to = mb_strtolower($target);
                foreach ($params['options'] as & $column) {
                    if ($column['like'] < 90) {
                        $from = mb_strtolower($column['title']);
                        if ($from && $to && ((strpos($from, $to) === 0) || (strpos($to, $from) === 0))) {
                            $l_from = mb_strlen($from);
                            $l_to = mb_strlen($to);
                            $column['like'] = 100 * min($l_from, $l_to) / max($l_from, $l_to, 1);
                            if ($column['like'] > $max) {
                                $selected =& $column;
                                $max = $column['like'];
                            }
                        }
                    }
                    unset($column);
                }
            }

            if (!empty($selected)) {
                $selected['style'] = 'font-weight:bold;text-decoration:underline;';
                $params['value'] = $selected['value'];
            } elseif ((func_num_args() < 2) && !empty($params['title']) && !empty($params['description'])) {
                self::findSimilar($params, $params['description'], $options);
            }
        }
        return $params['value'];
    }

    abstract protected function query($sql, $one = true);

    abstract protected function moveFile($path, $target, $public = true);
}
