<?php

/** @noinspection CssUnknownTarget */

class shopBackendAutocompleteController extends waController
{
    protected $limit = 10;

    public function execute()
    {
        $data = array();
        $q = waRequest::request('term', '', waRequest::TYPE_STRING_TRIM);
        if ($q) {
            $type = waRequest::get('type', 'product', waRequest::TYPE_STRING_TRIM);
            if ($type == 'sku') {
                $data = $this->skusAutocomplete($q);
            } elseif ($type == 'order') {
                $data = $this->ordersAutocomplete($q);
            } elseif ($type == 'order_id') {
                $data = $this->ordersIdAutocomplete($q);
            } elseif ($type == 'customer') {
                $data = $this->customersAutocomplete($q);
            } elseif ($type == 'contact') {
                $data = $this->contactsAutocomplete($q);
            } elseif ($type == 'feature') {
                $data = $this->featuresAutocomplete($q);
            } elseif ($type == 'filter') {
                $data = $this->filterAutocomplete($q);
            } elseif ($type == 'type') {
                $data = $this->typesAutocomplete($q);
            } else {
                $data = $this->productsAutocomplete($q);
            }

            /**
             * @event backend_autocomplete
             * Modify and append to $params['data'] to change results in backend search autocomplete dropdown.
             *
             * @param string $params['type']    which search form it is called from
             * @param string $params['limit']   maximum number of results
             * @param string $params['data']    list of autocomplete results
             * @since 9.1.0
             */
            wa('shop')->event('backend_autocomplete', ref([
                'type' => $type,
                'limit' => $this->limit,
                'data' => &$data,
            ]));

            $data = array_slice(array_values($data), 0, $this->limit);
            $data = $this->formatData($data, $type);
        }
        echo json_encode($data);
    }

    private function formatData($data, $type)
    {
        if (($type == 'order') || ($type == 'order_id')) {

            $orders = array();
            foreach ($data as $k => $item) {
                if ($item['autocomplete_item_type'] == 'order') {
                    $orders[] = $item;
                    unset($data[$k]);
                }
            }

            if ($orders) {
                shopHelper::workupOrders($orders);
                $data = array_merge($orders, $data);
            }

            foreach ($data as &$item) {
                if ($item['autocomplete_item_type'] == 'order') {
                    $item['value'] = shopHelper::encodeOrderId($item['id']);
                    $item['label'] = '';
                    if (!empty($item['icon'])) {
                        $item['label'] .= "<i class='{$item['icon']}'></i>";
                    }
                    $item['label'] .= $item['value']." ".$item['total_str'];
                    $item['label'] .= ' <span class="hint">'.htmlspecialchars($item['customer_name'], ENT_QUOTES, 'utf-8').'</span>';
                    $item = array(
                        'id'                     => $item['id'],
                        'value'                  => $item['value'],
                        'label'                  => $item['label'],
                        'amount'                 => wa_currency($item['total'], $item['currency']),
                        'state'                  => ifset($item['state']['name'], $item['state_id']),
                        'autocomplete_item_type' => 'order',
                    );

                }
            }
            return $data;

        } elseif ($type == 'product') {

            $with_counts = waRequest::get('with_counts', 0, waRequest::TYPE_INT);
            $with_sku_name = waRequest::get('with_sku_name', 0, waRequest::TYPE_INT);
            foreach ($data as &$item) {
                if (empty($item['label'])) {
                    $item['label'] = htmlspecialchars($item['value']);
                }
                if ($with_counts) {
                    $item['label'] .= ' '.shopHelper::getStockCountIcon($item['count'], null, true);
                }
                if ($with_sku_name) {
                    $item['label'] .= ' <span class="hint">'.htmlspecialchars($item['sku_name']).'</span>';
                }
            }

        } elseif ($type == 'filter') {
            foreach ($data as &$item) {
                if (empty($item['label'])) {
                    $item['label'] = htmlspecialchars($item['name']);
                }
            }
        }

        return $data;
    }

    public function skusAutocomplete($q)
    {
        $product_skus_model = new shopProductSkusModel();
        $q = $product_skus_model->escape($q, 'like');
        return $product_skus_model->
        select('id, name AS value')->
        where("name LIKE '{$q}%' OR sku LIKE '{$q}%'")-> // TODO: change name to full_name
        limit($this->limit)->
        fetchAll();
    }

