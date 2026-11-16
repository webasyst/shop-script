<?php

/**
 * Fees (commissions) declared for an order by payment and shipping plugins.
 * One record per (order_id, fee_provider); preliminary and final fees overwrite each other
 * according to the logic in shopOrderFees::declareFees().
 */
class shopOrderFeesModel extends waModel
{
    protected $table = 'shop_order_fees';
    protected $id = array('order_id', 'fee_provider');

    /**
     * Sum of all fee amounts for the given order.
     *
     * @param int $order_id
     * @return float
     */
    public function getTotalByOrder($order_id)
    {
        return (float)$this->select('SUM(amount) AS total')
            ->where('order_id = ?', (int)$order_id)
            ->fetchField('total');
    }

    /**
     * Fee record of the given provider for the order, or null.
     *
     * @param int $order_id
     * @param string $fee_provider
     * @return array|null
     */
    public function getByProvider($order_id, $fee_provider)
    {
        return $this->getByField(array(
            'order_id'     => (int)$order_id,
            'fee_provider' => $fee_provider,
        ));
    }

    /**
     * Delete all fee records of the order whose provider starts with the given prefix
     * (e.g. 'payment_' or 'shipping_').
     *
     * @param int $order_id
     * @param string $provider_prefix
     * @return bool|mixed
     */
    public function deleteByProviderPrefix($order_id, $provider_prefix)
    {
        return $this->exec("
            DELETE FROM {$this->table}
            WHERE order_id = ".(int)$order_id." 
                AND fee_provider LIKE '".$this->escape($provider_prefix, 'like')."%'
        ");
    }
}
