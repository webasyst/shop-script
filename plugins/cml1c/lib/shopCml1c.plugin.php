<?php

/**
 * Class shopCml1cPlugin
 * @property-read boolean[] $update_product_fields
 * @property-read boolean[] $update_category_fields
 */
class shopCml1cPlugin extends shopPlugin
{
    public function getControls($params = array())
    {
        waHtmlControl::registerControl('ContactFieldsControl', array($this, 'settingContactFieldsControl'));
        return parent::getControls($params);
    }

    public function getConfigParam($param = null)
    {
        static $config = null;
        if (is_null($config)) {
            $app_config = wa('shop');
            $files = array(
                $app_config->getAppPath('plugins/cml1c', 'shop').'/lib/config/config.php', // defaults
                $app_config->getConfigPath('shop/plugins/cml1c').'/config.php', // custom
            );
            $config = array();
            foreach ($files as $file_path) {
                if (file_exists($file_path)) {
                    $config = include($file_path);
                    if ($config && is_array($config)) {
                        foreach ($config as $name => $value) {
                            $config[$name] = $value;
                        }
                    }
                }
            }
        }
        return ($param === null) ? $config : (isset($config[$param]) ? $config[$param] : null);
    }

    public function getCallbackUrl($absolute = true, $disable_ssl = false)
    {
        $routing = wa()->getRouting();

        $route_params = array(
            'plugin' => $this->id,
            'hash'   => $this->uuid(),
        );

        $url = $routing->getUrl('shop/frontend/', $route_params, $absolute);

        if ($disable_ssl) {
            $url = preg_replace('@^https://@', 'http://', $url);
        }

        return $url;
    }

    public function path($file = '1c.xml')
    {
        $file = preg_replace('@^[\\/]+@', '', $file);
        $file = waLocale::transliterate($file);

        switch (wa()->getEnv()) {
            case 'frontend':
                $path = wa()->getDataPath('plugins/'.$this->id.'/'.$file, false, 'shop', true);
                break;
            case 'backend':
            default:
                $path = wa()->getTempPath('plugins/'.$this->id.'/'.$file, 'shop');
                break;
        }
        return $path;
    }

    /**
     * @todo complete validation
     * @param $xml
     * @param $path
     * @param string $version
     * @return bool
     */
    public function validate($xml, $path, $version = null)
    {
        $valid = false;
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new DOMDocument("1.0", "UTF-8");
        $dom->encoding = 'windows-1251';
        if ($version === null) {
            $version = $this->getSettings('version');
        }

        $schema = $this->getSchemaPath($version);

        if (!@$dom->schemaValidateSource($schema)) {
            $r = libxml_get_errors();
            libxml_clear_errors();
            $error = array(
                sprintf("XSD validation errors %s\n", $schema),
            );
            /**
             * @var LibXMLError[] $r
             */
            foreach ($r as $er) {
                $error[] = "Error #{$er->code}[{$er->level}] @ [{$er->line}:{$er->column}]: {$er->message}";
            }
            $this->error(implode("\t", $error));
        }

        if ($dom->loadXML($xml)) {

            $valid = $dom->schemaValidate($schema);
            if (!$valid) {
                $r = libxml_get_errors();
                libxml_clear_errors();
                /**
                 * @var LibXMLError[] $r
                 */
                $error = array(sprintf("XML validation errors %s\n", $path));
                foreach ($r as $er) {

                    $error[] = "Error #{$er->code}[{$er->level}] at [{$er->line}:{$er->column}]: {$er->message}";

                }
                $this->error(implode("\t", $error));
            } else {
                $this->error(sprintf('File %s is valid', $path));
            }
        } else {
            $r = libxml_get_errors();
            libxml_clear_errors();
            $error = array(sprintf('Error loading XML %s', $path));
            foreach ($r as $er) {
                $error[] = "Error #{$er->code}[{$er->level}] at [{$er->line}:{$er->column}]: {$er->message}";

            }
            $this->error(implode($error));
        }
        return $valid;
    }