    public function productsAutocomplete($q, $limit = null)
    {
        $with_image = waRequest::post('with_image', 0, waRequest::TYPE_INT);
        $limit = $limit !== null ? $limit : $this->limit;

        $product_model = new shopProductModel();
        $q = $product_model->escape($q, 'like');
        $fields = 'id, name AS value, price, count, sku_id';
        if ($with_image) {
            $fields .= ', image_id';
        }

        $products = array();
        if (is_numeric($q)) {
            $product_id = (int)$q;
            $products = $product_model->select($fields)
                ->where("id = $product_id")
                ->fetchAll('id');

            foreach ($products as &$product) {
                $product['label'] = $this->prepare($product['value'] . " (id=$product_id)", $product_id);
            }
            unset($product);
        }

        $products += $product_model->select($fields)
                                  ->where("name LIKE '$q%'")
                                  ->limit($limit)
                                  ->fetchAll('id');
        $count = count($products);

        if ($count < $limit) {
            $product_skus_model = new shopProductSkusModel();
            $product_ids = $product_skus_model
                ->select('id, product_id')
                ->where("(sku LIKE '$q%' OR name LIKE '$q%')")
                ->limit($limit)
                ->fetchAll('product_id');
            $product_ids = array_keys($product_ids);
            if ($product_ids) {
                $data = $product_model->select($fields)
                                      ->where('id IN ('.implode(',', $product_ids).')')
                                      ->limit($limit - $count)
                                      ->fetchAll('id');

                // not array_merge, because it makes first reset numeric keys and then make merge
                $products = $products + $data;
            }
        }

        // try find with LIKE %query%
        if (count($products) < $limit) {
            $data = $product_model
                ->select($fields)
                ->where("name LIKE '%$q%'")
                ->limit($limit)
                ->fetchAll('id');

            // not array_merge, because it makes first reset numeric keys and then make merge
            $products = $products + $data;
        }
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        $currency = $config->getCurrency();
        foreach ($products as &$p) {
            $p['price_str'] = wa_currency($p['price'], $currency);
            $p['price_html'] = wa_currency_html($p['price'], $currency);
        }
        unset($p);

        if (waRequest::get('with_sku_name')) {
            $sku_ids = array();
            foreach ($products as $p) {
                $sku_ids[] = $p['sku_id'];
            }
            $product_skus_model = new shopProductSkusModel();
            $skus = $product_skus_model->getByField('id', $sku_ids, 'id');
            $sku_names = array();
            foreach ($skus as $sku_id => $sku) {
                if ($sku['name']) {
                    $name = $sku['name'];
                    if ($sku['sku']) {
                        $name .= ' ('.$sku['sku'].')';
                    }
                } else {
                    $name = $sku['sku'];
                }
                $sku_names[$sku_id] = $name;
            }
            foreach ($products as &$p) {
                $p['sku_name'] = $sku_names[$p['sku_id']];
            }
            unset($p);
        }

        if ($with_image) {
            $product_image_model = new shopProductImagesModel();
            $product_images = $product_image_model->getImages(array_keys($products));
            $product['image_url'] = '';
            foreach ($products as &$product) {
                if (isset($product_images[$product['image_id']]['url_crop'])) {
                    $product['image_url'] = $product_images[$product['image_id']]['url_crop'];
                }
            }
            unset($product);
        }

        return array_values($products);
    }

    private function getOrders($q, $limit = null)
    {
        $order_model = new shopOrderModel();
        $limit = $limit ? $limit : $this->limit;
        $orders = $order_model->autocompleteById($q, $limit);
        if (!$orders) {
            return $order_model->autocompleteById($q, $limit, true);
        }
        return $orders;
    }

    public function ordersIdAutocomplete($q)
    {
        if (!wa()->getUser()->getRights('shop', 'orders')) {
            return array();
        }

        $limit = 5;

        // first, assume $q is encoded $order_id, so decode
        $dq = shopHelper::decodeOrderId($q);
        if (!$dq) {
            $dq = self::decodeOrderId($q);
        }

        if ($dq) {
            $hash = sprintf('search/id*=%d', $dq);
        } else {
            if (preg_match('@^[\d]{1,6}$@', $q)) {
                $hash = sprintf('search/id*=%d', $q);
            } elseif (preg_match('/(\w|@)/', $q)) {
                $hash = sprintf('search/params.contact_email*=%s', $q);
            } elseif (preg_match('/^\+?\d+$/', preg_replace('@[\s\-]+@', '', $q))) {
                $hash = sprintf('search/params.contact_phone*=%s', preg_replace('@[\s\-]+@', '', $q));
            } else {
                $hash = sprintf('search/params.contact_name*=%s', $q);
            }
        }

        $filter = waRequest::request('filter');
        if ($filter) {
            if (is_array($filter)) {
                $filter = implode('&', $filter);
            }
            $hash .= '&'.$filter;
        }

        $collection = new shopOrdersCollection($hash);
        $order = waRequest::request('order_by');
        if ($order) {
            $direction = waRequest::request('direction', 'ASC', waRequest::TYPE_STRING_TRIM);
            $collection->orderBy($order, $direction);
        }

        $orders = $collection->getOrders('id,state,total,currency,contact', 0, $limit, false);
        if ($orders) {
            foreach ($orders as &$order) {
                $order['autocomplete_item_type'] = 'order';
                $order['customer_name'] = $order['contact']['name'];
            }
            unset($order);
        }
        return $orders;
    }

