<?php

/**
 * Class shopCml1cPlugin
 * @property-read boolean[] $update_product_fields
 * @property-read boolean[] $update_category_fields
 */
class shopCml1cPlugin extends shopPlugin
{
    public function getCallbackUrl($absolute = true)
    {
        $routing = wa()->getRouting();

        $route_params = array(
            'plugin' => $this->id,
            'hash'   => $this->uuid(), //š
        );
        return preg_replace('@^https://@', 'http://', $routing->getUrl('shop/frontend/', $route_params, $absolute));
    }

    public function path($file = '1c.xml')
    {
        $file = preg_replace('@^[\\/]+@', '', $file);
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
                sprintf("XSD validation errors %s\n", $schema)
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

    public static function makeUuid()
    {

        $fp = @fopen('/dev/urandom', 'rb');
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
         <h5 class="heading">CML</h5>
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
                      <i class="icon16 folders"></i>Импортированные сегодня
                </a>
            </li>

            <li id="s-cml1c-recent">
                <span class="count"></span>
                <a href="#/products/hash=cml1c/recent">
                      <i class="icon16 folders"></i>Обновленные сегодня
                </a>
            </li>
            <li id="s-cml1c-no">
                <span class="count"></span>
                <a href="#/products/hash=cml1c/no">
                    <i class="icon16 folders"></i>Без идентификатора
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
        if (
            ($hash[0] !== $this->id) ||
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
                    $collection->addTitle('Продукты без идентификатора CML');
                    break;
                case 'recent':
                    break;
                default:
                    $collection->addTitle('Продукты с идентификатором CML');
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
            foreach (waContactFields::getAll() as $contact_field) {
                if ($contact_field instanceof waContactCompositeField) {
                    /**
                     * @var waContactCompositeField $contact_field
                     */
                    foreach ($contact_field->getFields() as $contact_sub_field) {
                        /**
                         * @var waContactField $contact_sub_field
                         */
                        $options[] = array(
                            'group' => $contact_field->getName(),
                            'value' => $contact_field->getId().':'.$contact_sub_field->getId(),
                            'title' => $contact_sub_field->getName(),
                        );
                    }
                } else {
                    /**
                     * @var waContactField $contact_field
                     */
                    $options[] = array(
                        'value' => $contact_field->getId(),
                        'title' => $contact_field->getName(),
                    );
                }
            }
        }
        return $options;
    }

    public function makeProductUUID($id)
    {
        static $product_model;
        if (!$product_model) {
            $product_model = new shopProductModel();
        }
        do {
            $uuid = shopCml1cPlugin::makeUuid();
        } while ($product_model->getByField('id_1c', $uuid));
        $product_model->updateById($id, array('id_1c' => $uuid));
        return $uuid;
    }

    public function makeSkuUUID($id)
    {
        static $product_sku_model;
        if (!$product_sku_model) {
            $product_sku_model = new shopProductSkusModel();
        }
        do {
            $uuid = shopCml1cPlugin::makeUuid();
        } while ($product_sku_model->getByField('id_1c', $uuid));
        $product_sku_model->updateById($id, array('id_1c' => $uuid));
        return $uuid;
    }

    public static function controlStock()
    {
        static $options = null;
        if ($options === null) {
            $options = array();
            $options[0] = 'Без учета складов';
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

    public static function isGuid($string)
    {
        //14ed8b20-55bd-11d9-848a-00112f43529a
        return preg_match('@^[0-9a-f]{8}\-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$@', $string);
    }

}
