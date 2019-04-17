<?php

class shopCustomersCollection extends waContactsCollection
{
    protected $fields_cache = array();
    protected $customer_table_alias;
    protected $order_table_alias;

    /**
     *
     * @var shopCustomersCollectionPreparator
     */
    protected $preparator;

    public function __construct($hash = '', $options = array()) {
        parent::__construct($hash, $options);
        $this->preparator = new shopCustomersCollectionPreparator($this);
        $this->customer_table_alias = $this->preparator->getCustomerTableAlias();
        $this->order_table_alias = $this->preparator->getOrderTableAlias();
    }

    public function getCustomers($fields = "*", $offset = 0, $limit = 50) {
        return $this->getContacts($fields, $offset, $limit);
    }

    protected function getFields($fields)
    {
        if (!isset($this->fields_cache[$fields])) {
            $model = $this->getModel('customer');
            $contact_model = $this->getModel();

            $contact_fields = array();
            foreach (explode(',', parent::getFields($fields)) as $contact_field) {
                if (substr($contact_field, 2) !== 'c.' && $contact_model->fieldExists($contact_field)) {
                    $contact_field = 'c.' . $contact_field;
                }
                $contact_fields[] = $contact_field;
            }

            $ignore_post_fields = array();

            if ($fields === '*') {
                $contact_fields = array_merge($contact_fields, $this->getCustomerFields());
            } else {
                foreach (explode(',', $fields) as $f) {
                    if ($model->fieldExists($f)) {
                        $contact_fields[] = $this->customer_table_alias . '.' . $f;
                    } else if (strstr($f, '.') !== false) {
                        if (!preg_match('/([a-z_]+)\.([a-z_]+)(?:\s+AS\s+([a-z_]+)){0,1}/i', $f, $m)) {
                            continue;
                        }

                        $table = trim($m[1]);
                        $fld = trim($m[2]);
                        $fld_alias = null;
                        if (!empty($m[3])) {
                            $fld_alias = trim($m[3]);
                        }

                        if ($table === 'order') {
                            $contact_fields[] = $this->order_table_alias . '.' . $fld . ($fld_alias ? " AS {$fld_alias}" : '');
                            $ignore_post_fields[$f] = true;
                        }

                    } else if ($f === '*') {
                        $contact_fields = array_merge($contact_fields, $this->getCustomerFields());
                    }
                }
            }

            $this->fields_cache[$fields] = implode(',', $contact_fields);
        }

        if (!empty($this->post_fields['data'])) {
            foreach ($this->post_fields['data'] as $k => $post_field) {
                if (isset($ignore_post_fields[$post_field])) {
                    unset($this->post_fields['data'][$k]);
                }
            }
            if (empty($this->post_fields['data'])) {
                unset($this->post_fields['data']);
            }
        }

        $this->setGroupBy('c.id');

        return $this->fields_cache[$fields];
    }

    protected function filterPrepare($filter_id, $auto_title = true)
    {
        $this->preparator->filterPrepare($filter_id, $auto_title);
    }

    protected function searchPrepare($query, $auto_title = true)
    {
        $rest_query = $this->preparator->searchPrepare($query, $auto_title, false);
        parent::searchPrepare($rest_query, $auto_title);
    }

    public function orderBy($field, $order = 'ASC')
    {
        $field = trim($field);
        if ($field == '~data') {
            return parent::orderBy($field, $order);
        } else {
            $model = $this->getModel();
            $model->escape($field);
            $this->order_by = $field . ' ' . (strtoupper($order) === 'ASC' ? 'ASC' : 'DESC');
            return $this->order_by;
        }
    }

    protected function getCustomerFields($with_alias = true)
    {
        $customer_fields = array_keys($this->getModel('customer')->getMetadata());
        if ($with_alias) {
            foreach ($customer_fields as &$f) {
                $f = $this->customer_table_alias . '.' . $f;
            }
            unset($f);
        }
        return $customer_fields;
    }

    /**
     *
     * @param null|string $type
     * @return waModel
     */
    protected function getModel($type = null) {
        if (is_string($type) && in_array($type, array(
                'customer',
                'order',
                'order_params',
                'plugin',
                'coupon',
                'customers_filter',
                'product'
            )))
        {
            return $this->loadModel($type);
        } else {
            return parent::getModel($type);
        }
    }

    /**
     *
     * @param string $type
     * @return waModel
     */
    private function loadModel($type)
    {
        if (!isset($this->models[$type])) {
            $name = 'shop'.implode('', array_map('ucfirst', explode('_',  $type))).'Model';
            $this->models[$type] = new $name();
        }
        return $this->models[$type];
    }

    protected function getTableName($type)
    {
        return 'shop_' . $type;
    }

}