    public function ordersAutocomplete($q)
    {
        if (!wa()->getUser()->getRights('shop', 'orders')) {
            return array();
        }

        // search by:
        // 1. order_id,
        // 2. email, phone, firstname, lastname, name
        // 3. product, sku
        // and others
        $limit = 5;

        // first, assume $q is encoded $order_id, so decode
        $dq = shopHelper::decodeOrderId($q);
        if (!$dq) {
            $dq = self::decodeOrderId($q);
        }
        if ($dq) {
            $orders = $this->getOrders($dq, $limit);
        } else {
            $orders = array();
        }

        $cnt = count($orders);
        if ($cnt < $limit) {
            $potential_orders = $this->getOrders($q, $limit - $cnt);
            $orders = array_unique(array_merge($orders, $potential_orders), SORT_REGULAR);
        }

        foreach ($orders as &$o) {
            $o['autocomplete_item_type'] = 'order';
        }
        unset($o);

        $contacts = $this->contactsAutocomplete($q, $limit);
        foreach ($contacts as &$c) {
            $c['autocomplete_item_type'] = 'contact';
            $c['value'] = $c['name'];
        }
        unset($c);

        $products = $this->productsAutocomplete($q, $limit);
        foreach ($products as &$p) {
            $p['autocomplete_item_type'] = 'product';
            if (empty($p['label'])) {
                $p['label'] = htmlspecialchars($p['value']);
            }
            $p['label'] .= ' '.shopHelper::getStockCountIcon($p['count'], null, true);
        }

        return array_merge(
            $orders,
            $contacts,
            $products,
            $this->couponAutocomplete($q, $limit),
            $this->trackingNumberAutocomplete($q, $limit),
            $this->pluginMethodsAutocomplete($q, 'shipping', $limit),
            $this->pluginMethodsAutocomplete($q, 'payment', $limit),
            $this->cityAutocomplete($q, $limit),
            $this->regionAutocomplete($q, $limit),
            $this->countryAutocomplete($q, $limit),
            $this->itemCodesAutocomplete($q, $limit)
        );

    }

    /**
     * Tries to decode order_id ignoring all non-digit characters in string.
     * Helps to implement human-intuitive searching over decoded IDs.
     * @param string $encoded_id
     * @return int
     */
    public static function decodeOrderId($encoded_id)
    {
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        $format = $config->getOrderFormat();
        $format = str_replace('%', 'garbage', $format);
        $format = str_replace('{$order.id}', '%', $format);
        $format = preg_split('~[^0-9%]~', $format);
        foreach ($format as $part) {
            if (strpos($part, '%')) {
                $format = $part;
                break;
            }
        }
        if (!is_string($format)) {
            return '';
        }

        $format = '/^'.str_replace('%', '(\d+)', preg_quote($format, '/')).'$/';
        if (!preg_match($format, $encoded_id, $m)) {
            return '';
        }
        return $m[1];
    }

