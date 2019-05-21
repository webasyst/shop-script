<?php

class shopDemoDataImporter
{
    protected static $source_list;

    protected $config;

    protected $cache = array();

    public function __construct($source)
    {
        if (wa_is_int($source) && $source > 0) {
            $sources = self::getSourceList();
            if (isset($sources[$source])) {
                $this->config = $sources[$source];
            }
        } elseif (is_array($source)) {
            $this->config = $source;
        }

        if (!is_array($this->config) && !isset($this->config['url'])) {
            $this->config = null;
        }
    }

    public function import()
    {
        if (!$this->config) {
            return false;
        }

        $zip_path = $this->download();

        if (!$zip_path) {
            return false;
        }

        $extract_path = $this->unpack($zip_path);

        // IMPORT TABLES
        $tables_dir = $extract_path . 'tables/';
        if (file_exists($tables_dir)) {
            $this->importTablesData($tables_dir);
        } else {
            self::printLog("Couldn't find tables dir after unpacking source zip of source data");
        }


        $this->importDataFiles($extract_path);

        $tmp_config_files_dir = $extract_path . 'wa-config/';

        // IMPORT wa-config/routing.php
        $import_routing_result = $this->importRoutingSettings($tmp_config_files_dir);

        // IMPORT wa-config/apps/shop
        $this->importShopConfigs($tmp_config_files_dir, $import_routing_result);

        if (wa()->appExists('installer')) {
            try {
                wa('installer');
                installerHelper::flushCache();
            } catch (Exception $ex) {

            }
        }

        return true;
    }

    protected function download()
    {
        if (!$this->config) {
            return false;
        }

        $zip_url = $this->config['url'];

        $temp_path = wa()->getTempPath("demo/source/" . md5(uniqid(rand(), true)).'.zip', 'shop');

        $this->markToDelete($temp_path);

        $wfd = @fopen($temp_path, 'w');
        if (!$wfd) {
            return false;
        }

        $rwd = @fopen($zip_url, 'r');
        if (!$rwd) {
            return false;
        }

        if (!stream_copy_to_stream($rwd, $wfd)) {
            return false;
        }

        @fclose($wfd);
        @fclose($rwd);

        return $temp_path;
    }

    protected function unpack($temp_path)
    {
        $zip = new ZipArchive();
        if ($zip->open($temp_path) !== true) {
            return false;
        }

        $extract_path = wa()->getTempPath("demo/source/" . md5(uniqid(rand(), true)) . '/', 'shop');

        $this->markToDelete($extract_path);

        $zip->extractTo($extract_path);
        $zip->close();

        return $extract_path;
    }

    /**
     * @param $tables_dir
     * @throws waDbException
     */
    protected function importTablesData($tables_dir)
    {
        foreach (waFiles::listdir($tables_dir) as $file) {

            $filepath = "{$tables_dir}{$file}";

            if (!file_exists($filepath)) {
                continue;
            }

            $table_name = str_replace('.php', '', $file);
            $data = include($filepath);

            try {
                $table = $this->newShopDemoDataTable($table_name);
                foreach ($data as $item) {
                    $table->insert($item, 1);
                }
            } catch (waDbException $e) {
                // ignore or maybe log
                throw $e;
            }

        }
    }

    protected function newShopDemoDataTable($table_name)
    {
        return new shopDemoDataTable($table_name);
    }

    protected function importDataFiles($extract_path)
    {
        $this->importProtectedData($extract_path);
        $this->importPublicData($extract_path);
        $this->importSiteThemes($extract_path);
    }

    protected function importPublicData($extract_path)
    {
        // IMPORT public WA-DATA
        $tmp_public_files_dir = $extract_path . 'wa-data/public/shop/';
        $app_public_files_dir = wa()->getDataPath('', true, 'shop');
        try {
            waFiles::copy($tmp_public_files_dir, $app_public_files_dir);
        } catch (Exception $e) {
            self::printLog("Couldn't find 'wa-data/public/shop/' dir after unpacking source zip of source data");
            self::printLog($e->getMessage());
        }
    }

