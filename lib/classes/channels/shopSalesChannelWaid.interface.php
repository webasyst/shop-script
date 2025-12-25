<?php
/**
 * Allows to save arbitrary key-value pairs to WAID Channel API.
 * 
 * When saving settings of shopSalesChannelType that implements this inteface, 
 * Shop will try to save a Channel via WAID API. This is used to implement
 * Telegram and other chat bots via Shop Headless API.
 */
interface shopSalesChannelWaidInterface
{
    /**
     * Returns array with two elements:
     * - a string containing full URL to public storefront Headless API 
     * - array with arbitrary keys and values (specific to this sale channel type) to save to WAID API
     * 
     * @param $channel array    row from shop_sales_channel with additional 'params' key
     * @return array 
     */
    public function getWaidChannelParams(array $channel): array;
}