    public function contactsAutocomplete($q, $limit = null)
    {
        $q = trim($q);

        $m = new waModel();

        // The plan is: try queries one by one (starting with fast ones),
        // until we find 5 rows total.
        $sqls = array();
        $search_terms = array();   // by what term was search in current sql, need for highlighting

        // Name starts with requested string
        $sqls[] = "SELECT c.id, c.name, c.firstname, c.middlename, c.lastname, c.photo
                   FROM wa_contact AS c
                   WHERE c.name LIKE '".$m->escape($q, 'like')."%'
                   LIMIT {LIMIT}";
        $search_terms[] = $q;

        // Email starts with requested string
        $sqls[] = "SELECT c.id, c.name, e.email, c.firstname, c.middlename, c.lastname, c.photo
                   FROM wa_contact AS c
                       JOIN wa_contact_emails AS e
                           ON e.contact_id=c.id
                   WHERE e.email LIKE '".$m->escape($q, 'like')."%'
                   LIMIT {LIMIT}";
        $search_terms[] = $q;

        // Phone contains requested string
        if (preg_match('~^[wp0-9\-\+\#\*\(\)\. ]+$~', $q)) {

            $query_phone = waContactPhoneField::cleanPhoneNumber($q);

            // search sql template
            $sql_template = "SELECT c.id, c.name, d.value as phone, c.firstname, c.middlename, c.lastname, c.photo
                       FROM wa_contact AS c
                           JOIN wa_contact_data AS d
                               ON d.contact_id=c.id AND d.field='phone'
                       WHERE {CONDITION}
                       LIMIT {LIMIT}";

            // search as prefix
            $condition_rule = "d.value LIKE '{PHONE}%'";

            // first of all search by query phone as it
            $sql_t = str_replace("{CONDITION}", $condition_rule, $sql_template);
            $sql = str_replace("{PHONE}", $query_phone, $sql_t);
            $sqls[] = $sql;
            $search_terms[] = $query_phone;

            // than try apply transformation and than search by transform phone

            $is_international = substr($q, 0, 1) === '+';

            // apply transformations for all domains
            $transform_results = waDomainAuthConfig::transformPhonePrefixForDomains($query_phone, $is_international);
            $transform_results = array_filter($transform_results, function ($result) {
                return $result['status'];   // status == true, so phone is changed
            });

            // unique phones that changed after transformation
            $phones = waUtils::getFieldValues($transform_results, 'phone');

            if ($phones) {
                $condition = array();
                foreach ($phones as $phone) {
                    $condition[] = str_replace('{PHONE}', $phone, $condition_rule);
                }
                $condition = '('.join(' OR ', $condition).')';
                $sql = str_replace('{CONDITION}', $condition, $sql_template);

                $sqls[] = $sql;
                $search_terms[] = $phones;
            }

            // search as substring
            $condition_rule = "d.value LIKE '%{PHONE}%'";

            // search only by query phone as it, without transformation
            $sql_t = str_replace("{CONDITION}", $condition_rule, $sql_template);
            $sql = str_replace("{PHONE}", $query_phone, $sql_t);
            $sqls[] = $sql;
            $search_terms[] = $query_phone;

        }

        // Name contains requested string
        $sqls[] = "SELECT c.id, c.name, c.firstname, c.middlename, c.lastname, c.photo
                   FROM wa_contact AS c
                   WHERE c.name LIKE '_%".$m->escape($q, 'like')."%'
                   LIMIT {LIMIT}";
        $search_terms[] = $q;

        // Email contains requested string
        $sqls[] = "SELECT c.id, c.name, e.email, c.firstname, c.middlename, c.lastname, c.photo
                   FROM wa_contact AS c
                       JOIN wa_contact_emails AS e
                           ON e.contact_id=c.id
                   WHERE e.email LIKE '_%".$m->escape($q, 'like')."%'
                   LIMIT {LIMIT}";
        $search_terms[] = $q;

        $limit = $limit !== null ? $limit : 5;
        $result = array();
        foreach ($sqls as $index => $sql) {
            if (count($result) >= $limit) {
                break;
            }
            foreach ($m->query(str_replace('{LIMIT}', $limit, $sql)) as $c) {
                if (empty($result[$c['id']])) {
                    if (!empty($c['firstname']) || !empty($c['middlename']) || !empty($c['lastname'])) {
                        $c['name'] = waContactNameField::formatName($c);
                    }


                    $name = htmlspecialchars($c['name'], ENT_QUOTES, 'utf-8');
                    $email = htmlspecialchars(ifset($c['email'], ''), ENT_QUOTES, 'utf-8');
                    $phone = htmlspecialchars(ifset($c['phone'], ''), ENT_QUOTES, 'utf-8');

                    $terms = (array)$search_terms[$index];
                    foreach ($terms as $term) {
                        $term_safe = htmlspecialchars($term);
                        $match = false;

                        if ($this->match($name, $term_safe)) {
                            $name = $this->prepare($name, $term_safe, false);
                            $match = true;
                        }

                        if ($this->match($email, $term_safe)) {
                            $email = $this->prepare($email, $term_safe, false);
                            if ($email) {
                                $email = '<i class="icon16 email fas fa-envelope"></i> '.$email;
                            }
                            $match = true;
                        }

                        if ($this->match($phone, $term_safe)) {
                            $phone = $this->prepare($phone, $term_safe, false);
                            if ($phone) {
                                $phone = '<i class="icon16 phone fas fa-mobile-alt"></i> '.$phone;
                            }
                            $match = true;
                        }

                        if ($match) {
                            break;
                        }
                    }

                    $result[$c['id']] = array(
                        'id'        => $c['id'],
                        'value'     => $c['id'],
                        'name'      => $c['name'],
                        'photo_url' => waContact::getPhotoUrl($c['id'], $c['photo'], 96),
                        'label'     => implode(' ', array_filter(array($name, $email, $phone))),
                    );

                    if (count($result) >= $limit) {
                        break 2;
                    }
                }
            }
        }

        foreach ($result as &$c) {
            $customer = new shopCustomer($c['id']);
            $userpic = $customer->getUserpic(array('size' => 20));
            $c['label'] = "<i class='icon16 userpic20' style='background-image: url(\"".$userpic."\");'></i>".$c['label'];
        }
        unset($c);

        return array_values($result);
    }

