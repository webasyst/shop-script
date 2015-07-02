<?php

abstract class shopMigrateTransport implements Serializable
{
    const LOG_DEBUG = 5;
    const LOG_INFO = 4;
    const LOG_NOTICE = 3;
    const LOG_WARNING = 2;
    const LOG_ERROR = 1;

    private $temp_path;
    private $process_id = 'common';
    /**
     *
     * @var shopConfig
     */
    private $wa;

    private $options = array();
    protected $map = array();
    protected $offset = array();

    /**
     * @var waModel
     */
    protected $dest;

    const STAGE_CATEGORY = 'category';
    const STAGE_CATEGORY_REBUILD = 'categoryRebuild';

    const STAGE_TAX = 'tax';

    const STAGE_CUSTOMER = 'customer';
    const STAGE_CUSTOMER_CATEGORY = 'customerCategory';

    const STAGE_OPTIONS = 'options';
    const STAGE_OPTION_VALUES = 'optionValues';

    const STAGE_PRODUCT = 'product';
    const STAGE_PRODUCT_REVIEW = 'productReview';
    const STAGE_PRODUCT_FILE = 'productFile';
    const STAGE_PRODUCT_IMAGE = 'productImage';
    const STAGE_PRODUCT_IMAGE_RESIZE = 'productImageResize';
    const STAGE_PRODUCT_SET = 'productSet';
    const STAGE_PRODUCT_CATEGORY = 'productCategory';

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
            case self::STAGE_PRODUCT_CATEGORY:
                $name = _wp('Updating product categories');
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
                case self::STAGE_OPTIONS:
                    $report = _wp("%d product's option", "%d product's options", $count);
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

    /**
     * Get migrate transport instance
     * @param string $id transport id
     * @param array $options
     * @param string $process_id
     * @throws waException
     * @return shopMigrateTransport
     */
    public static function getTransport($id, $options = array(), $process_id = null)
    {
        $class = 'shopMigrate'.ucfirst($id).'Transport';
        if ($id && class_exists($class)) {

            if (isset($options['transport'])) {
                unset($options['transport']);
            }
            /**
             * @var shopMigrateTransport $transport
             */
            $transport = new $class($options, $process_id);
        } else {
            throw new waException('Transport not found');
        }
        if (!($transport instanceof self)) {
            throw new waException('Invalid transport');
        }

        return $transport;
    }

    protected function __construct($options = array(), $process_id = 'common')
    {
        $this->process_id = $process_id;
        $this->initOptions();
        foreach ($options as $k => $v) {
            $this->setOption($k, $v);
        }
        $this->dest = new waModel();
    }

    public function __wakeup()
    {
        $this->dest = new waModel();
    }

    public function validate($result, &$errors)
    {
        if ($result) {
            $cached = null;
            $storage = null;
            foreach ($this->options as $name => $option) {
                if (!empty($option['cache'])) {
                    if (is_null($cached)) {
                        $storage = new waSessionStorage();
                        $cached = $storage->get($this->getStorageKey());
                        if (!is_array($cached)) {
                            $cached = array();
                        }
                    }
                    $cached[$name] = $this->getOption($name);
                }
            }
            if ($cached && $storage) {
                $storage->set($this->getStorageKey(), $cached);
            }
        }
        if ($errors) {
            $this->log('Validate errors', self::LOG_NOTICE, $errors);
        }
        return true && $result;
    }

    public function init()
    {

    }

    abstract public function step(&$current, &$count, &$processed, $stage, &$error);

