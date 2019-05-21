<?php

class shopCustomer extends waContact
{
    protected $customer_data;

    /**
     * type of customer: 'person', 'person' is when waContact.is_company == 0
     */
    const TYPE_PERSON = 'person';

    /**
     * type of customer: 'company', 'company' is when waContact.is_company == 1
     */
    const TYPE_COMPANY = 'company';

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
     * Set bool confirmation mark for main email of customer (contact)
     *
     * @param bool $confirmed
     * @param null|string|array $email - strengthen condition if need
     *   - NULL:   always set confirmation mark
     *   - string: check if main email of current customer equals to passed as argument
     *       If so     - set confirmation mark
     *       Otherwise - not set confirmation mark
     *   - array: try extract from '0' index scalar email value and than do the same as if it is a string case (see above)
     *
     * @throws waException
     */
    public function markMainEmailAsConfirmed($confirmed, $email = null)
    {
        if ($email === null) {
            $can_mark = true;
        } elseif (is_scalar($email)) {
            $email = trim((string)$email);
            $can_mark = true;
        } elseif (is_array($email) && isset($email[0]) && is_scalar($email[0])) {
            $email = trim((string)$email[0]);
            $can_mark = true;
        } else {
            $can_mark = false;
        }

        if (!$can_mark) {
            return;
        }

        $customer_emails = $this->get("email");
        if (isset($customer_emails[0])) {
            $customer_email = trim($customer_emails[0]['value']);
        } else {
            $customer_email = null;
        }

        if ($customer_email === null) {
            return;
        }

        if ($email === null || $customer_email === $email) {
            $cem = new waContactEmailsModel();
            if ($confirmed) {
                $status = waContactEmailsModel::STATUS_CONFIRMED;
            } else {
                $status = waContactEmailsModel::STATUS_UNKNOWN;
            }
            $cem->updateContactEmailStatus($this->getId(), $customer_email, $status);
        }
    }

    /**
     * Set bool confirmation mark for main phone of customer (contact)
     *
     * @param bool $confirmed
     * @param null|string|array $phone - strengthen condition if need
     *   - NULL:   always set confirmation mark
     *   - string: check if main phone of current customer equals to passed as argument.
     *       If so     - set confirmation mark
     *       Otherwise - not set confirmation mark
     *   - array: try extract from '0' index scalar phone value and than do the same as if it is a string case (see above)
     *
     * @throws waException
     */
    public function markMainPhoneAsConfirmed($confirmed, $phone = null)
    {
        if ($phone === null) {
            $can_mark = true;
        } elseif (is_scalar($phone)) {
            $phone = trim((string)$phone);
            $can_mark = true;
        } elseif (is_array($phone) && isset($phone[0]) && is_scalar($phone[0])) {
            $phone = trim((string)$phone[0]);
            $can_mark = true;
        } else {
            $can_mark = false;
        }

        if (!$can_mark) {
            return;
        }

        $customer_phones = $this->get("phone");
        if (isset($customer_phones[0])) {
            $customer_phone = trim($customer_phones[0]['value']);
        } else {
            $customer_phone = null;
        }

        if ($customer_phone === null) {
            return;
        }

        if ($phone === null || waContactPhoneField::isPhoneEquals($customer_phone, $phone)) {
            $cdm = new waContactDataModel();
            if ($confirmed) {
                $status = waContactDataModel::STATUS_CONFIRMED;
            } else {
                $status = waContactDataModel::STATUS_UNKNOWN;
            }
            $cdm->updateContactPhoneStatus($this->getId(), $customer_phone, $status);
        }
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

    public function getUserpic($options = array())
    {
        $options = is_array($options) ? $options : array();

        /**
         * @var shopConfig $config
         */
        $config = wa('shop')->getConfig();

        $use_gravatar = $config->getGeneralSettings('use_gravatar');

        $size = isset($options['size']) && wa_is_int($options['size']) ? $options['size'] : 96;

        $photo = $this->get('photo');
        if (!$photo && $use_gravatar) {
            $photo = shopHelper::getGravatarPic($this->get('email', 'default'), array(
                'size' => $size,
                'default' => $config->getGeneralSettings('gravatar_default'),
                'is_company' => $this['is_company']
            ));
        } else {
            $photo = $this->getPhoto($size);
        }

        return $photo;
    }

    public static function getUserpics($contacts)
    {
        /**
         * @var shopConfig $config
         */
        $config = wa('shop')->getConfig();

        $use_gravatar = $config->getGeneralSettings('use_gravatar');
        $gravatar_default = $config->getGeneralSettings('gravatar_default');

        $photos = array();

        foreach ($contacts as $index => $c) {

            $default_email = null;

            if (isset($c['email']) && $c['email']) {
                if ($c instanceof waContact) {
                    $default_email = $c->get('email', 'default');
                } else {

                    if (is_array($c['email']) && isset($c['email'][0])) {
                        $emails = $c['email'];
                    } elseif (is_array($c['email'])) {
                        $emails = array($c['email']);
                    } elseif (is_scalar($c['email'])) {
                        $emails = array(array('email' => $c['email']));
                    } else {
                        $emails = array(array('email' => ''));
                    }

                    $email = reset($emails);
                    if (isset($email['email'])) {
                        $default_email = $email['email'];
                    } elseif (isset($email['value'])) {
                        $default_email = $email['value'];
                    } else {
                        $default_email = '';
                    }
                }
            }

            $photo = ifset($c, 'photo', 0);
            if (!$photo && $use_gravatar) {
                $photo = shopHelper::getGravatarPic($default_email ? $default_email : '', array(
                    'size' => 50,
                    'default' => $gravatar_default,
                    'is_company' => !empty($c['is_company'])
                ));
            } else {
                $id = ifset($c, 'id', 0);
                $id = $id > 0 ? $id : 0;
                $type = !empty($c['is_company']) ? 'company' : 'person';
                $photo = waContact::getPhotoUrl($id, $photo, 50, 50, $type);
            }

            // We must index by $index, not by ID, cause current $c may be empty contact (not existed)
            $photos[$index] = $photo;
        }

        return $photos;
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
                    $formatted = $field->format($field_value, 'top,html');
                    $field_value['formatted'] = $formatted;
                    $field_value['is_confirmed'] = ($field_id === 'email' && $field_value['status'] === waContactEmailsModel::STATUS_CONFIRMED) ||
                        $field_value['status'] === waContactDataModel::STATUS_CONFIRMED;
                }
                unset($field_value);

                if (!$field_values) {
                    continue;
                }

                $values = $field_values;
                $default_value = array_shift($values);
                $other_values = $values;

                $all_values = $field_values;


                $top[$c['id']][$field_id] = array(
                    'id' => $field_id,
                    'name' => $field->getName(),
                    'all_values' => $all_values,
                    'default_value' => $default_value,
                    'other_values' => $other_values
                );

            }
        }

        return $top;
    }
}