    private function filterAutocomplete($q)
    {
        $category_helper = new shopCategoryHelper();
        $category_id = waRequest::request('category_id', null);
        $options = (array)waRequest::request('options', array());

        $ignore_id = ifset($options, 'ignore_id', []);
        $category_type = ifset($options, 'category_type', []);

        $result = [];

        if ($category_id === 'new' || $category_type == shopCategoryModel::TYPE_DYNAMIC) {
            $options_feature = [
                'status' => null,
            ];
        } elseif ($category_type == shopCategoryModel::TYPE_STATIC) {
            $options_feature = array(
                'type_id' => $category_helper->getTypesId($category_id),
            );
        }

        if (!empty($options_feature)) {
            $options_feature['frontend'] = true;
            $options_feature['count'] = false;
            $options_feature['ignore_id'] = $ignore_id;
            $options_feature['term'] = $q;
            $filters = $category_helper->getFilters($options_feature);
            if (!empty($options['get_default_filters'])) {
                $default_filters = $category_helper->getDefaultFilters();
                if (mb_strpos(mb_strtolower($default_filters['name']), mb_strtolower($q)) !== false) {
                    $filters += [$default_filters];
                }
            }
            $result = $this->prepareFilters($filters);
        }

        return $result;
    }

    protected function prepareFilters($filters)
    {
        $result = [];

        foreach ($filters as $filter) {
            $result[] = array(
                'id'        => $filter['id'],
                'value'     => $filter['code'],
                'code'      => $filter['code'],
                'name'      => $filter['name'],
                'type'      => $filter['type'],
                'type_name' => $filter['type_name'],
                'available_for_sku' => (bool)$filter['available_for_sku'],
            );
        }
        return $result;
    }


    private function featuresAutocomplete($q)
    {
        $model = new shopFeatureModel();
        $result = [];

        $term = $model->escape($q, 'like');
        $table = $model->getTableName();
        $options = (array)waRequest::request('options', array());

        $where = [1];

        if (!empty($options)) {
            foreach ($options as $name => $value) {
                switch ($name) {
                    case 'single':
                        $where[] = '`multiple`=0';
                        break;
                    case 'ignore_id':
                        $ignore_features = (array)$model->escape($value, 'int');
                        if ($ignore_features) {
                            $where[] = 'id NOT IN('.join(",", $ignore_features).')';
                        }
                        break;
                    case 'count':
                        $where[] = 'count >='.$value;
                        break;
                }
            }
        }

        if ($where) {
            $where = ' AND (('.implode(') AND (', $where).'))';
        }

        $sql = <<<SQL
                SELECT * FROM {$table}
                WHERE
                  (`parent_id` IS NULL
                    AND
                    (
                    (`name` LIKE '%{$term}%')
                    OR
                    (`code` LIKE '%{$term}%')
                    )
                  ){$where}
                ORDER BY `count` DESC
                LIMIT 20
SQL;

        $data = $model->query($sql)->fetchAll('code', true);

        foreach ($data as $code => $f) {
            $label = array(
                'name'  => $f['name'],
                'type'  => shopFeatureModel::getTypeName($f),
                'count' => _w('%d value', '%d values', $f['count']),
            );

            $result[] = array(
                'id'       => $f['id'],
                'value'    => $code,
                'name'     => $f['name'],
                'label'    => sprintf('<span title="%s; %s">%s </span><span class="hint">%s</span>', $label['type'], $label['count'], $label['name'], $code),
                'type'     => $f['type'],
                'multiple' => !!$f['multiple'],
            );
        }

        return $result;
    }

    private function typesAutocomplete($q)
    {
        $result = array();
        $model = new shopTypeModel();
        $value = $model->escape($q, 'like');
        $table = $model->getTableName();
        $sql = <<<SQL
SELECT `icon`,`id`,`name`,`count` FROM {$table}
WHERE
  `name` LIKE '%{$value}%'
ORDER BY `count` DESC
LIMIT 20
SQL;
        foreach ($model->query($sql)->fetchAll('id', true) as $id => $t) {
            $icon = shopHelper::getIcon($t['icon']);
            unset($t['icon']);
            $t['count'] = _w('%d product', '%d products', $t['count']);
            $result[] = array(
                'id'    => $id,
                'value' => $id,
                'name'  => $t['name'],
                'label' => $icon.implode('; ', array_filter($t)),
            );
        }

        return $result;
    }