    public function getSchemaPath($version = '2.05')
    {
        switch ($version) {
            case '2.08':
                $path = $this->path.'/xml/CML208.xsd';
                break;
            case '2.07':
                $path = $this->path.'/xml/CML207.xsd';
                break;
            case '2.06':
                $path = $this->path.'/xml/CML206.xsd';
                break;
            case '2.05':
            default:
                $path = $this->path.'/xml/CML205.xsd';
                break;
        }
        return $path;
    }

    private function error($message)
    {
        $path = wa()->getConfig()->getPath('log');
        waFiles::create($path.'/shop/plugins/'.$this->id.'.log');
        waLog::log($message, 'shop/plugins/'.$this->id.'.log');
    }

    public static function makeUuid($id = null)
    {
        if ($id) {
            $pr_bits = md5($id, true);
        } else {
            $fp = @file_exists('/dev/urandom') ? @fopen('/dev/urandom', 'rb') : false;
            if ($fp !== false) {
                $pr_bits = @fread($fp, 16);
                @fclose($fp);
            } else {
                // If /dev/urandom isn't available (eg: in non-unix systems), use mt_rand().
                $pr_bits = "";
                for ($cnt = 0; $cnt < 16; $cnt++) {
                    $pr_bits .= chr(mt_rand(0, 255));
                }
            }
        }
        $time_low = bin2hex(substr($pr_bits, 0, 4));
        $time_mid = bin2hex(substr($pr_bits, 4, 2));
        $time_hi_and_version = bin2hex(substr($pr_bits, 6, 2));
        $clock_seq_hi_and_reserved = bin2hex(substr($pr_bits, 8, 2));
        $node = bin2hex(substr($pr_bits, 10, 6));

        /**
         * Set the four most significant bits (bits 12 through 15) of the
         * time_hi_and_version field to the 4-bit version number from
         * Section 4.1.3.
         * @see http://tools.ietf.org/html/rfc4122#section-4.1.3
         */
        $time_hi_and_version = hexdec($time_hi_and_version);
        $time_hi_and_version = $time_hi_and_version >> 4;
        $time_hi_and_version = $time_hi_and_version | 0x4000;

        /**
         * Set the two most significant bits (bits 6 and 7) of the
         * clock_seq_hi_and_reserved to zero and one, respectively.
         */
        $clock_seq_hi_and_reserved = hexdec($clock_seq_hi_and_reserved);
        $clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved >> 2;
        $clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved | 0x8000;

        $uuid = sprintf('%08s-%04s-%04x-%04x-%012s', $time_low, $time_mid, $time_hi_and_version, $clock_seq_hi_and_reserved, $node);
        return $uuid;
    }

    public function uuid($enabled = null)
    {
        $refresh = false;
        $uuid = null;

        if ($enabled !== null) {
            if ($enabled && !($this->getSettings('enabled'))) {
                $refresh = true;
            } elseif (!$enabled && $this->getSettings('enabled')) {
                $refresh = true;
            }
        }

        if ($refresh) {
            if ($enabled) {
                $uuid = self::makeUuid();
            }
            $settings = array(
                'uuid'    => $uuid,
                'enabled' => $enabled,
            );
            $settings += $this->getSettings();
            $this->saveSettings($settings);
        } elseif ($this->getSettings('enabled')) {
            $uuid = $this->getSettings('uuid');
        }
        return $uuid;
    }

    public function exportTime($update = false)
    {
        $datetime = $this->getSettings('export_datetime');
        if (!is_array($datetime)) {
            $datetime = array();
        }

        $env = wa()->getEnv();
        if ($update) {
            $datetime[$env] = time();

            $settings = $this->getSettings();
            $settings['export_datetime'] = $datetime;
            $this->saveSettings($settings);
        }
        return ifset($datetime[$env]);
    }