    protected function importProtectedData($extract_path)
    {
        // IMPORT protected WA-DATA
        $tmp_public_files_dir = $extract_path . 'wa-data/protected/shop/';
        $app_public_files_dir = wa()->getDataPath('', false, 'shop');
        try {
            waFiles::copy($tmp_public_files_dir, $app_public_files_dir);
        } catch (Exception $e) {
            self::printLog("Couldn't find 'wa-data/protected/shop/' dir after unpacking source zip of source data");
            self::printLog($e->getMessage());
        }
    }

    protected function importSiteThemes($extract_path)
    {
        // IMPORT themes of site
        $tmp_site_themes_dir = $extract_path . 'wa-data/public/site/themes';
        $current_site_themes_dir = wa()->getDataPath('themes', true, 'site');
        try {
            waFiles::copy($tmp_site_themes_dir, $current_site_themes_dir);
        } catch (Exception $e) {
            self::printLog("Couldn't find 'wa-data/public/site/themes' dir after unpacking source zip of source data");
            self::printLog($e->getMessage());
        }
    }

    protected function importRoutingSettings($config_files_dir)
    {
        // distribute all settings of shop routing of exporter throughout all shop-settlements of importer

        $exported_routing_config = array();
        $config_routing_file = $config_files_dir . 'routing.php';

        if (file_exists($config_routing_file)) {
            $exported_routing_config = include($config_routing_file);
        }

        // get first shop settlement
        $exporter_shop_settlement_config = null;
        foreach ($exported_routing_config as $domain => $domain_routing_config) {
            // may be 'mirror' - 'mirror' is scalar value, not array
            if (is_array($domain_routing_config)) {
                foreach ($domain_routing_config as $settlement_config) {
                    if (is_array($settlement_config) && isset($settlement_config['app']) && $settlement_config['app'] == 'shop') {
                        $exporter_shop_settlement_config = $settlement_config;
                        break;
                    }
                }
            }
        }

        if (!$exporter_shop_settlement_config) {
            return array();
        }

        $current_routing_config = array();
        $current_config_files_dir = wa()->getConfigPath();
        $current_config_routing_file = $current_config_files_dir . '/routing.php';
        if (file_exists($current_config_routing_file)) {
            $current_routing_config = include($current_config_routing_file);
        }

        $changed = false;

        $checkout_storefront_ids = array();

        foreach ($current_routing_config as $current_domain => &$current_domain_routing_config) {
            // may be 'mirror' - 'mirror' is scalar value, not array
            if (is_array($current_domain_routing_config)) {
                foreach ($current_domain_routing_config as &$current_settlement_config) {
                    if (is_array($current_settlement_config) && isset($current_settlement_config['app']) && $current_settlement_config['app'] == 'shop') {

                        // url will not be changed
                        $url = $current_settlement_config['url'];

                        $current_settlement_config = $exporter_shop_settlement_config;
                        $current_settlement_config['url'] = $url;

                        // checkout 1 if there is not suck key - (shop version <= 7)
                        if (!isset($new_current_settlement_config['checkout_version'])) {
                            $new_current_settlement_config['checkout_version'] = 1;
                        }

                        $current_settlement_config['checkout_storefront_id'] = md5(mt_rand() . 'n34nrt021n123ndf97adrf21' . time() . mt_rand());
                        $checkout_storefront_ids[] = $current_settlement_config['checkout_storefront_id'];

                        $changed = true;
                    }
                }
                unset($current_settlement_config);
            }
        }
        unset($current_domain_routing_config);

        if (!$changed) {
            return array();
        }

        waUtils::varExportToFile($current_routing_config, $current_config_routing_file);

        return array(
            // will need for update checkout2 config
            'exporter_checkout_storefront_id' => ifset($exporter_shop_settlement_config['checkout_storefront_id']),
            'current_checkout_storefront_ids' => $checkout_storefront_ids
        );

    }

