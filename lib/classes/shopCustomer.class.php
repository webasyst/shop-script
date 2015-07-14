<?php

class shopCustomer extends waContact
{
    protected $customer_data;

    /**
     * Returns current customer's affiliate bonus points.
     *
     * @return int
     */
    public function affiliateBonus()
    {
        if ($this->getId()) {
            return (float)$this->getCustomerData('affiliate_bonus');
        }
        return 0;
    }

    public function getCustomerData($name = null)
    {
        if ($this->customer_data == null) {
            $customer_model = new shopCustomerModel();
            $this->customer_data = $customer_model->getById($this->getId());
        }
        if ($name) {
            return isset($this->customer_data[$name]) ? $this->customer_data[$name] : null;
        }
        return $this->customer_data;
    }

    /**
     * @param int $customer_id
     */
    public static function recalculateTotalSpent($customer_id)
    {
        static $model = null;
        if ($model === null) {
            $model = new shopCustomerModel();
        }
        $model->recalcTotalSpent($customer_id);
    }

}