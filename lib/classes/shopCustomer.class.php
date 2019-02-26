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

    /**
     * @param int|int[] $contact_id
     * @return array|mixed
     */
    public static function getDuplicateStats($contact_id)
    {
        $input_contact_id = $contact_id;

        $contact_ids = waUtils::toIntArray($contact_id);
        $contact_ids = waUtils::dropNotPositive($contact_ids);

        if (!$contact_ids) {
            return array();
        }

        $stats = array();

        $email_stats = self::getEmailDuplicatesStats($contact_id);
        $phone_stats = self::getPhoneDuplicatesStats($contact_id);

        foreach ($contact_ids as $contact_id) {
            if (isset($email_stats[$contact_id])) {
                $stats[$contact_id]['email'] = $email_stats[$contact_id];
            } else {
                $stats[$contact_id]['email'] = array(
                    'value' => '',
                    'count' => 0
                );
            }
            if (isset($phone_stats[$contact_id])) {
                $stats[$contact_id]['phone'] = $phone_stats[$contact_id];
            }  else {
                $stats[$contact_id]['phone'] = array(
                    'value' => '',
                    'count' => 0
                );
            }
        }

        // When call method with scalar contact ID
        if (is_scalar($input_contact_id)) {
            if (isset($stats[$input_contact_id])) {
                return $stats[$input_contact_id];
            } else {
                return array(
                    'value' => '',
                    'count' => 0
                );
            }
        }

        // When call method with array of contact ID
        return $stats;

    }

    public static function getEmailDuplicatesStats($contact_id)
    {
        $contact_ids = waUtils::toIntArray($contact_id);
        $contact_ids = waUtils::dropNotPositive($contact_ids);

        if (!$contact_ids) {
            return array();
        }

        // SQL do that:
        // get primary emails of CUSTOMERS and then
        // join them with other emails of OTHER CUSTOMERS
        $sql = "SELECT `primary`.`contact_id`, `primary`.`email` AS `value`, COUNT(*) AS `count`
                   FROM `shop_customer` `customer`  
                   
                       JOIN `wa_contact_emails` `primary` ON `primary`.`contact_id` = `customer`.`contact_id`
                       
                       JOIN `wa_contact_emails` `other` ON `other`.`email` = `primary`.`email` 
                              AND `other`.`contact_id` != `primary`.`contact_id`
                              
                       JOIN `shop_customer` `other_customer` ON `other_customer`.`contact_id` = `other`.`contact_id`
                          
                   WHERE `customer`.`contact_id` IN (:ids) AND `primary`.`sort` = 0
                GROUP BY `primary`.`contact_id`";

        $cem = new waContactEmailsModel();
        return $cem->query($sql, array('ids' => $contact_ids))->fetchAll('contact_id');
    }

    public static function getPhoneDuplicatesStats($contact_id)
    {
        $contact_ids = waUtils::toIntArray($contact_id);
        $contact_ids = waUtils::dropNotPositive($contact_ids);

        if (!$contact_ids) {
            return array();
        }

        // SQL do that:
        // get primary phones and then join them with other phones of OTHER contacts
        $sql = "SELECT `primary`.`contact_id`, `primary`.`value`, COUNT(*) AS `count`
                  FROM `shop_customer` `customer`  
                    JOIN `wa_contact_data` `primary` ON `primary`.`contact_id` = `customer`.`contact_id` 
                      AND `primary`.`field` = 'phone'
                      
                    JOIN `wa_contact_data` `other` ON `other`.`value` = `primary`.`value` 
                      AND `other`.`contact_id` != `primary`.`contact_id`
                      AND `other`.`field` = 'phone'
                      
                    JOIN `shop_customer` `other_customer` ON `other_customer`.`contact_id` = `other`.`contact_id`
                      
                   WHERE `primary`.`contact_id` IN (:ids) AND `primary`.`sort` = 0 
                GROUP BY `primary`.`contact_id`";

        $cem = new waContactEmailsModel();
        return $cem->query($sql, array('ids' => $contact_ids))->fetchAll('contact_id');
    }

    public static function getCustomerTopFields($contact)
    {
        $top = self::getCustomersTopFields(array($contact));
        return isset($top[$contact['id']]) ? $top[$contact['id']] : array();
    }

    public static function getCustomersTopFields($contacts)
    {
        $top_fields = array(
            'email' => waContactFields::get('email'),
            'phone' => waContactFields::get('phone'),
            'im'    => waContactFields::get('im')
        );

        // TOP fields
        $top = array();

        foreach ($contacts as $c) {

            foreach ($top_fields as $field_id => $field) {

                if (empty($c[$field_id])) {
                    continue;
                }

                $field_values = $c[$field_id];
                foreach ($field_values as &$field_value) {
                    $field_value = $field->format($field_value, 'top,html');
                }
                unset($field_value);

                if (!$field_values) {
                    continue;
                }

                $raw_field_values = $c[$field_id];
                $raw_default_value = reset($raw_field_values);

                $is_confirmed = ($field_id === 'email' && $raw_default_value['status'] === waContactEmailsModel::STATUS_CONFIRMED) ||
                    $raw_default_value['status'] === waContactDataModel::STATUS_CONFIRMED;

                reset($field_values);

                $all_values = $field_values;

                $default_value = array_shift($all_values);
                $other_values = $all_values;
                
                $all_values = $field_values;

                $top[$c['id']][$field_id] = array(
                    'id' => $field_id,
                    'name' => $field->getName(),
                    'all_values' => $all_values,
                    'default_value' => $default_value,
                    'other_values' => $other_values,
                    'is_confirmed' => $is_confirmed,
                    'value' => join(", ", $all_values)
                );

            }
        }

        return $top;
    }
}
