<?php

/**
 * Model for temporary table `shop_product_promo_price_tmp`.
 *
 * Used in shopProductsCollection, when filtering and sorting by price of products.
 * In the promo we give the opportunity to redefine the price of the product for the duration of its action.
 * but in shop_product and shop_product_skus we don't want to rewrite these prices for various reasons.
 */
class shopProductPromoPriceTmpModel extends waModel
{
    protected $table = 'shop_product_promo_price_tmp';

    public function __construct($type = null, $writable = false)
    {
        $this->writable = $writable;
        $this->type = $type ? $type : 'default';
        $this->adapter = waDbConnector::getConnection($this->type, $this->writable);
    }

    public function __destruct()
    {
        $this->destroyTable();
    }

    public function setupTable()
    {
        $this->exec("CREATE TEMPORARY TABLE IF NOT EXISTS {$this->table} (
                        storefront            VARCHAR(255) NOT NULL,
                        promo_id              INT(11) NOT NULL,
                        product_id            INT(11) NOT NULL,
                        sku_id                INT(11) NOT NULL,
                        price                 DECIMAL(15, 4) DEFAULT '0.0000' NULL,
                        primary_price         DECIMAL(15, 4) DEFAULT '0.0000' NULL,
                        compare_price         DECIMAL(15, 4) DEFAULT '0.0000' NULL,
                        primary_compare_price DECIMAL(15, 4) DEFAULT '0.0000' NULL,
                        INDEX `storefront` (`storefront`),
                        INDEX `product_id` (`product_id`),
                        INDEX `sku_id` (`sku_id`),
                        INDEX `primary_price` (`primary_price`),
                        unique (storefront, product_id, sku_id)
                    ) ENGINE = MEMORY DEFAULT CHARSET utf8");
        $this->exec("TRUNCATE {$this->table}");
        $this->getMetadata();
    }

    public function destroyTable()
    {
        $disable_exception_log = waConfig::get('disable_exception_log');
        waConfig::set('disable_exception_log', true);

        try {
            $this->exec("DROP TEMPORARY TABLE IF EXISTS {$this->table}");
        } catch (waDbException $e) {
            // ignore error, because it is destructor - script already done work (with high probability)
            // the most annoying error is 2014: commands out of sync, it could be because db resources already is destroyed but we try execute sql query
            // if you want log error, at least not log 2014 error
            // but for now ignore all errors and not print in log
        }

        waConfig::set('disable_exception_log', $disable_exception_log);
    }
}