    // Helper for contactsAutocomplete()
    protected function prepare($str, $term_safe, $escape = true)
    {
        $pattern = '~('.preg_quote($term_safe, '~').')~ui';
        $template = '<span class="bold highlighted">\1</span>';
        if ($escape) {
            $str = htmlspecialchars($str, ENT_QUOTES, 'utf-8');
        }
        return preg_replace($pattern, $template, $str);
    }

    protected function match($str, $term_safe, $escape = true)
    {
        if ($escape) {
            $str = htmlspecialchars($str, ENT_QUOTES, 'utf-8');
        }
        return preg_match('~('.preg_quote($term_safe, '~').')~ui', $str);
    }

    public function customersAutocomplete($q)
    {
        if (!wa()->getUser()->getRights('shop', 'customers')) {
            return array();
        }

        $q = trim($q);

        // collect hashes for collection here
        $hashes = array();

        // collect phone terms will used for search
        $phone_terms = array();

        if (preg_match('~^\+*[0-9\s\-\(\)]+$~', $q)) {

            $query_phone = waContactPhoneField::cleanPhoneNumber($q);

            // search as prefix
            $search_hash_t = "search/phone@^={PHONES}";

            // first of all search by query phone as it
            $hash = str_replace('{PHONES}', $query_phone, $search_hash_t);
            $hashes["phone_{$hash}"] = $hash;
            $phone_terms[] = $query_phone;

            // than try apply transformation and than search by transform phone

            $is_international = substr($q, 0, 1) === '+';

            // apply transformations for all domains
            $transform_results = waDomainAuthConfig::transformPhonePrefixForDomains($query_phone, $is_international);
            $transform_results = array_filter($transform_results, function ($result) {
                return $result['status'];   // status == true, so phone is changed
            });

            // unique phones that changed after transformation
            $phones = waUtils::getFieldValues($transform_results, 'phone');

            if ($phones) {
                $hash = str_replace('{PHONES}', join(',', $phones), $search_hash_t);
                // add hash in list of hashes, but check for existing - no need lookup 2 times for the same hash
                if (!isset($hashes["phone_{$hash}"])) {
                    $hashes["phone_{$hash}"] = $hash;
                    $phone_terms = array_merge($phone_terms, $phones);
                }
            }

            // search as substring
            $search_hash_t = "search/phone*={PHONES}";

            // search only by query phone as it, without transformation
            $hash = str_replace('{PHONES}', $query_phone, $search_hash_t);
            $hashes["phone_{$hash}"] = $hash;
            $phone_terms[] = $query_phone;


        } else {
            $hashes['email|name'] = 'search/email|name*='.$q;
            $hashes['city'] = 'search/address:city*='.$q;
            $hashes['region'] = 'search/address:region*='.$q;
            $hashes['country'] = 'search/address:country*='.$q;
        }

        $used_hashes = array();
        $customers = array();

        $limit = 5;

        foreach ($hashes as $hash_id => $hash) {
            $count = count($customers);
            if ($count < $limit) {
                $col = new shopCustomersCollection($hash);
                $res = $col->getCustomers('id,name,firstname,middlename,lastname,email,phone,address', 0, $limit - $count);
                foreach ($res as $customer) {
                    $customers[$customer['id']] = $customer;
                }
                if (count($res) > 0) {
                    if (in_array($hash_id, array('city', 'region', 'country'))) {
                        $used_hashes['address'] = true;
                    } else {
                        $used_hashes[$hash_id] = true;
                    }
                }
            }
        }

        foreach ($customers as &$customer) {

            $customer['address_formatted'] = array();
            $customer['email_formatted'] = array();
            $customer['phone_formatted'] = array();

            foreach ($used_hashes as $hash_id => $is_used) {
                if (empty($is_used)) {
                    continue;
                }

                if ($hash_id === 'address') {
                    if (isset($customer['address'][0])) {
                        $address_field = waContactFields::get('address');
                        foreach ($customer['address'] as $i => $address) {
                            $customer['address_formatted'][$i] = $address_field->format($address, 'html');
                        }
                    }
                    continue;
                }

                if (substr($hash_id, 0, 5) === 'phone') {
                    if (isset($customer['phone'][0])) {
                        foreach ($customer['phone'] as $i => $phone) {
                            $customer['phone_formatted'][$i] = (string)ifset($phone['value']);
                        }
                    }
                    continue;
                }

                if ($hash_id === 'email|name') {
                    if (isset($customer['email'][0])) {
                        $email_field = waContactFields::get('email');
                        foreach ($customer['email'] as $i => $email) {
                            $customer['email_formatted'][$i] = $email_field->format($email, 'html');
                        }
                    }
                    continue;
                }
            }
        }
        unset($customer);

        $result = array();

        $term_safe = htmlspecialchars($q, ENT_QUOTES, 'utf-8');
        foreach ($customers as $c) {

            $name = waContactNameField::formatName($c);
            $name = $this->prepare($name, $term_safe);

            $emails = array();
            foreach ($c['email_formatted'] as $email) {
                if ($this->match($email, $term_safe, false)) {
                    $emails[] = '<i class="icon16 email fas fa-envelope"></i> '.$this->prepare($email, $term_safe, false);
                    break;
                }
            }

            $phones = array();
            foreach ($c['phone_formatted'] as $phone) {
                $phone_terms = array_unique($phone_terms);
                foreach ($phone_terms as $phone_term) {
                    $phone_term_safe = htmlspecialchars($phone_term, ENT_QUOTES, 'utf-8');
                    if ($this->match($phone, $phone_term_safe, false)) {
                        $phones[] = '<i class="icon16 phone fas fa-mobile-alt"></i> '.$this->prepare($phone, $phone_term_safe, false);
                        break 2;
                    }
                }
            }

            $addresses = array();
            foreach ($c['address_formatted'] as $address) {
                if ($this->match($address, $term_safe, false)) {
                    $addresses[] = $this->prepare($address, $term_safe, false);
                    break;
                }
            }


            $result[] = array(
                'value' => $c['name'],
                'label' => implode(' ', array_merge(array($name), $emails, $phones, $addresses)),
                'id'    => $c['id'],
            );
        }

        foreach ($result as &$c) {
            $customer = new shopCustomer($c['id']);
            $userpic = $customer->getUserpic(array('size' => 20));
            $c['label'] = "<i class='icon16 userpic20' style='background-image: url(\"".$userpic."\");'></i>".$c['label'];
        }
        unset($c);

        return array_merge(
            $result,
            $this->couponAutocomplete($q, $limit),
            $this->pluginMethodsAutocomplete($q, 'shipping', $limit),
            $this->pluginMethodsAutocomplete($q, 'payment', $limit),
            $this->cityAutocomplete($q, $limit),
            $this->regionAutocomplete($q, $limit),
            $this->countryAutocomplete($q, $limit)
        );
    }

