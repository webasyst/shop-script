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

        $table_data = array();
        $tables_dir = $extract_path . 'tables/';

        // IMPORT TABLES (straightforwardly, exclude 'site_page', 'site_page_params')
        if (file_exists($tables_dir)) {
            $table_data = $this->getTablesData($tables_dir);

            $import_table_data = $table_data;
            unset($import_table_data['site_page'], $import_table_data['site_page_params']);
            unset($import_table_data['shop_page'], $import_table_data['shop_page_params']);

            $this->importTablesData($import_table_data);
        } else {
            static::printLog("Couldn't find tables dir after unpacking source zip of source data");
        }

        //
        $this->importDataFiles($extract_path);

        $tmp_config_files_dir = $extract_path . 'wa-config/';

        // Backup wa-config files to be affected by this import
        $this->backupWaConfig();

        // IMPORT wa-config/routing.php for SHOP app
        $import_options = $this->importShopRoutingSettings($tmp_config_files_dir);

        // IMPORT wa-config/apps/shop
        $this->importShopConfigs($tmp_config_files_dir, $import_options);

        // IMPORT wa-config/routing.php for SITE and other apps
        $import_options += $this->importOtherRoutingSettings($tmp_config_files_dir);

        // IMPORT site "The array defining core website navigation menu" setting
        $this->importSiteNavigationMenuSettings($tmp_config_files_dir, $import_options);

        // Import site_page and site_page_params tables
        $site_pages_table_data = array_intersect_key($table_data, ['site_page' => 1, 'site_page_params' => 1]);
        $shop_pages_table_data = array_intersect_key($table_data, ['shop_page' => 1, 'shop_page_params' => 1]);

        if ($shop_pages_table_data || $site_pages_table_data) {
            if ($shop_pages_table_data) {
                list(
                    $import_options['current_domain'],
                    $import_options['current_shop_url']
                ) = $this->getCurrentDomainShopSettlementUrl();
                $this->importShopPagesTableData($shop_pages_table_data, $import_options);
            }
            if ($site_pages_table_data && wa()->appExists('site')) {
                $import_options['current_domain_id'] = $this->getCurrentDomainId();
                $this->importSitePagesTableData($site_pages_table_data, $import_options);
            }
        }

        if (wa()->appExists('installer')) {
            try {
                wa('installer');
                installerHelper::flushCache();
            } catch (Exception $ex) {

            }
        }

        if (wa()->appExists('site')) {
            try {
                wa('site');
                // will fill site_domain table
                siteHelper::getDomains();
            } catch (Exception $e) {

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
     * Read tables data from dir
     * @param $tables_dir
     * @return array
     */
    protected function getTablesData($tables_dir)
    {
        $tables_data = array();
        foreach (waFiles::listdir($tables_dir) as $file) {
            $filepath = "{$tables_dir}{$file}";
            if (!file_exists($filepath)) {
                continue;
            }
            $table_name = str_replace('.php', '', $file);
            $data = include($filepath);
            $tables_data[$table_name] = $data;
        }

        return $tables_data;
    }

    /**
     * Straightforward import data
     * @param array $tables_data
     * @throws waDbException
     */
    protected function importTablesData($tables_data)
    {
        foreach ($tables_data as $table_name => $data) {
            try {
                $table = $this->newShopDemoDataTable($table_name);
                foreach ($data as $item) {
                    $table->insert($item, 1);
                }
            } catch (waDbException $e) {
                // ignore or maybe log
                static::printLog($e);
                throw $e;
            }
        }
    }

    protected function importShopPagesTableData($tables_data, $options = array())
    {
        if (empty($tables_data['shop_page'])) {
            return;
        }

        $tables_data = array_intersect_key($tables_data, ['shop_page' => 1, 'shop_page_params' => 1]);

        // Prepare pages by replacing domain id and route
        $tables_data['shop_page'] = $this->prepareShopPageData($tables_data['shop_page'], $options);

        $this->importTablesData($tables_data);
    }

    protected function prepareShopPageData($data, $options = array())
    {
        foreach ($data as &$item) {
            if (isset($options['current_domain'])) {
                $item['domain'] = $options['current_domain'];
            }
            if (isset($options['current_shop_url'])) {
                $item['route'] = $options['current_shop_url'];
            }

        }
        unset($item);

        return $data;
    }

    /**
     * Import site pages data
     * @param array $tables_data
     * @param array options
     *      - bool options['changed']
     *      - null|string options['current_site_url'] - current url of first site settlement on current domain
     *      - null|int $options['current_domain_id'] - current domain id
     * @throws waDbException
     * @throws waException
     */
    protected function importSitePagesTableData($tables_data, $options = array())
    {
        if (empty($tables_data) || empty($tables_data['site_page'])) {
            return;
        }

        // just in case
        $white_list = array_fill_keys(array('site_page', 'site_page_params'), true);
        foreach ($tables_data as $table_name => $data) {
            if (empty($white_list[$table_name])) {
                unset($tables_data[$table_name]);
            }
        }

        // mapping from exported page ID to current page ID
        $page_id_map = array();

        // first export site_page data (cause we need fill $page_id_map)
        $site_page_data = $tables_data['site_page'];

        // change domain_id and route of site page data
        if (!empty($options['changed'])) {
            $site_page_data = $this->prepareSitePageData($site_page_data, $options);
        }

        $table = $this->newShopDemoDataTable('site_page');

        foreach ($site_page_data as $item) {
            try {

                $exported_id = $item['id'];
                unset($item['id']);

                $existed_item = $table->getByField(array(
                    'domain_id' => $item['domain_id'],
                    'route' => $item['route'],
                    'full_url' => $item['full_url']
                ));

                if ($existed_item) {
                    $table->updateById($existed_item['id'], $item);
                    $page_id_map[$exported_id] = $existed_item['id'];
                } else {
                    $new_id = $table->insert($item, 1);
                    $page_id_map[$exported_id] = $new_id;
                }

            } catch (waDbException $e) {
                // ignore or maybe log
                static::printLog($e);
                throw $e;
            }
        }

        // that try export site_page_params data if they exist
        $site_page_params_data = isset($tables_data['site_page_params']) ? $tables_data['site_page_params'] : array();
        if (empty($site_page_params_data)) {
            return;
        }

        $table = $this->newShopDemoDataTable('site_page_params');
        foreach ($site_page_params_data as $item) {
            try {
                if (empty($page_id_map[$item['page_id']])) {
                    continue;
                }

                // remap page_id
                $item['page_id'] = $page_id_map[$item['page_id']];

                $table->insert($item, 1);
            } catch (waDbException $e) {
                // ignore or maybe log
                static::printLog($e);
                throw $e;
            }
        }
    }

    /**
     * Import site pages data
     * @param array $data
     * @param array options
     *      - null|string $options['current_domain_id'] - current domain id
     *      - null|string options['current_site_url']   - current url of first site settlement on current domain
     * @return array
     * @throws waDbException
     * @throws waException
     */
    protected function prepareSitePageData($data, $options = array())
    {
        foreach ($data as &$item) {
            if (isset($options['current_domain_id'])) {
                $item['domain_id'] = $options['current_domain_id'];
            }
            if (isset($options['current_site_url'])) {
                $item['route'] = $options['current_site_url'];
            }

        }
        unset($item);

        return $data;
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
            static::printLog("Couldn't find 'wa-data/public/shop/' dir after unpacking source zip of source data");
            static::printLog($e->getMessage());
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
            static::printLog("Couldn't find 'wa-data/protected/shop/' dir after unpacking source zip of source data");
            static::printLog($e->getMessage());
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
            static::printLog("Couldn't find 'wa-data/public/site/themes' dir after unpacking source zip of source data");
            static::printLog($e->getMessage());
        }
    }

    protected function backupWaConfig()
    {
        // routing.php
        $wa_config_files_dir = wa()->getConfigPath();
        $routing_config_path = $wa_config_files_dir . '/routing.php';

        // checkout config
        $shop_configs_path = $wa_config_files_dir . '/apps/shop/';
        $shop_checkout2_config_path = $shop_configs_path . 'checkout2.php';

        // site domain config for current domain
        $current_domain = $this->getCurrentDomain();
        $site_configs_path = $wa_config_files_dir . '/apps/site/domains/';
        $site_domain_config_path = $site_configs_path . $current_domain . '.php';

        $backup_path = wa('shop')->getDataPath('backup/onboarding/'.date('Ymd-His'));
        waFiles::create($backup_path, true);
        foreach([
            $routing_config_path,
            $shop_checkout2_config_path,
            $site_domain_config_path
        ] as $file) {
            if (file_exists($file)) {
                $new_file = $backup_path.'/'.basename($file);
                waFiles::copy($file, $new_file);
            }
        }
    }

    protected function getCurrentRoutingConfig()
    {
        $current_config_files_dir = wa()->getConfigPath();
        $current_config_routing_file = $current_config_files_dir . '/routing.php';
        if (file_exists($current_config_routing_file)) {
            return include($current_config_routing_file);
        }
        return [];
    }

    protected function importShopRoutingSettings($config_files_dir)
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

        $current_routing_config = $this->getCurrentRoutingConfig();
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

                        // checkout 1 if there is not such key - (shop version <= 7)
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

        $this->varExportToFile($current_routing_config, wa()->getConfigPath() . '/routing.php');

        return array(
            // will need for update checkout2 config
            'exporter_checkout_storefront_id' => ifset($exporter_shop_settlement_config['checkout_storefront_id']),
            'current_checkout_storefront_ids' => $checkout_storefront_ids
        );

    }

    protected function getCurrentDomainShopSettlementUrl()
    {
        $domain = $this->getCurrentDomain();
        foreach ($this->getCurrentRoutingConfig() as $d => $settlements) {
            if ($d === $domain && is_array($settlements)) {
                foreach ($settlements as $settlement) {
                    if (is_array($settlement) && ifset($settlement, 'app', null) === 'shop' && isset($settlement['url'])) {
                        return [
                            $domain,
                            $settlement['url']
                        ];
                    }
                }
            }
        }
        return [$domain, null];
    }

    /**
     * Change settlement settings in current installation according to settlement settings in archive we're importing.
     *
     * @param $config_files_dir
     * @return array $result
     *      - bool $result['changed']
     *      - null|string $result['exported_site_url']  - url of first site settlement of exported shop data
     *      - null|string $result['current_site_url']   - current url of first site settlement on current domain
     *
     * @throws waException
     */
    protected function importOtherRoutingSettings($config_files_dir)
    {
        $result = array(
            'changed' => false,
            'exported_site_url' => null,
            'current_site_url' => null
        );

        $config_routing_file = $config_files_dir . 'routing.php';
        if (!file_exists($config_routing_file)) {
            return $result;
        }

        $routing_config_being_imported = include($config_routing_file);
        $apps_to_update = array_fill_keys($this->getImportantInstalledApps(), true);

        // from archive being imported, get first settlement of each app we need to update
        $routing_app_rules_being_imported = [];
        foreach ($routing_config_being_imported as $domain => $domain_routing_config) {
            if (!is_array($domain_routing_config)) {
                continue; // may be string 'mirror'
            }

            foreach ($domain_routing_config as $settlement_config) {
                if (is_array($settlement_config)) {
                    $app_id = ifset($settlement_config, 'app', null);
                    if ($app_id && !isset($routing_app_rules_being_imported[$app_id]) && isset($apps_to_update[$app_id])) {
                        $routing_app_rules_being_imported[$app_id] = $settlement_config;
                    }
                }
            }
        }

        if (!$routing_app_rules_being_imported) {
            return $result;
        }

        // Read routing config from current installation
        $current_routing_config = $this->getCurrentRoutingConfig();

        // Create new site (and other apps) settlement on each domain that has shop settlement but no site settlement
        list($changed, $current_routing_config) = $this->createEmptySettlements($current_routing_config, $routing_app_rules_being_imported);

        // `$imported_site_settlement_config` will be set to first site settlement of current domain
        // we'll return this to be used later ouside this method
        $imported_site_settlement_config = null;
        $domain = $this->getCurrentDomain();

        foreach ($current_routing_config as $current_domain => &$current_domain_routing_config) {
            if (!is_array($current_domain_routing_config)) {
                continue; // may be string 'mirror'
            }

            foreach ($current_domain_routing_config as &$current_settlement_config) {
                if (!is_array($current_settlement_config)) {
                    continue;
                }

                $app_id = ifset($current_settlement_config, 'app', null);
                if (!$app_id || empty($routing_app_rules_being_imported[$app_id])) {
                    // not interested in importing this app
                    continue;
                }

                // do not modify any settlement that already has a theme selected
                // it means that user already set it up by hand, we don't want to break that
                if (empty($current_settlement_config['theme'])) {
                    $current_settlement_config = [
                        // URL should stay as set up on current installation, not taken from archive being imported
                        'url' => ifset($current_settlement_config, 'url', $routing_app_rules_being_imported[$app_id]['url']),
                    ] + $routing_app_rules_being_imported[$app_id];
                }

                // first site settlement of current domain is to be used later outside of this method
                if ($app_id == 'site' && $current_domain === $domain && !$imported_site_settlement_config) {
                    $imported_site_settlement_config = $current_settlement_config;
                }

                $changed = true;
            }
            unset($current_settlement_config);
        }
        unset($current_domain_routing_config);

        if ($changed) {
            $this->varExportToFile($current_routing_config, wa()->getConfigPath() . '/routing.php');
        }

        $result['changed'] = $changed;
        $result['exported_site_url'] = $routing_app_rules_being_imported['site']['url'];
        if ($imported_site_settlement_config) {
            $result['current_site_url'] = $imported_site_settlement_config['url'];
        }

        return $result;
    }

    protected function getImportantInstalledApps()
    {
        $apps_to_check = ['site'];
        foreach(['hub', 'blog', 'photos', 'helpdesk'] as $app_id) {
            if (wa()->appExists($app_id)) {
                $apps_to_check[] = $app_id;
            }
        }
        return $apps_to_check;
    }

    /**
     * For each domain in $current_routing_config, if domain has a shop settlement
     * but no settlement of site (or another important app we care about), add a settlement to that domain.
     * Site settlement will be added as root (url=*) if domain has no root settlement,
     * otherwise url=site/*
     */
    protected function createEmptySettlements($current_routing_config, array $routing_app_rules_being_imported)
    {
        $apps_to_check = array_keys($routing_app_rules_being_imported + ['shop' => true, 'site' => true]);

        // Figure out which domains need a new site settlement
        $changed = false;
        $affected_domains = []; // domain => route url
        foreach ($current_routing_config as $current_domain => $current_domain_routing_config) {
            if (!is_array($current_domain_routing_config)) {
                continue;
            }
            $domain_has_app = [];
            $domain_has_root_settlement = false;
            foreach ($current_domain_routing_config as $current_settlement_config) {
                if (!is_array($current_settlement_config)) {
                    continue;
                }

                $domain_has_root_settlement = $domain_has_root_settlement || ifset($current_settlement_config, 'url', null) === '*';
                foreach ($apps_to_check as $app_id) {
                    $domain_has_app[$app_id] = !empty($domain_has_app[$app_id]) || ifset($current_settlement_config, 'app', null) === $app_id;
                }
            }

            if ($domain_has_app['shop']) {
                foreach ($apps_to_check as $app_id) {
                    if ($app_id == 'shop' || !empty($domain_has_app[$app_id])) {
                        continue;
                    }
                    $changed = true;
                    if ($app_id == 'site' && !$domain_has_root_settlement) {
                        $current_routing_config[$current_domain][] = [
                            'url' => '*',
                            'app' => 'site',
                        ];
                    } else {
                        array_unshift($current_routing_config[$current_domain], [
                            'url' => $app_id.'/*',
                            'app' => $app_id,
                        ]);
                    }
                }
            }
        }

        return [$changed, $current_routing_config];
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
                        $this->varExportToFile($current_checkout2_config, $current_checkout2_config_path);
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

    /**
     * @param $config_files_dir
     * @param $options
     *      - bool $options['changed']
     *      - null|string $options['exported_site_url']  - url of first site settlement of exported shop data
     *      - null|string $options['current_site_url']   - current url of first site settlement on current domain
     * @throws waException
     */
    protected function importSiteNavigationMenuSettings($config_files_dir, $options = array())
    {
        $navigation_menu_settings = $this->readSiteNavigationMenuSettings($config_files_dir, $options);

        if (!$navigation_menu_settings) {
            return;
        }

        // change urls in site menu items from exported_site_url to current_site_url
        if (!empty($options['changed']) && !empty($options['exported_site_url']) && !empty($options['current_site_url']) && $options['exported_site_url'] != $options['current_site_url']) {

            // remove * at the end of route urls
            $from = preg_replace('!\*$!', '', $options['exported_site_url']);
            $to = preg_replace('!\*$!', '', $options['current_site_url']);

            $navigation_menu_settings = $this->changeUrlPrefixOfSiteNavigationMenuSettings($navigation_menu_settings, array(
                $from => $to
            ));
        }

        list($navigation_menu_settings, $changed) = $this->mergeSiteNavigationMenuSettings($navigation_menu_settings);
        if (!$changed) {
            return;
        }

        // get current domain
        $current_domain = $this->getCurrentDomain();

        $current_configs_path = wa()->getConfigPath() . '/apps/site/domains/';
        $current_configs_path_filepath = $current_configs_path . $current_domain . '.php';

        if (file_exists($current_configs_path_filepath)) {
            $domain_settings = include($current_configs_path_filepath);
        } else {
            $domain_settings = array();
        }

        $domain_settings['apps'] = $navigation_menu_settings;

        waFiles::create($current_configs_path);
        $this->varExportToFile($domain_settings, $current_configs_path_filepath);

    }

    protected function varExportToFile($data, $path)
    {
        return waUtils::varExportToFile($data, $path);
    }

    /**
     * Read site navigation menu settings from path $config_files_dir
     * Find first .php file in $config_files_dir that has key 'apps' and value is array
     *
     * @param string $config_files_dir
     * @return array|mixed
     */
    protected function readSiteNavigationMenuSettings($config_files_dir)
    {
        $navigation_menu_settings = array();
        $tmp_configs_path = $config_files_dir . 'apps/site/domains/';
        foreach (waFiles::listdir($tmp_configs_path) as $filepath) {
            if (substr($filepath, -4) !== '.php') {
                continue;
            }
            $domain_settings = include($tmp_configs_path . $filepath);
            if (isset($domain_settings['apps']) && is_array($domain_settings['apps'])) {
                $navigation_menu_settings = $domain_settings['apps'];
            }
        }
        return $navigation_menu_settings;
    }

    /**
     * Merge input settings with those that exist
     * Don't lose existed menu settings
     * @param array $navigation_menu_settings
     * @return array $result
     *      - array $result[0] - result navigation_menu_settings
     *      - bool $result[1] - has be actually merged or nothing changed
     * @throws waException
     */
    protected function mergeSiteNavigationMenuSettings($navigation_menu_settings)
    {
        // get current domain
        $current_domain = $this->getCurrentDomain();

        $current_configs_path = wa()->getConfigPath() . '/apps/site/domains/';
        $current_configs_path_filepath = $current_configs_path . $current_domain . '.php';

        if (file_exists($current_configs_path_filepath)) {
            $domain_settings = include($current_configs_path_filepath);
        } else {
            $domain_settings = array();
        }

        $existed_navigation_menu_settings = array();
        if (isset($domain_settings['apps'])) {
            $existed_navigation_menu_settings = $domain_settings['apps'];
        }

        $url_existed = array();
        foreach ($existed_navigation_menu_settings as $menu_setting) {
            $url_existed[$menu_setting['url']] = true;
        }

        $changed = false;

        // add menu_setting items but don't lose existed urls
        foreach ($navigation_menu_settings as $menu_setting) {
            if (empty($url_existed[$menu_setting['url']])) {
                $existed_navigation_menu_settings[] = $menu_setting;
                $changed = true;
            }
        }

        return array($existed_navigation_menu_settings, $changed);
    }

    /**
     * @param $navigation_menu_settings
     * @param array $replace , key => value
     * @return mixed
     */
    protected function changeUrlPrefixOfSiteNavigationMenuSettings($navigation_menu_settings, $replace)
    {
        if (empty($replace)) {
            return $navigation_menu_settings;
        }

        // add menu_setting items but don't lose existed urls
        foreach ($navigation_menu_settings as &$menu_setting) {
            foreach ($replace as $from => $to) {
                $from_len = strlen($from);

                $url = $menu_setting['url'];
                $slash = substr($url, 0, 1) === '/';

                if ($slash) {
                    $url = substr($url, 1);
                }

                // change prefix from $from to $to
                if (substr($url, 0, $from_len) === $from) {
                    $url = $to . substr($url, $from_len);
                }

                if ($slash) {
                    $url = '/' . ltrim($url, '/');  // if happen several slashes at the beginning leave only one
                } else {
                    $url = ltrim($url, '/');        // no slashes
                }

                $menu_setting['url'] = $url;
            }
        }
        unset($menu_setting);

        return $navigation_menu_settings;
    }

    protected function getCurrentDomain()
    {
        return wa()->getConfig()->getDomain();
    }

    protected function getCurrentDomainId()
    {
        $domain = $this->getCurrentDomain();
        wa('site');
        $dm = new siteDomainModel();
        $domain_info = $dm->getByName($domain);
        if ($domain_info) {
            return $domain_info['id'];
        }
        return null;
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

    /**
     * @return array $result
     *   - bool   $result['status'] Can importer work
     *   - string $result['reason'] String reason why can't work
     */
    public static function canWork()
    {
        if (class_exists('ZipArchive')) {
            return array(
                'status' => true,
                'reason' => ''
            );
        } else {
            return array(
                'status' => false,
                'reason' => _w('Zip extension for PHP is required to be enabled on the server to import product examples into your online store.')
            );
        }
    }

    /**
     * @param string|null $locale
     * @return array|mixed
     * @throws waException
     */
    public static function getSourceList($locale = null)
    {
        if (self::$source_list === null) {
            if (empty($locale) || !is_string($locale)) {
                $locale = wa()->getLocale();
            }
            self::$source_list = array_filter(self::obtainSourceList(), function($value) use ($locale) {
                return $value['locale'] == $locale;
            });
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
        if ($message instanceof Exception) {
            $str = $message->getMessage();
            $code = $message->getCode();
            $trace = $message->getTraceAsString();
            $message = "{$code}: {$str}\n{$trace}";
        }
        if (!is_scalar($message)) {
            $message = var_export($message, true);
        }
        waLog::log($message, 'shop/demo_data_importer.log');
    }
}