    protected function importShopConfigs($config_files_dir, $options = array())
    {
        $options = is_array($options) ? $options : array();

        $tmp_configs_path = $config_files_dir . 'apps/shop/';
        $current_configs_path = wa()->getConfigPath() . '/apps/shop/';


        $tmp_checkout2_config_path = $tmp_configs_path . 'checkout2.php';
        $current_checkout2_config_path = $current_configs_path . 'checkout2.php';

        if (file_exists($tmp_checkout2_config_path)) {
            try {
                waFiles::copy($tmp_checkout2_config_path, $current_checkout2_config_path);

                $exporter_checkout_storefront_id = ifset($options['exporter_checkout_storefront_id']);

                // $current_checkout2_config is already updated
                $current_checkout2_config = include($current_checkout2_config_path);

                // 1) There is one shop settlement that we choose and distribute all its settings to all current shop settlement
                //    Now all current shop settlement settings are the same (clones)
                // 2) For each shop settlements had been generated new UNIQUE checkout_storefront_id
                // 3) Now we have imported checkout2 config, this config is array that indexed by checkout_storefront_id that is old and more or less constant,
                //    so:
                //    - re-index array by new checkout_storefront_id that we keep
                //    - and also unset redundant items - cause we import only ONE shop settlement
                //

                $changed = false;

                // unset redundant items - cause we import only ONE shop settlement:
                foreach ($current_checkout2_config as $checkout_storefront_id => $_) {
                    if ($checkout_storefront_id !== $exporter_checkout_storefront_id) {
                        $changed = true;
                        unset($current_checkout2_config[$checkout_storefront_id]);
                    }
                }

                // now we will re-index array by checkout_storefront_id that we keep (not rewritten)

                $exporter_checkout_storefront_config = ifset($current_checkout2_config, $exporter_checkout_storefront_id, null);
                if ($exporter_checkout_storefront_config) {     // just in case
                    $current_checkout_storefront_ids = ifset($options['current_checkout_storefront_ids']);
                    if (is_array($current_checkout_storefront_ids) && count($current_checkout_storefront_ids) > 0) {
                        foreach ($current_checkout_storefront_ids as $checkout_storefront_id) {
                            $current_checkout2_config[$checkout_storefront_id] = $exporter_checkout_storefront_config;
                        }
                        unset($current_checkout2_config[$exporter_checkout_storefront_id]);
                        $changed = true;
                    }
                }

                if ($changed) {
                    if (empty($current_checkout2_config)) {
                        try {
                            waFiles::delete($current_checkout2_config_path);
                        } catch (Exception $e) {

                        }
                    } else {
                        waUtils::varExportToFile($current_checkout2_config, $current_checkout2_config_path);
                    }
                }

            } catch (Exception $e) {

            }
        }

        // Other configs - just copy
        try {
            waFiles::copy($tmp_configs_path, $current_configs_path, array(
                '/checkout2\.php/'
            ));
        } catch (Exception $e) {

        }

    }

    protected function startWith($string, $substr)
    {
        $len = strlen($substr);
        return substr($string, 0, $len) === $substr;
    }

    protected function markToDelete($path)
    {
        $this->cache['delete_files'] = ifset($this->cache['delete_files']);
        $this->cache['delete_files'] = is_array($this->cache['delete_files']) ? $this->cache['delete_files'] : array();
        $this->cache['delete_files'][] = $path;
    }

    protected function clean()
    {
        $this->cache['delete_files'] = ifset($this->cache['delete_files']);
        $this->cache['delete_files'] = is_array($this->cache['delete_files']) ? $this->cache['delete_files'] : array();
        foreach ($this->cache['delete_files'] as $filepath) {
            try {
                waFiles::delete($filepath);
            } catch (waException $e) {}
        }
    }

    public function __destruct()
    {
        $this->clean();
    }

    public static function getSourceList()
    {
        if (self::$source_list === null) {
            self::$source_list = self::obtainSourceList();
        }
        return self::$source_list;
    }

    protected static function obtainSourceList()
    {
        $filepath = wa('shop')->getAppPath('lib/config/data/welcome/demo_sources.php', 'shop');
        if (file_exists($filepath)) {
            return include $filepath;
        } else {
            return array();
        }
    }

    protected static function printLog($message)
    {
        if (!is_scalar($message)) {
            $message = var_export($message, true);
        }
        waLog::log($message, 'shop/demo_data_importer.log');
    }
}