    public function couponAutocomplete($q, $limit = 5)
    {
        $cm = new shopCouponModel();
        $q = $cm->escape($q, 'like');
        $term_safe = htmlspecialchars($q, ENT_QUOTES, 'utf-8');
        $result = array();
        $limit = $this->limit($limit);
        foreach ($cm->query("SELECT * FROM `shop_coupon` WHERE code LIKE '%{$q}%' LIMIT {$limit}") as $item) {
            $result[] = array(
                'value'                  => $item['code'],
                'label'                  => '<i class="icon16 ss coupon"></i> '.$this->prepare($item['code'], $term_safe),
                'autocomplete_item_type' => 'coupon',
                'id'                     => $item['id']
            );
        }
        return $result;
    }

    public function trackingNumberAutocomplete($q, $limit = 5)
    {
        $opm = new shopOrderParamsModel();
        $q = $opm->escape($q, 'like');
        $term_safe = htmlspecialchars($q, ENT_QUOTES, 'utf-8');
        $result = array();
        $limit = $this->limit($limit);
        foreach ($opm->query("SELECT * FROM `shop_order_params` WHERE name = 'tracking_number' AND value LIKE '{$q}%' LIMIT {$limit}") as $item) {
            $result[] = array(
                'value'                  => $item['value'],
                'label'                  => '<i class="icon16 ss sent"></i> '.$this->prepare($item['value'], $term_safe),
                'autocomplete_item_type' => 'tracking_number'
            );
        }
        return $result;
    }

    public function pluginMethodsAutocomplete($q, $type, $limit = 5)
    {
        $pm = new shopPluginModel();
        $q = $pm->escape($q, 'like');
        $type = $pm->escape($type, 'like');
        $term_safe = htmlspecialchars($q, ENT_QUOTES, 'utf-8');
        $result = array();
        $limit = $this->limit($limit);
        $sql = "SELECT * FROM `shop_plugin` WHERE `type` = '{$type}' AND (`name` LIKE '%{$q}%' OR `plugin` LIKE '{$q}%') LIMIT {$limit}";
        foreach ($pm->query($sql) as $item) {
            $icon = sprintf('<img src="%s" style="height: 16px;">', htmlspecialchars($item['logo'], ENT_QUOTES, 'utf-8'));
            $result[] = array(
                'value'                  => $item['name'],
                'label'                  => $icon.$this->prepare($item['name'], $term_safe),
                'autocomplete_item_type' => $type,
                'id'                     => $item['id']
            );
        }
        return $result;
    }