    /**
     * @param array $current
     * @param string $stage
     * @param array $error
     * @param \Exception|\waException $ex
     * @throws Exception|waException
     */
    protected function stepException($current, $stage, &$error, Exception $ex)
    {
        sleep(5);
        $message = ifset($stage, 'unknown stage').': '.$ex->getMessage();
        $message .= (empty($error) ? 'first' : 'repeat');
        $message .= "\n".$ex->getTraceAsString();
        $this->log($message, self::LOG_ERROR);
        if (!empty($error)) {
            if (($error['stage'] == $stage)
                && ($error['iteration'] == $current[$stage])
                && ($error['code'] == $ex->getCode())
                && ($error['message'] == $ex->getMessage())
            ) {
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

    protected function addProductImage($product_id, $file, $name = null, $description = null)
    {
        $processed = 0;
        /**
         * @var shopProductImagesModel $model
         */
        static $model;
        if (!$model) {
            $model = new shopProductImagesModel();
        }
        if ($image = waImage::factory($file)) {
            if ($name === null) {
                $name = basename($file);
            }
            $data = array(
                'product_id'        => $product_id,
                'upload_datetime'   => date('Y-m-d H:i:s'),
                'description'       => $description,
                'width'             => $image->width,
                'height'            => $image->height,
                'size'              => filesize($file),
                'original_filename' => $name,
                'ext'               => pathinfo($name, PATHINFO_EXTENSION),
            );

            $image_changed = false;

            /**
             * Extend add/update product images
             * Make extra workup
             * @event image_upload
             */
            $event = wa()->event('image_upload', $image);
            if ($event) {
                foreach ($event as $result) {
                    if ($result) {
                        $image_changed = true;
                        break;
                    }
                }
            }

            if (!($data['id'] = $model->add($data))) {
                throw new waException("Database error");
            }

            $image_path = shopImage::getPath($data);
            if (
                (file_exists($image_path) && !is_writable($image_path))
                ||
                (!file_exists($image_path) && !waFiles::create($image_path))
            ) {
                $model->deleteById($data['id']);
                throw new waException(
                    sprintf(
                        "The insufficient file write permissions for the %s folder.",
                        substr($image_path, strlen($this->getConfig()->getRootPath()))
                    )
                );
            }


            if ($image_changed) {
                $image->save($image_path);
                if ($this->getConfig()->getOption('image_save_original') && ($original_file = shopImage::getOriginalPath($data))) {
                    waFiles::copy($file, $original_file);
                }
            } else {
                waFiles::copy($file, $image_path);
            }
            ++$processed;
        } else {
            $this->log(sprintf('Invalid image file', $file), self::LOG_ERROR);
        }
        return $processed;
    }

    /**
     * @return string[string]
     */
    abstract public function count();

    private static function getLogLevelName($level)
    {
        $name = '';
        switch ($level) {
            case self::LOG_DEBUG:
                $name = 'Debug';
                break;
            case self::LOG_INFO:
                $name = 'Info';
                break;
            case self::LOG_NOTICE:
                $name = 'Notice';
                break;
            case self::LOG_WARNING:
                $name = 'Warning';
                break;
            case self::LOG_ERROR:
                $name = 'Error';
                break;
        }

        return $name;
    }

    protected function log($message, $level = self::LOG_WARNING, $data = null)
    {
        if (class_exists('waDebug')) {

        }
        if ($level <= $this->getOption('debug', self::LOG_WARNING)) {
            if (!is_string($message)) {
                $message = var_export($message, true);
            }
            if ($data) {
                if (!is_string($data)) {
                    $message .= "\n".var_export($data, true);
                } else {
                    $message .= "\n".$data;
                }
            }
            waLog::log($this->process_id.': '.$this->getLogLevelName($level).': '.$message, 'shop/plugins/migrate.log');
        }
    }

    /**
     *
     * @param string $file_prefix
     * @return string
     */
    protected function getTempPath($file_prefix = null)
    {
        if (!$this->temp_path) {
            $this->temp_path = wa()->getTempPath('plugins/migrate/'.$this->process_id.'/', 'shop');
            // waFiles::create($this->temp_path);
        }

        return ($file_prefix === null) ? $this->temp_path : tempnam($this->temp_path, $file_prefix);
    }

    /**
     *
     * @return shopConfig
     */
    protected function getConfig()
    {
        if (!$this->wa) {
            $this->wa = wa()->getConfig();
        }

        return $this->wa;
    }

    public function restore()
    {
        shopProductStocksLogModel::setContext(shopProductStocksLogModel::TYPE_IMPORT, $this->getContextDescription());
    }

    public function finish()
    {
        shopProductStocksLogModel::clearContext();
        waFiles::delete($this->getTempPath(), true);
    }

    protected function getRouteOptions(&$option)
    {
        $routing = wa()->getRouting();
        $domain_routes = $routing->getByApp('shop');
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $route) {
                $option['options'][] = array(
                    'value' => $domain.':'.$route['url'],
                    'title' => $domain.'/'.$route['url'],
                );
            }
        }
    }

    protected function initOptions()
    {
        if ($this->getConfig()->isDebug()) {
            $debug_levels = array(
                self::LOG_WARNING => _wp('Errors only'),
                self::LOG_DEBUG   => _wp('Debug (detailed log)'),
            );
            $option = array(
                'value'        => self::LOG_WARNING,
                'control_type' => waHtmlControl::SELECT,
                'title'        => _wp('Log level'),
                'options'      => array(),
            );
            $option['options'] = $debug_levels;
            $this->addOption('debug', $option);
        }
        waHtmlControl::registerControl('OptionMapControl', array(&$this, "settingOptionMapControl"));
        waHtmlControl::registerControl('CustomersControl', array(&$this, "settingCustomersControl"));
    }

    protected function getOption($name, $default = null)
    {
        if (isset($this->options[$name]['value'])) {
            $value = $this->options[$name]['value'];
        } else {
            $value = $default;
        }

        return $value;
    }

    protected function getOptionSource($name)
    {
        return isset($this->options[$name]) ? $this->options[$name] : null;
    }

    protected function setOption($name, $value)
    {
        if (!isset($this->options[$name])) {
            $this->options[$name] = array(
                'control_type' => waHtmlControl::HIDDEN,
            );
        }
        $this->options[$name]['value'] = $value;
    }

    protected function addOption($name, $option = null)
    {
        if ($option) {
            if (!isset($this->options[$name])) {
                $this->options[$name] = array_merge(
                    array(
                        'control_type' => waHtmlControl::HIDDEN,
                        'value'        => null,
                    ),
                    $option
                );
            } else {
                $this->options[$name] = array_merge($this->options[$name], $option);
            }
        } elseif (is_array($name)) {
            foreach ($name as $_name => $option) {
                $this->addOption($_name, $option);
            }
        } elseif (isset($this->options[$name])) {
            unset($this->options[$name]);
        }
    }

    private function getStorageKey()
    {
        return 'shop.migrate.'.preg_replace('@(^shopMigrate|Transport$)@', '', get_class($this));
    }

    public function getControls($errors = array())
    {
        $controls = array();
        $cached = null;

        $params = array();
        $params['title_wrapper'] = '<div class="name">%s</div>';
        $params['description_wrapper'] = '<br><span class="hint">%s</span>';
        $params['control_separator'] = '</div><br><div class="value no-shift">';

        $params['control_wrapper'] = '
<div class="field">
%s
<div class="value no-shift">%s%s</div>
</div>';
        foreach ($this->options as $field => $properties) {
            if (!empty($properties['control_type'])) {
                if (!empty($errors[$field])) {
                    if (!isset($properties['class'])) {
                        $properties['class'] = array();
                    }
                    $properties['class'] = array_merge((array)$properties['class'], array('error'));
                    if (!isset($properties['description'])) {
                        $properties['description'] = '</span><span class="errormsg">';
                    } else {
                        $properties['description'] .= '</span><span class="errormsg">';
                    }
                    $properties['description'] .= $errors[$field];
                } elseif (!empty($properties['valid']) && !isset($properties['control_wrapper'])) {
                    $properties['control_wrapper'] = '
<div class="field">
%s
<div class="value no-shift">%s&nbsp;<i class="icon16 yes"></i>%s</div>
</div>';
                }
                if (!empty($properties['cache'])) {
                    if (is_null($cached)) {
                        $storage = new waSessionStorage();
                        $cached = $storage->get($this->getStorageKey());
                        if (!is_array($cached)) {
                            $cached = array();
                        }
                    }
                    if (!isset($properties['value']) || ($properties['value'] === null)) {
                        $properties['value'] = ifset($cached[$field]);
                    }

                }
                $control_params = array_merge($params, $properties);
                if (($properties['control_type'] == waHtmlControl::HIDDEN) && (empty($control_params['description']))) {
                    $control_params['control_wrapper'] = '
<div class="field" style="display: none;">
%s
<div class="value no-shift">%s%s</div>
</div>';
                }
                $controls[$field] = waHtmlControl::getControl($properties['control_type'], $field, $control_params);
            }
        }

        return $controls;
    }

    private static function filter($feature, $filter)
    {
        $matched = true;
        foreach ($filter as $field => $value) {
            if (ifset($feature[$field]) != $value) {
                $matched = false;
                break;
            }
        }
        return $matched;
    }


    protected function getFeaturesOptions(&$suggests, $full = false, $multiple = true, $filter = array())
    {

        $key = md5(var_export(compact('full', 'multiple', 'filter'), true));
        $cache = new waRuntimeCache(__METHOD__.$key);
        $suggests_cache = new waRuntimeCache(__METHOD__.'_s'.$key);

        if (!$cache->isCached()) {
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
                                if (!$filter || self::filter($f, $filter)) {
                                    $features_options[] = array(
                                        'group' => & $translates['Add as new feature'],
                                        'value' => sprintf("f+:%s:%d:%d", $f['type'], $f['multiple'], $f['selectable']),
                                        'title' => empty($f['group']) ? $f['name'] : ($f['group'].': '.$f['name']),
                                    );
                                }
                            }
                        } else {
                            foreach ($f['subtype'] as $sf) {
                                if ($sf['available']) {
                                    $type = str_replace('*', $sf['type'], $f['type']);
                                    if ($multiple || (empty($f['multiple']) && !preg_match('@^(range|2d|3d)\.@', $type))) {
                                        if (!$filter || self::filter($f, $filter)) {
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
                if (empty($feature['parent_id'])
                    && ($multiple || (empty($feature['multiple']) && !preg_match('@^(range|2d|3d)\.@', $feature['type'])))
                ) {
                    if (!$filter || self::filter($feature, $filter)) {
                        $features_options[] = array(
                            'group'       => & $translates['Feature'],
                            'value'       => sprintf('f:%s', $feature['code']),
                            'title'       => $feature['name'],
                            'description' => $feature['code'],
                            'class'       => 'js-type-'.$feature['type'],
                        );
                    }
                }
                $suggests[sprintf('f:%s', $feature['code'])] = mb_strtolower($feature['name']);
            }
            $cache->set($features_options);
            $suggests_cache->set($suggests);
        } else {
            $features_options = $cache->get();
            $suggests = array_merge($suggests, $suggests_cache->get());
        }

        return $features_options;
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

    protected function getProductTypeOption($add_as_new = false)
    {
        $option = array(
            'control_type' => waHtmlControl::SELECT,
            'title'        => _wp('Product type'),
            'description'  => _wp('Selected product type will be applied to all imported products'),
            'options'      => array(),
        );
        if ($add_as_new) {
            $option['options'][] = array(
                'value' => -1,
                'title' => _wp('Add as new product type'),
            );
        }
        $type_model = new shopTypeModel();
        if ($types = $type_model->getAll()) {

            foreach ($types as $type) {
                $option['options'][] = array(
                    'value' => $type['id'],
                    'title' => $type['name'],
                );
            }
        } else {
            $type = array(
                'name' => _wp('Default product type'),
                'icon' => 'box',
            );
            $option = array(
                'control_type' => waHtmlControl::HIDDEN,
                'value'        => $type_model->insert($type),
            );

        }
        return $option;
    }

    public function settingCustomersControl($name, $params = array())
    {
        $control = '';
        if ($params['options']) {
            foreach ($params as $field => $param) {
                if (strpos($field, 'wrapper')) {
                    unset($params[$field]);
                }
            }
            if (!isset($params['value']) || !is_array($params['value'])) {
                $params['value'] = array();
            }

            waHtmlControl::addNamespace($params, $name);

            $params['control_wrapper'] = '<tr><td>%1$s %3$s</td><td>&rarr;</td><td>%2$s</td></tr>';
            $params['title_wrapper'] = '%s';
            $params['description_wrapper'] = '<br><span class="hint">%s</span>';

            $fields = $params['options'];
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
            foreach ($fields as $id => $field) {
                if (!is_array($field)) {
                    $field = array(
                        'title' => $field,
                    );
                }
                $title = ifset($field['title'], $id);
                $field_params = $params;
                $field_params['title'] = $title;
                $field_params['description'] = ifset($field['description']);

                if (isset($params['value'][$id])) {
                    $field_params['value'] = $params['value'][$id];
                } else {
                    $field_params['value'] = '';
                    $title = mb_strtolower($title, 'utf-8');
                    foreach ($params['options'] as $option) {
                        if (!empty($option['suggestion']) && ($option['suggestion'] == $title)) {
                            $field_params['value'] = $option['value'];
                            break;
                        }
                    }
                }

                $control .= waHtmlControl::getControl(waHtmlControl::SELECT, $id, $field_params);
            }
            $control .= "</tbody>";
            $control .= "</table>";
        } else {
            $control .= _wp('There no customer fields to import');
        }

        return $control;
    }


    /**
     * @param $name
     * @param array $params
     * @return string
     * @throws waException
     *
     * @todo fix suggestions
     */
    public function settingOptionMapControl($name, $params = array())
    {
        $suggests_features = array();
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

        $params['title_wrapper'] = '%s';
        $params['description_wrapper'] = '<br><span class="hint">%s</span>';

        $params['options'] = array();

        $targets = ifset($params['target'], 'feature,service,sku');
        if (!is_array($targets)) {
            $targets = preg_split('@,\s*@', $targets);
        }
        $target_params = $params;
        $target_params['description'] = null;
        $target_options = array(
            array(
                'value'       => 'feature',
                'title'       => _wp('Feature'),
                'description' => _wp('Content will be imported as a fixed descriptive product field'),
            ),
            array(
                'value'       => 'service',
                'title'       => _wp('Service'),
                'description' => _wp(
                    'Services feature allows customers to customize product when adding it to shopping cart (either select or unselect particular service with the product)'
                ),
            ),
            array(
                'value'       => 'sku',
                'title'       => _wp('SKU'),
                'description' => _wp(
                    'SKUs feature allows tracking inventory by multiple stocks. Multiple product SKUs (purchase options) will be created according to this custom option value set'
                ),
            ),
            array(
                'value' => '',
                'title' => _wp("Don't import"),
            ),
        );
        $target_params['value'] = ifset($params['value']['target']);
        $suggested = !empty($target_params['value']);

        if (in_array('feature', $targets)) {
            $feature_params = $params;
            $filter = ifset($params['feature_filter'], array());
            $feature_options = $this->getFeaturesOptions($suggests_features, true, true, $filter);
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
                $feature_params['description'] = $target_options[0]['description'];
                $target_options[0]['description'] = '';

            } else {
                $value = reset($feature_options);
                $feature_params['value'] = $value['value'];
                $feature_control = waHtmlControl::HIDDEN;
            }
        } else {
            $feature_params = null;
            $feature_control = false;
        }
        if (in_array('service', $targets)) {

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
        } else {
            $service_control = false;
            $service_params = null;
        }

        if (!$suggested) {
            $target_params['value'] = reset($targets);
        }

        $target_control = count($targets) > 1 ? waHtmlControl::RADIOGROUP : waHtmlControl::HIDDEN;
        if (in_array('feature', $targets)) {
            $target_params['options'] = array_slice($target_options, 0, 1);
            $control .= waHtmlControl::getControl($target_control, 'target', $target_params);
            $control .= waHtmlControl::getControl($feature_control, 'feature', $feature_params);

            $dimension_params = $params;
            $dimension_params['options'] = self::getFeatureDimensions();
            $dimension_params['description'] = null;
            $control .= waHtmlControl::getControl(waHtmlControl::SELECT, 'dimension', $dimension_params);
            if (count($targets) > 1) {
                $control .= $params['control_separator'];
            }
        }


        if (in_array('service', $targets)) {
            $target_params['options'] = array_slice($target_options, 1, 1);
            $control .= waHtmlControl::getControl($target_control, 'target', $target_params);
            $control .= waHtmlControl::getControl($service_control, 'service', $service_params);
            if (count($targets) > 1) {
                $control .= $params['control_separator'];
            }
        }

        if (in_array('sku', $targets)) {
            $sku_params = $params;
            $sku_params['options'] = array(
                'none'    => 'импортировать артикул как есть',
                'counter' => 'добавлять число',
                'name'    => 'обавлять значение хар-ки',
            );
            $sku_params['description'] = null;
            $target_params['options'] = array_slice($target_options, 2, 1);
            $control .= waHtmlControl::getControl($target_control, 'target', $target_params);
            $control .= waHtmlControl::getControl(waHtmlControl::SELECT, 'sku', $sku_params);
        }

        $feature_name = preg_replace("@([\\[\\]])@", '\\\\$1', waHtmlControl::getName($feature_params, 'feature'));
        $dimension_name = preg_replace("@([\\[\\]])@", '\\\\$1', waHtmlControl::getName($dimension_params, 'dimension'));

        $control .= <<<HTML
<script type="text/javascript">
if(typeof($) == 'function') {

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

    public function serialize()
    {
        $data = array();
        $data['map'] = $this->map;
        $data['offset'] = $this->offset;
        $data['options'] = array();
        $data['process_id'] = $this->process_id;
        foreach ($this->options as $name => & $option) {
            if (isset($option['value'])) {
                $data['options'][$name] = $option['value'];
            }
        }

        return serialize($data);
    }

    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        if (!empty($data['options'])) {
            foreach ($data['options'] as $name => $value) {
                $this->setOption($name, $value);
            }
        }
        if (!empty($data['map'])) {
            $this->map = $data['map'];
        }
        if (!empty($data['offset'])) {
            $this->offset = $data['offset'];
        }
        if (!empty($data['process_id'])) {
            $this->process_id = $data['process_id'];
        }

        return $this;
    }

    /**
     * @return string
     */
    protected function getContextDescription()
    {
        return '';
    }

    public static function enumerate()
    {
        $transports = array();
        $description = '';
        $dir = dirname(__FILE__);
        foreach (waFiles::listdir($dir) as $file) {
            if (($file != __FILE__) && preg_match('@^shopMigrate(\w+)Transport\.class\.php@', $file, $matches)) {


                $value = strtolower($matches[1]);
                if (preg_match('@^webasyst(\w*)$@', $value, $matches)) {
                    $group = empty($matches[1]) ? null : 'Webasyst';
                } else {
                    $group = '3rdParty';
                }

                if ($group) {
                    $tokens = token_get_all(file_get_contents($dir.'/'.$file));
                    $doc = array();
                    while ($token = array_shift($tokens)) {
                        if (is_array($token) && ($token[0] == T_DOC_COMMENT)) {
                            if (preg_match('~^/\*\*\n((.+\n){1,})\s*\*/$~', $token[1], $matches)) {
                                if ($raw = preg_split('~(^|\n)\s+\*\s+@~', $matches[1], -1, PREG_SPLIT_NO_EMPTY)) {
                                    foreach ($raw as $line) {
                                        $line = preg_split('@\s+@', $line, 2, PREG_SPLIT_NO_EMPTY);
                                        if (count($line) > 1) {
                                            $doc[$line[0]] = _wp(trim(end($line), "\n\r "));
                                        }
                                    }
                                }
                                break;
                            }
                        }
                    }
                    $title = $value;
                    $transports[$value] = $doc + compact('file', 'group', 'title', 'description', 'value');
                }
            }
        }
        uasort($transports, array(__CLASS__, 'sort'));

        return $transports;
    }

    private static function sort($a, $b)
    {
        $sort = strcmp($b['group'], $a['group']);
        if ($sort == 0) {
            $sort = strcasecmp($a['title'], $b['title']);
        }
        return $sort;
    }

    protected function deleteOrder($id)
    {
        static $model;
        static $customer_model;
        if (empty($model)) {
            $model = new shopOrderModel();
        }
        if ($_data = $this->orderModel()->getById($id)) {
            $tables = array(
                'shop_order_items',
                'shop_order_log',
                'shop_order_log_params',
                'shop_order_params',
            );
            foreach ($tables as $table) {
                $this->orderModel()->query(sprintf("DELETE FROM `%s` WHERE `order_id`=%d", $table, $id));
            }


            $query = sprintf("UPDATE `shop_customer` SET `last_order_id`=NULL WHERE `last_order_id`=%d", $id);
            $this->orderModel()->query($query);
            $this->orderModel()->deleteById($id);
            if (!empty($_data['contact_id'])) {
                $customer_data = array(
                    'number_of_orders' => $this->orderModel()->countByField('contact_id', $_data['contact_id']),
                    'last_order_id'    => $this->orderModel()->select('MAX(id)')->where('contact_id = :contact_id', $_data)->fetchField(),
                    'total_spent'      => $this->orderModel()->getTotalSalesByContact($_data['contact_id']),
                );

                if (empty($customer_model)) {
                    $customer_model = new shopCustomerModel();
                }
                $customer_model->updateById($_data['contact_id'], $customer_data);
            }

        }
    }

    private $order_model;

    /**
     * @return shopOrderModel
     */
    protected function orderModel()
    {
        if (empty($this->order_model)) {
            $this->order_model = new shopOrderModel();
        }
        return $this->order_model;
    }

    private $order_items_model;

    /**
     * @return shopOrderItemsModel
     */
    protected function orderItemsModel()
    {
        if (empty($this->order_items_model)) {
            $this->order_items_model = new shopOrderItemsModel();
        }
        return $this->order_items_model;
    }

    private $customer_model;

    /**
     * @return shopCustomerModel
     */
    protected function customerModel()
    {
        if (empty($this->customer_model)) {
            $this->customer_model = new shopCustomerModel();
        }
        return $this->customer_model;
    }

    protected function formatPaidDate($paid_time)
    {
        if (!is_numeric($paid_time)) {
            $paid_time = strtotime($paid_time);
        }
        return array(
            'paid_date'    => date('Y-m-d', $paid_time),
            'paid_year'    => date('Y', $paid_time),
            'paid_month'   => date('n', $paid_time),
            'paid_quarter' => floor((date('n', $paid_time) - 1) / 3) + 1,
        );
    }

    protected function formatDatetime($utc)
    {
        return date('Y-m-d H:i:s', empty($utc) ? null : strtotime($utc));
    }
}
