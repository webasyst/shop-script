<?php
/**
 * Allows channel to provide URLs to external sites (e.g. a marketplace) for given list 
 * of categories, orders, products or SKUs.
 */
interface shopSalesChannelWithLinksInterface
{
    /**
     * Returns array [entity_id => string or array of strings], allowing to provide more than one URL per product/order/category. 
     * 
     * Resulting URLs support templating like `shop_external_entity.url_template`
     * 
     * @param string $entity_type
     * @param array $rows [entity_id => DB row] for orders, categories, or products (contains 'skus' for $entity_type='sku')
     * @return array entity_id => 
     */
    public function getExternalLinks(array $channel, string $entity_type, array $rows): array;
}