    /**
     * UI hook for backend_products
     * @param $params
     * @return array
     */
    public function backendProducts($params)
    {
        $result = array();
        if (empty($params)) {
            $result['sidebar_section'] = <<<HTML
    <div class="block">
        <span class="count"><a href="?action=importexport#/cml1c/tab/manual/"><i class="icon16 upload"></i></a></span>
         <h5 class="heading">CommerceML выборка</h5>
        <ul class="menu-v with-icons">

            <li id="s-cml1c">
                <span class="count"></span>
                <a href="#/products/hash=cml1c">
                      <i class="icon16 folders"></i>Синхронизированные
                </a>
            </li>

            <li id="s-cml1c-new">
                <span class="count"></span>
                <a href="#/products/hash=cml1c/new">
                      <i class="icon16 new"></i>Импортированные за 24 часа
                </a>
            </li>

            <li id="s-cml1c-recent">
                <span class="count"></span>
                <a href="#/products/hash=cml1c/recent">
                      <i class="icon16 sync"></i>Обновленные за 24 часа
                </a>
            </li>
            <li id="s-cml1c-no">
                <span class="count"></span>
                <a href="#/products/hash=cml1c/no">
                    <i class="icon16 no-bw"></i>Без идентификатора
                </a>
            </li>

        </ul>
    </div>
HTML;
        }
        return $result;
    }

    /**
     * search hook products_collection
     * @param $params
     * @return bool|null
     */
    public function productsCollection($params)
    {
        $collection = $params['collection'];
        /**
         * @var shopProductsCollection $collection
         */

        $hash = $collection->getHash();
        if (($hash[0] !== $this->id) ||
            (wa()->getEnv() != 'backend')
        ) {
            return null;
        }
        unset($params['collection']);
        switch (ifset($hash[1])) {
            case 'no':
                $collection->addWhere('`id_1c` IS NULL');
                break;
            case 'recent':
                $collection->addWhere('((`id_1c` IS NOT NULL) AND (`edit_datetime` > "'.date('Y-m-d H:i:s', time() - 86400).'"))');
                break;
            case 'new':
                $collection->addWhere('((`id_1c` IS NOT NULL) AND (`edit_datetime` IS NULL) AND (`create_datetime` > "'.date('Y-m-d H:i:s', time() - 86400).'"))');
                break;
            default:
                $collection->addWhere('`id_1c` IS NOT NULL');
                break;
        }

        if (!empty($params['auto_title'])) {
            switch (ifset($hash[1])) {
                case 'no':
                    $collection->addTitle('Товары без идентификатора CML');
                    break;
                case 'recent':
                    break;
                default:
                    $collection->addTitle('Товары с идентификатором CML');
                    break;
            }

        }

        return true;
    }

    public static function controlCustomerFields()
    {
        static $options = array();
        if (empty($options)) {
            $options[] = array(
                'value' => '',
                'title' => '—',
            );
            if (false) {
                $form = shopHelper::getCustomerForm();
                foreach ($form->fields() as $field) {
                    if ($field instanceof waContactCompositeField) {
                        foreach ($field->getFields() as $sub_field) {
                            if (!($sub_field instanceof waContactHiddenField)) {
                                /**
                                 * @var waContactField $sub_field
                                 */
                                $options[] = array(
                                    'group' => $field->getName(),
                                    'value' => $field->getId().':'.$sub_field->getId(),
                                    'title' => $sub_field->getName(),
                                );
                            }
                        }
                    } elseif (!($field instanceof waContactHiddenField)) {
                        $options[] = array(
                            'value' => $field->getId(),
                            'title' => $field->getName(),
                        );
                    }
                }
            } else {
                foreach (waContactFields::getAll() as $contact_field) {
                    if (!$contact_field instanceof waContactHiddenField) {
                        if ($contact_field instanceof waContactCompositeField) {
                            /**
                             * @var waContactCompositeField $contact_field
                             */
                            foreach ($contact_field->getFields() as $contact_sub_field) {
                                if (!$contact_sub_field instanceof waContactHiddenField) {
                                    /**
                                     * @var waContactField $contact_sub_field
                                     */
                                    $options[] = array(
                                        'group' => $contact_field->getName(),
                                        'value' => $contact_field->getId().':'.$contact_sub_field->getId(),
                                        'title' => $contact_sub_field->getName(),
                                    );
                                }
                            }
                        } else {
                            /**
                             * @var waContactField $contact_field
                             */
                            $options[] = array(
                                'group' => 'Общие поля',
                                'value' => $contact_field->getId(),
                                'title' => $contact_field->getName(),
                            );
                        }
                    }
                }
            }
        }
        return $options;
    }

