<?php
/**
 * @since 12.8.0
 */
class shopOrderFees
{
    public $order_id;
    protected static $fees_model;

    public function __construct(int $order_id)
    {
        if (empty(self::$fees_model)) {
            self::$fees_model = new shopOrderFeesModel();
        }
        $this->order_id = $order_id;
    }

    /**
     * Declare a fee (commission) for the order on behalf of the given provider.
     *
     * Only one record per provider is kept. A preliminary fee is overwritten by any
     * subsequent declaration, whereas a final (non-preliminary) fee is overwritten
     * only by another final declaration for the same provider.
     *
     * @param float $fee_amount
     * @param string $fee_provider provider identifier, e.g. 'payment_pay' or 'shipping_manual'
     * @param string|null $description
     * @param bool|null $precise whether the amount is exact (true) or an estimate (false)
     * @param bool $preliminary whether the fee is a preliminary estimate
     * @return void
     */
    public function declareFees(float $fee_amount, string $fee_provider, ?string $description = null, bool $precise = false, bool $preliminary = false)
    {
        $existing = self::$fees_model->getByProvider($this->order_id, $fee_provider);

        // A final fee can not be replaced by a preliminary one.
        if ($existing && empty($existing['is_preliminary']) && $preliminary) {
            return;
        }

        // Only one payment_* or shipping_* fee row is allowed per order.
        if (strpos($fee_provider, 'payment_') === 0 || strpos($fee_provider, 'shipping_') === 0) {
            $prefix = strpos($fee_provider, 'payment_') === 0 ? 'payment_' : 'shipping_';
            self::$fees_model->deleteByProviderPrefix($this->order_id, $prefix);
        }

        self::$fees_model->replace([
            'order_id'       => $this->order_id,
            'fee_provider'   => $fee_provider,
            'amount'         => $fee_amount,
            'is_precise'     => $precise ? 1 : 0,
            'is_preliminary' => $preliminary ? 1 : 0,
            'description'    => $description,
        ]);

        $this->syncOrderFee();
    }

    /** Recalculate shop_order.fee as a sum of all declared fees of the order. */
    protected function syncOrderFee()
    {
        $total = self::$fees_model->getTotalByOrder($this->order_id);
        (new shopOrderModel())->updateById($this->order_id, array('fee' => $total));
    }
}