<?php
class shopSettingsResetAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->isAdmin('shop')) {
            throw new waRightsException(_w('Access denied'));
        }
        if (waRequest::post('reset')) {
            $confirm = waRequest::post('confirm');
            if ($confirm !== null) {
                $storage = $this->getStorage();
                if ($confirm == $storage->read('reset_confirm')) {
                    $storage->del('reset_confirm');
                    $this->reset();
                } else {
                    $this->view->assign('error', true);
                    $this->makeConfirm();
                }
            } else {
                $this->makeConfirm();
            }
        }
    }

    private function makeConfirm()
    {

        $confirm = 'YES '.substr(md5(time()), 0, 3);
        $this->getStorage()->write('reset_confirm', $confirm);
        $this->view->assign('confirm', $confirm);
    }

    private function reset()
    {
        /**
         * @event reset
         *
         * All application settings are about to be reset, and all DB tables truncated.
         * Plugin tables will not be truncated automatically.
         * Plugins should subscribe to this event and delete their data if they want to support full shop reset.
         *
         * @param void
         * @return void
         */
        wa()->event('reset');

        //
        // Truncate all app tables (not the plugin tables, though)
        //
        $db_schema_path = wa('shop')->getConfig()->getAppConfigPath('db');
        if (!file_exists($db_schema_path)) {
            throw new Exception('Unable to read DB tables');
        }
        $db_schema = include($db_schema_path);
        if (!is_array($db_schema)) {
            throw new Exception('Unable to read DB tables');
        }

        $model = new waModel();
        foreach (array_keys($db_schema) as $table) {
            $exist = false;
            try {
                $model->query(sprintf("SELECT * FROM `%s` WHERE 0", $table));
                $exist = true;
                $model->query(sprintf("TRUNCATE `%s`", $table));
            } catch (waDbException $ex) {
                if ($exist) {
                    throw $ex;
                }
            }
        }
        $sqls = array();
        $sqls[] = 'UPDATE`shop_type` SET`count` = 0';
        $sqls[] = 'UPDATE`shop_set` SET`count` = 0';
        foreach ($sqls as $sql) {
            $model->query($sql);
        }

        // Delete app's categories from contacts
        $ccm = new waContactCategoryModel();
        $ccm->deleteByField('app_id', 'shop');

        // Delete contact access rights that contain type_id
        $model->exec("DELETE FROM wa_contact_rights WHERE app_id='shop' AND name LIKE 'type.%' AND name<>'type.all'");

        // Delete settings from wa_app_settings and wa_contact_settings.
        // It's OK to do direct queries because we'll clear the cache afterwards.
        // Note that since we delete the `update_time` from wa_app_settings of shop and plugins,
        // next app start will call install.php
        $model->exec("DELETE FROM wa_app_settings WHERE app_id='shop' OR app_id LIKE 'shop.%'");
        $model->exec("DELETE FROM wa_contact_settings WHERE app_id='shop' OR app_id LIKE 'shop.%'");

        //
        // Delete files from wa-config, wa-data and wa-cache
        //

        // wa-data
        $paths = array();
        $paths[] = wa()->getDataPath('products', false, 'shop');
        $paths[] = wa()->getDataPath('products', true, 'shop');

        // wa-config
        $config_path = wa()->getConfigPath('shop');
        foreach (waFiles::listdir($config_path, true) as $path) {
            if (!in_array($path, array('plugins.php', '..', '.'))) {
                $paths[] = $config_path.'/'.$path;
            }
        }

        // wa-cache
        $paths[] = wa()->getTempPath();
        $paths[] = wa()->getCachePath(null, 'shop');
        $paths[] = wa()->getCachePath(null, 'webasyst');

        foreach ($paths as $path) {
            waFiles::delete($path, true);
        }

         /**
         * @event reset_complete
         *
         * All application settings has just been reset, and all shop tables truncated.
         * Note that during this event, both the app AND plugins are is in state before install.php is run (although db tables are present).
         * Some settings may be unavailable.
         *
         * install.php of both the app and plugins will run next time the app starts.
         *
         * @param void
         * @return void
         */
        wa()->event('reset_complete');

        echo json_encode(array('result' => 'ok', 'redirect' => '?action=welcome'));
        exit;
    }
}