    public function makeEntryUUID($id, $type = 'product', $parent_id = null)
    {
        /**
         * @var waModel[] $models
         */
        static $models = array();
        switch ($type) {
            case 'product':
                if (!isset($models[$type])) {
                    $models[$type] = new shopProductModel();
                }
                $field = 'id_1c';
                break;
            case 'sku':
                if (!isset($models[$type])) {
                    $models[$type] = new shopProductSkusModel();
                }
                $field = 'id_1c';
                break;
            case 'service':
                if (!isset($models[$type])) {
                    $models[$type] = new shopServiceModel();
                }
                $field = 'cml1c_id';
                break;
            case 'service_variant':
                if (!isset($models[$type])) {
                    $models[$type] = new shopServiceVariantsModel();
                }
                $field = 'cml1c_id';
                break;
            default:
                throw new waException('Invalid entry type');
                break;
        }

        do {
            $uuid = shopCml1cPlugin::makeUuid();
        } while ($models[$type]->getByField($field, $uuid));
        $models[$type]->updateById($id, array($field => $uuid));

        return $uuid;
    }

    /**
     * @deprecated use shopCml1cPlugin::makeEntryUUID
     * @param $id
     * @return string
     */
    public function makeProductUUID($id)
    {
        return $this->makeEntryUUID($id, 'product');
    }

    /**
     * @deprecated use shopCml1cPlugin::makeEntryUUID
     * @param $id
     * @return string
     */
    public function makeSkuUUID($id)
    {
        return $this->makeEntryUUID($id, 'sku');
    }

    public function settingContactFieldsControl($name, $params = array())
    {
        $control = '';
        $control .= <<<HTML
<table class="zebra">
<thead>
<tr>
<th>Поле контакта в Shop-Script</th>
<th>Наименование реквизита в CommerceML</th>
</tr></thead>
<tbody>
HTML;
        $params = array_merge(
            $params,
            array(
                'description'     => null,
                'title_wrapper'   => false,
                'title'           => null,
                'control_wrapper' => "%s\n%s\n%s\n",
            )
        );
        $options = $params['options'];
        waHtmlControl::addNamespace($params, $name);
        unset($params['options']);
        $group = null;
        foreach ($options as $option) {

            $id = $option['value'];
            if (!empty($id)) {
                $line_params = $params;
                $line_params['value'] = ifset($params['value'][$id], array());
                waHtmlControl::addNamespace($line_params, $id);

                #checkbox
                $enabled_params = $line_params;
                $enabled_params['value'] = ifset($line_params['value']['enabled']);
                $enabled_params['label'] = $option['title'];
                $enabled_params['description'] = $id;
                $enabled = waHtmlControl::getControl(waHtmlControl::CHECKBOX, 'enabled', $enabled_params);

                #name
                $tag_params = $line_params;
                $tag_params['value'] = ifset($line_params['value']['tag'], $option['title']);
                $tag_params['placeholder'] = $option['title'];
                $tag = waHtmlControl::getControl(waHtmlControl::INPUT, 'tag', $tag_params);

                if (ifset($option['group']) != $group) {
                    $group = $option['group'];
                    $control .= <<<HTML
<tr>
    <td colspan="2"><h3>{$group}</h3></td>
</tr>
HTML;
                }

                $control .= <<<HTML
<tr>
    <td>{$enabled}</td>
    <td>{$tag}</td>
</tr>
HTML;

            }
        }

        $control .= <<<HTML
</tbody>
</table>
HTML;
        return $control;

    }

    public static function controlStock()
    {
        static $options = null;
        if ($options === null) {
            $options = array();
            $options[0] = 'Импорт в общие остатки товаров Shop-Script';
            $model = new shopStockModel();
            foreach ($model->getAll($model->getTableId()) as $id => $stock) {
                $options[$id] = $stock['name'];
            }
        }
        return $options;
    }

    public static function controlCurrencies()
    {
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        $options = array();
        foreach ($z = $config->getCurrencies() as $code => $data) {
            $options[] = array(
                'value'       => $code,
                'title'       => sprintf('%s %s', $data['code'], $data['title']),
                'description' => $data['sign'],
            );
        }
        return $options;
    }