    public function cityAutocomplete($q, $limit = 5)
    {
        $m = new waContactDataModel();
        $q = $m->escape($q, 'like');
        $term_safe = htmlspecialchars($q, ENT_QUOTES, 'utf-8');
        $limit = $this->limit($limit);
        $result = array();
        $sql = "SELECT DISTINCT value FROM `wa_contact_data` WHERE field = 'address:city' AND value LIKE '%{$q}%' LIMIT {$limit}";
        foreach ($m->query($sql) as $item) {
            $result[] = array(
                'value'                  => $item['value'],
                'label'                  => $this->prepare($item['value'], $term_safe),
                'autocomplete_item_type' => 'city'
            );
        }
        return $result;
    }

    public function regionAutocomplete($q, $limit = 5)
    {
        $rm = new waRegionModel();
        $q = $rm->escape($q, 'like');
        $term_safe = htmlspecialchars($q, ENT_QUOTES, 'utf-8');
        $limit = $this->limit($limit);
        $result = array();
        $url = wa()->getRootUrl();
        $sql = "SELECT DISTINCT code, country_iso3, name FROM `wa_region` WHERE name LIKE '%{$q}%' LIMIT {$limit}";
        foreach ($rm->query($sql) as $item) {
            $icon = '<img src="'.$url.'wa-content/img/country/'.$item['country_iso3'].'.gif"> ';
            $result[] = array(
                'value'                  => $item['country_iso3'].':'.$item['code'],
                'label'                  => $icon.$this->prepare($item['name'], $term_safe),
                'autocomplete_item_type' => 'region'
            );
        }
        return $result;
    }

    public function countryAutocomplete($q, $limit = 5)
    {
        $cm = new waCountryModel();
        $q = $cm->escape($q, 'like');
        $term_safe = htmlspecialchars($q, ENT_QUOTES, 'utf-8');
        $limit = $this->limit($limit);
        $url = wa()->getRootUrl();
        $result = array();
        $sql = "SELECT DISTINCT name, iso3letter FROM `wa_country` WHERE name LIKE '%{$q}%' LIMIT {$limit}";
        foreach ($cm->query($sql) as $item) {
            $icon = '<img src="'.$url.'wa-content/img/country/'.$item['iso3letter'].'.gif"> ';
            $result[] = array(
                'value'                  => $item['iso3letter'],
                'label'                  => $icon.$this->prepare($item['name'], $term_safe),
                'autocomplete_item_type' => 'country'
            );
        }
        $count = count($result);
        if ($count < $limit) {
            $all = $cm->select('name,iso3letter')->fetchAll();
            foreach ($all as $item) {
                $name = _ws($item['name']);
                if ($this->match($name, $term_safe)) {
                    $count += 1;
                    $icon = '<img src="'.$url.'wa-content/img/country/'.$item['iso3letter'].'.gif"> ';
                    $result[] = array(
                        'value'                  => $item['iso3letter'],
                        'label'                  => $icon.$this->prepare($name, $term_safe),
                        'autocomplete_item_type' => 'country'
                    );
                    if ($count >= $limit) {
                        break;
                    }
                }
            }
        }
        return $result;
    }

    public function itemCodesAutocomplete($q, $limit = 5)
    {
        $oicm = new shopOrderItemCodesModel();
        $q = $oicm->escape($q, 'like');
        $term_safe = htmlspecialchars($q, ENT_QUOTES, 'utf-8');
        $limit = $this->limit($limit);
        $result = array();
        $sql = "SELECT DISTINCT oic.code_id, oic.value, oic.code, pc.name
                FROM shop_order_item_codes AS oic
                    LEFT JOIN shop_product_code AS pc
                        ON pc.id=oic.code_id
                WHERE oic.value LIKE '%{$q}%'
                LIMIT {$limit}";
        foreach ($oicm->query($sql) as $item) {
            $result[] = array(
                'id'                     => $item['code_id'],
                'value'                  => $item['value'],
                'label'                  => $this->prepare(ifset($item, 'name', $item['code']).': '.$item['value'], $term_safe),
                'autocomplete_item_type' => 'item_code'
            );
        }
        return $result;
    }

    private function limit($limit)
    {
        return intval(max(1, intval($limit)));
    }
}
