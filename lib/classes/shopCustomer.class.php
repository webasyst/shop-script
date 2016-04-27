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

    public function getCategories()
    {
        $all_categories = self::getAllCategories();
        $ccsm = new waContactCategoriesModel();

        $contact_categories = array();
        foreach ($ccsm->getContactCategories($this->getId()) as $category) {
            if (isset($all_categories[$category['id']])) {
                $contact_categories[$category['id']] = $category;
            }
        }

        return $contact_categories;
    }

    /**
     * @return array
     */
    public static function getAllCategories()
    {
        static $categories;

        if ($categories === null) {
            $ccm = new waContactCategoryModel();
            $categories = array();
            foreach ($ccm->getAll() as $c) {
                if ($c['app_id'] == 'shop') {
                    $c['cnt'] = 0;
                    $categories[$c['id']] = $c;
                }
            }

            if (!$categories) {
                return array();
            }

            $cm = new shopCustomerModel();
            $counts = $cm->getCategoryCounts(array_keys($categories));
            foreach ($categories as &$c) {
                $c['cnt'] = ifset($counts[$c['id']], 0);
            }
            unset($c);
        }

        return $categories;
    }
}