    public static function controlStatus()
    {
        $workflow = new shopWorkflow();
        $options = array();
        foreach ($workflow->getAllStates() as $id => $state) {
            /**
             * @var waWorkflowEntity $state
             */
            $options[] = array(
                'value'       => $id,
                'title'       => $state->getName(),
                'description' => $id,
            );
        }
        return $options;
    }

    public static function controlType()
    {
        $m = new shopTypeModel();
        $options = array();
        foreach ($m->getTypes() as $id => $type) {
            $options[] = array(
                'value' => $id,
                'title' => $type['name'],
            );
        }
        return $options;
    }

    public static function controlProductFields()
    {
        return array(
            array(
                'value'       => 'name',
                'title'       => _w('Product name'),
                'description' => '',
            ),
            array(
                'value'       => 'sku',
                'title'       => _w('SKU'),
                'description' => '',
            ),
            array(
                'value'       => 'sku_name',
                'title'       => _w('SKU name'),
                'description' => '',
            ),
            array(
                'value'       => 'description',
                'title'       => _w('Description'),
                'description' => '',
            ),
            array(
                'value'       => 'summary',
                'title'       => _w('Summary'),
                'description' => '',
            ),
            array(
                'value'       => 'tax_id',
                'title'       => _w('Tax type'),
                'description' => '',
            ),
            array(
                'value'       => 'features',
                'title'       => _w('Features'),
                'description' => '',
            ),
            array(
                'value'       => 'weight',
                'title'       => _w('Weight'),
                'description' => '',
            ),
            array(
                'value'       => 'params',
                'title'       => _w('Custom parameters'),
                'description' => '',
            ),
        );
    }

    public static function controlWeightUnits()
    {
        return shopDimension::getUnits('weight');
    }

    public static function isGuid($string)
    {
        //14ed8b20-55bd-11d9-848a-00112f43529a
        return preg_match('@^[0-9a-f]{8}\-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$@', $string);
    }

    public function productHandler($product)
    {
        $result = array();
        if (!empty($product['id_1c'])) {
            $result['info_section'] = sprintf('<span class="hint" >Идентификатор «1С»: %s</span>', $product['id_1c']);
        }
        return $result;
    }

    /**
     * @param $event_params
     * @param shopProduct $event_params ['product']
     * @param array $event_params ['sku']
     * @return string|null
     */
    public function skuHandler($event_params)
    {
        if (!empty($event_params['sku']['id_1c'])) {
            $template = <<<HTML
<div class="field">
<div class="name">Идентификатор «1С»</div>
<div class="value">%s#%s</div>
</div>
HTML;
            return sprintf($template, $event_params['product']['id_1c'], $event_params['sku']['id_1c']);
        }
        return null;
    }

    /**
     * @param $params
     * @return array
     */
    public function prodHandler($params)
    {
        $content_id = ifset($params, 'content_id', '');
        $id_1c = ifset($params, 'product', 'id_1c', '');
        $html = <<<HTML
<div class="wa-field">
    <div class="name"><div class="wa-label">
        Идентификатор «1С»
    </div></div>
    <div class="value">{$id_1c}</div>
</div>
HTML;

        return [
            'form_top' => ($content_id === 'general' ? $html : '')
        ];
    }

    /**
     * @param $params
     * @return array
     */
    public function prodSkuFieldsHandler($params)
    {
        /** Цены и характеристики */
        $product = $params['product'];
        $plugin_fields = [[
            'type'          => 'help',
            'id'            => 'id_1c',
            'name'          => 'Идентификатор «1С»',
            'placement'     => 'bottom',
            'tooltip'       => '',
            'css_class'     => '',
            'default_value' => ''
        ]];

        foreach ($product['skus'] as $sku_id => $sku) {
            foreach ($plugin_fields as $i => $field) {
                $id_1c_sku = ifempty($sku, 'id_1c', '');
                $id_1c_prod = ifempty($product, 'id_1c', '');
                if (!empty($id_1c_sku) && !empty($id_1c_prod)) {
                    $plugin_fields[$i]['sku_values'][$sku_id] = "$id_1c_prod#$id_1c_sku";
                }
            }
        }

        return $plugin_fields;
    }
}
