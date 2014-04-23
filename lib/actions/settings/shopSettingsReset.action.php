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
         * Notify plugins about reset all settings
         *
         * @param void
         * @return void
         */
        wa()->event('reset');

        //XXX hardcode
        $tables = array(
            'shop_page',
            'shop_page_params',

            'shop_category',
            'shop_category_params',
            'shop_category_products',
            'shop_category_routes',
            'shop_product',
            'shop_product_params',
            'shop_product_features',
            'shop_product_features_selectable',
            'shop_product_images',
            'shop_product_pages',
            'shop_product_related',
            'shop_product_reviews',
            'shop_product_services',
            'shop_product_skus',
            'shop_product_stocks',
            'shop_product_stocks_log',
            'shop_search_index',
            'shop_search_word',

            'shop_tag',
            'shop_product_tags',

            'shop_set',
            'shop_set_products',

            'shop_stock',

            'shop_feature',
            'shop_feature_values_dimension',
            'shop_feature_values_double',
            'shop_feature_values_text',
            'shop_feature_values_varchar',
            'shop_feature_values_color',
            'shop_feature_values_range',

            'shop_type',
            'shop_type_features',
            'shop_type_services',
            'shop_type_upselling',

            'shop_service',
            'shop_service_variants',

            'shop_currency',

            'shop_customer',
            'shop_cart_items',
            'shop_order',
            'shop_order_items',
            'shop_order_log',
            'shop_order_log_params',
            'shop_order_params',
            'shop_affiliate_transaction',

            'shop_checkout_flow',
            'shop_notification',
            'shop_notification_params',

            'shop_coupon',
            'shop_discount_by_sum',

            'shop_tax',
            'shop_tax_regions',
            'shop_tax_zip_codes',

            'shop_affiliate_transaction',

            'shop_importexport',
        );
        $model = new waModel();
        foreach ($tables as $table) {
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

        $ccm = new waContactCategoryModel();
        $ccm->deleteByField('app_id', 'shop');

        $app_settings_model = new waAppSettingsModel();

        $currency_model = new shopCurrencyModel();
        $currency_model->insert(array(
            'code' => 'USD',
            'rate' => 1.000,
            'sort' => 1,
        ), 2);

        $app_settings_model->set('shop', 'currency', 'USD');
        $app_settings_model->set('shop', 'use_product_currency', true);

        $paths = array();
        $paths[] = wa()->getDataPath('products', false, 'shop');
        $paths[] = wa()->getDataPath('products', true, 'shop');

        $paths[] = wa()->getTempPath();

        $config_path = wa()->getConfigPath('shop');
        foreach (waFiles::listdir($config_path, true) as $path) {
            if (!in_array($path, array('plugins.php', '..', '.'))) {
                $paths[] = $config_path.'/'.$path;
            }
        }
        $paths[] = wa()->getCachePath(null, 'shop');


        foreach ($paths as $path) {
            waFiles::delete($path, true);
        }

        $path = wa()->getDataPath('products', true, 'shop');
        waFiles::write($path.'/thumb.php', '<?php
$file = realpath(dirname(__FILE__)."/../../../../")."/wa-apps/shop/lib/config/data/thumb.php";

if (file_exists($file)) {
    include($file);
} else {
    header("HTTP/1.0 404 Not Found");
}
');
        waFiles::copy($this->getConfig()->getAppPath('lib/config/data/.htaccess'), $path.'/.htaccess');
        echo json_encode(array('result' => 'ok', 'redirect' => '?action=welcome'));
        exit;
    }
}
