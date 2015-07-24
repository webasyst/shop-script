<?php

class shopProductReviewsModel extends waNestedSetModel
{
    const STATUS_DELETED   = 'deleted';
    const STATUS_PUBLISHED = 'approved';
    const AUTH_GUEST = 'guest';
    const AUTH_USER = 'user';

    protected $left = 'left_key';
    protected $right = 'right_key';
    protected $parent = 'parent_id';

    protected $table = 'shop_product_reviews';

    public function getFullTree($product_id, $offset = 0, $count = null, $order = null, array $options = array())
    {
        // first lever reviews - reviews to products
        $reviews = $this->getReviews($product_id, $offset, $count, $order, $options);
        if (!empty($reviews)) {

            $is_frontend = wa()->getEnv() == 'frontend';

            // extract reviews to reviews (comments)
            $sql = "SELECT * FROM {$this->table} WHERE product_id = ".(int)$product_id.
                    " AND review_id IN(".implode(',', array_keys($reviews)).")";
            $sql .= " ORDER BY review_id, {$this->left}";

            foreach ($this->query($sql) as $item) {
                $reviews[$item['review_id']]['comments'][$item['id']] = $item;
            }
            foreach ($reviews as &$review) {
                if (!empty($review['comments'])) {
                    if ($is_frontend) {
                        $this->cutOffDeleted($review['comments']);
                    }
                    $this->extendItems($review['comments'], $options);
                }
            }
            unset($review);
        }

        return $reviews;
    }

    private function cutOffDeleted(&$items)
    {
        // need for cutting deleted reviews and its children in frontend
        $max_depth = 1000;
        if (!empty($items)) {
            $depth = $max_depth;
            foreach ($items as $id => $item) {
                if ($item['status'] == self::STATUS_DELETED) {
                    if ($item[$this->depth] < $depth) {
                        $depth = $item[$this->depth];
                    }
                    unset($items[$id]);
                    continue;
                }
                if ($item[$this->depth] > $depth) {
                    unset($items[$id]);
                } else {
                    $depth = $max_depth;
                }
            }
        }
    }

    /**
     * @param int $product_id
     * @param int $offset
     * @param int $count
     * @param int $order
     * @param array $options
     * @return array
     */
    public function getReviews($product_id, $offset = 0, $count = null, $order = null, array $options = array())
    {
        if (!$product_id) {
            return array();
        }
        $sql = "SELECT * FROM {$this->table} WHERE product_id = ".(int)$product_id." AND review_id = 0";
        if (wa()->getEnv() == 'frontend') {
            $sql .= " AND status = '".self::STATUS_PUBLISHED."'";
        }
        $sql .= " ORDER BY ".($order ? $order : $this->left);
        if ($count !== null) {
            $sql .= " LIMIT ".(int)$offset.", ".(int)$count;
        }
        $reviews = $this->query($sql)->fetchAll('id');
        $this->extendItems($reviews, $options);
        return $reviews;
    }

    public function getProductRates($product_id)
    {
        $sql = "SELECT rate, COUNT(*) c FROM ".$this->table."
                WHERE product_id = i:0 AND review_id = 0 AND status = '".self::STATUS_PUBLISHED."'
                GROUP BY rate
                ORDER BY rate DESC";
        $result = array();
        foreach ($this->query($sql, $product_id) as $row) {
            $result[round($row['rate'])] = $row['c'];
        }
        return $result;
    }

    public function getListDefaultOptions()
    {
        return array(
            'offset' => 0,
            'limit'  => 50,
            'escape' => true,
            'where'  => array()
        );
    }

    // fields = *,is_new,contac,product
    public function getList($fields = '*,is_new,contact,product', $options = array())
    {
        $options += $this->getListDefaultOptions();

        $main_fields = '';
        $post_fields = '';

        foreach (explode(',', $fields) as $name) {
            if ($this->fieldExists($name) || $name == '*') {
                $main_fields .= ','.$name;
            } else {
                $post_fields .= ','.$name;
            }
        }

        $main_fields = substr($main_fields, 1);
        $post_fields = substr($post_fields, 1);

        $where = $this->getWhereByField($options['where']);

        $limit_str = '';
        if ($options['limit'] !== false) {
            $limit_str = " LIMIT ".($options['offset'] ? $options['offset'].',' : '').(int)$options['limit'];
        }

        $sql = "SELECT $main_fields FROM `{$this->table}`".
                ($where ? " WHERE $where" : "").
                " ORDER BY datetime DESC, id".
                $limit_str;

        $data = $this->query($sql)->fetchAll('id');
        if (!$data) {
            return $data;
        }
        foreach ($data as &$item) {
            $item['datetime_ts'] = strtotime($item['datetime']);
            if ($options['escape']) {
                $item['title'] = htmlspecialchars($item['title']);
            }
        }
        unset($item);

        $this->workupList($data, $post_fields, $options);
        return $data;

    }

    private function workupList(&$data, $fields, $options)
    {
        $extract_contact_info = false;
        foreach (explode(',', $fields) as $field) {

            if ($field == 'contact') {
                $contact_ids = array();
                foreach ($data as $item) {
                    if ($item['contact_id']) {
                        $contact_ids[] = $item['contact_id'];
                    }
                }
                $contact_ids = array_unique($contact_ids);
                $contacts = self::getAuthorInfo($contact_ids);

                foreach ($data as &$item) {
                    $author = array(
                        'name' =>  $item['name'],
                        'email' => $item['email'],
                        'site' =>  $item['site']
                    );
                    $item['author'] = array_merge(
                        $author,
                        isset($contacts[$item['contact_id']]) ? $contacts[$item['contact_id']] : array()
                    );
                    if (!empty($options['escape'])) {
                        $item['author']['name'] = htmlspecialchars($item['author']['name']);
                    }
                }
                unset($item);
            }

            if ($field == 'is_new') {
                $this->checkForNew($data);
            }

            if ($field == 'product') {
                $product_ids = array();
                foreach ($data as $item) {
                    $product_ids[] = $item['product_id'];
                }
                $product_ids = array_unique($product_ids);
                $product_model = new shopProductModel();
                $products = $product_model->getById($product_ids);
                if (wa()->getEnv() == 'frontend' && waRequest::param('url_type') == 2) {
                    $cat_ids = array();
                    foreach ($products as &$p) {
                        if (!empty($p['category_id'])) {
                            $cat_ids[] = $p['category_id'];
                        }
                    }
                    $cat_ids = array_unique($cat_ids);
                    if ($cat_ids) {
                        $category_model = new shopCategoryModel();
                        $categories = $category_model->getById($cat_ids);
                        foreach ($products as &$p) {
                            if (!empty($p['category_id'])) {
                                $p['category_url'] = $categories[$p['category_id']]['full_url'];
                            }
                        }
                        unset($p);
                    }
                }

                $image_size = wa('shop')->getConfig()->getImageSize('crop_small');
                foreach ($data as &$item) {
                    if (isset($products[$item['product_id']])) {
                        $product = $products[$item['product_id']];
                        $item['product_name'] = $product['name'];
                        $item['product_image_id'] = $product['image_id'];
                        $item['product_image_ext'] = $product['ext'];
                        if (wa()->getEnv() == 'frontend') {
                            $route_params = array('product_url' => $product['url']);
                            if (isset($product['category_url'])) {
                                $route_params['category_url'] = $product['category_url'];
                            }
                            $item['product_url'] = wa()->getRouteUrl('shop/frontend/product', $route_params);
                        }
                        if ($product['image_id']) {
                            $item['product_url_crop_small'] = shopImage::getUrl(
                                array(
                                    'id' => $product['image_id'],
                                    'product_id' => $product['id'],
                                    'ext' => $product['ext'],
                                    'filename' => $product['image_filename']
                                ),
                                $image_size
                            );
                        } else {
                            $item['product_url_crop_small'] = null;
                        }
                    }
                }
            }
        }

        foreach ($data as &$item) {
            // recursive workuping
            if (!empty($item['comments'])) {
                $this->extendItems($item['comments'], $options);
            }
        }
        unset($item);
    }

    public function count($product_id = null, $reviews_only = true)
    {
        if (wa()->getEnv() == 'frontend') {
            return $this->countInFrontend($product_id, $reviews_only);
        }

        $sql = "SELECT COUNT(id) AS cnt FROM `{$this->table}` ";

        $where = array();
        if ($product_id) {
            $where[] = "product_id = ".(int)$product_id;
        }
        if ($reviews_only) {
            $where[] = "review_id = 0";
        }
        if ($where) {
            $sql .= " WHERE ".implode(' AND ', $where);
        }
        return $this->query($sql)->fetchField('cnt');
    }

    private function countInFrontend($product_id = null, $reviews_only = true)
    {
        if ($product_id) {
            $where = "product_id = ".(int)$product_id;
        } else {
            $where = "";
        }

        if ($reviews_only) {
            $sql = "SELECT COUNT(id) AS cnt FROM `{$this->table}` AS r
                WHERE review_id = 0 AND status = '".self::STATUS_PUBLISHED."' ".
                                ($where ? " AND " . $where : "");
            return $this->query($sql)->fetchField('cnt');
        }

        $fields = array();
        $fields[] = 'id';
        $fields[] = $this->left;
        $fields[] = $this->right;
        $fields[] = $this->depth;
        $fields[] = 'status';
        $sql = "SELECT " .  implode(',', $fields) . "
                FROM `{$this->table}` ".
                        ($where ? "WHERE $where " : " ").
                        "ORDER BY `{$this->left}`";

        $reviews = $this->query($sql)->fetchAll('id');
        $this->cutOffDeleted($reviews);

        return count($reviews);
    }

    public function countNew($recalc = false)
    {
        $datetime = wa('shop')->getConfig()->getLastDatetime();
        $storage = wa()->getStorage();
        $sql = "SELECT COUNT(id) AS cnt FROM `{$this->table}` WHERE datetime > ? AND contact_id != ?";
        $cnt = $this->query($sql, date('Y-m-d H:i:s', $datetime), wa()->getUser()->getId())->fetchField('cnt');
        if (!$recalc) {
            $shop_outdated_reviews_count = (int)$storage->get('shop_outdated_reviews_count');
        } else {
            $viewed_reviews = $storage->get('shop_viewed_reviews');
            $shop_outdated_reviews_count = 0;
            if (!empty($viewed_reviews)) {
                $reviews = $this->getByField('id', array_keys($viewed_reviews), true);
                $this->checkForNew($reviews);
                $shop_outdated_reviews_count = (int)$storage->get('shop_outdated_reviews_count');
            }
        }
        return $cnt - $shop_outdated_reviews_count;
    }

    private function recalcProductRating($product_id, $rate, $inc = true)
    {
        if ($rate <= 0) {
            return;
        }
        $product_model = new shopProductModel();
        $product = $product_model->getById($product_id);
        if ($inc) {
            $update = array(
                    'rating' => ($product['rating']*$product['rating_count'] + $rate)/($product['rating_count'] + 1),
                    'rating_count' => $product['rating_count'] + 1
            );
        } else {
            $update = array(
                    'rating' => ($product['rating']*$product['rating_count'] - $rate)/($product['rating_count'] - 1),
                    'rating_count' => $product['rating_count'] - 1
            );
        }
        $product_model->updateById($product_id, $update);
    }

    public function changeStatus($review_id, $status)
    {
        $review = $this->getById($review_id);
        if (!$review) {
            return false;
        }
        if ($status == $review['status']) {
            return true;
        }
        if ($status != self::STATUS_DELETED && $status != self::STATUS_PUBLISHED) {
            return false;
        }
        if ($status == self::STATUS_DELETED) {
            $this->recalcProductRating($review['product_id'], $review['rate'], false);
        } else {
            $this->recalcProductRating($review['product_id'], $review['rate']);
        }
        $this->updateById($review_id, array('status' => $status));
        return true;
    }

    public function add($review, $parent_id = null, $before_id = null)
    {
        if (empty($review['product_id'])) {
            return false;
        }
        if ($parent_id) {
            $parent = $this->getById($parent_id);
            if (!$parent) {
                return false;
            }
            if ($parent['review_id']) {
                $review['review_id'] = $parent['review_id'];
            } else {
                $review['review_id'] = $parent['id'];
            }
        }

        if (!isset($review['ip']) && ($ip = waRequest::getIp())) {
            $ip = ip2long($ip);
            if ($ip > 2147483647) {
                $ip -= 4294967296;
            }
            $review['ip'] = $ip;
        }

        if(!empty($review['contact_id'])) {
            $user = wa()->getUser();
            if ($user->getId() && !$user->get('is_user')) {
                $user->addToCategory(wa()->getApp());
            }
        }

        if (!isset($review['datetime'])) {
            $review['datetime'] = date('Y-m-d H:i:s');
        }
        if (isset($review['site']) && $review['site']) {
            if (!preg_match('@^https?://@',$review['site'])) {
                $review['site'] = 'http://'.$review['site'];
            }
        }
        $before_id = null;
        $id = parent::add($review, $parent_id, $before_id);
        if (!$id) {
            return false;
        }

        if (empty($review['review_id']) && !empty($review['rate'])) {
            $this->recalcProductRating($review['product_id'], $review['rate']);
        }

        return $id;
    }

    /**
     * @param int|array $contact_id
     */
    static public function getAuthorInfo($contact_id)
    {
        $fields = 'id,name,photo_url_50,photo_url_20,is_user';
        $contact_ids = (array)$contact_id;
        $collection = new waContactsCollection('id/'.implode(',', $contact_ids));
        $contacts = $collection->getContacts($fields, 0, count($contact_ids));
        if (is_numeric($contact_id)) {
            if (isset($contacts[$contact_id])) {
                return $contacts[$contact_id];
            } else {
                return array_fill_keys(explode(',', $fields), '');
            }
        } else {
            return $contacts;
        }
    }

    public function validate($review)
    {
        $errors = array();
        $config = wa('shop')->getConfig();

        if ($review['auth_provider'] == self::AUTH_GUEST) {

            if ($config->getGeneralSettings('require_authorization', false)) {
                return array('name' => _w('Only authorized users can post reviews'));
            }

            if ($config->getGeneralSettings('require_captcha') && !wa()->getCaptcha()->isValid()) {
                return array('captcha' => _w('Invalid captcha code'));
            }

            if (!empty($review['site']) && strpos($review['site'], '://')===false) {
                $review['site'] = "http://" . $review['site'];
            }
            if (empty($review['name']) || mb_strlen($review['name']) == 0 ) {
                $errors['name'] = _w('Name can not be left blank');
            }
            if (mb_strlen($review['name']) > 255) {
                $errors['name'] = _w('Name length should not exceed 255 symbols');
            }
            if (empty($review['email']) || mb_strlen($review['email']) == 0) {
                $errors['email'] = _w('Email can not be left blank');
            }
            $validator = new waEmailValidator();
            if (!$validator->isValid($review['email'])) {
                $errors['email'] = _w('Email is not valid');
            }
            $validator = new waUrlValidator();
            if (!empty($review['site']) && !$validator->isValid($review['site'])) {
                $errors['site'] = _w('Site URL is not valid');
            }
        }

        if (empty($review['parent_id'])) {    // review to product
            if (empty($review['title'])) {
                $errors['title'] = _w('Review title can not be left blank');
            }
        } else {                            // comment ot review
            if (empty($review['text'])) {
                $errors['text'] = _w('Review text can not be left blank');
            }
        }

        if (mb_strlen($review['text']) > 4096) {
            $errors['text'] = _w('Review length should not exceed 4096 symbols');
        }
        return $errors;
    }

    public function getReview($id, $escape = false)
    {
        $item = $this->getById($id);
        $items = array($id => $item);
        $this->extendItems($items, array('escape' => $escape));
        return $items[$id];
    }

    private function extendItems(&$items, array $options = array())
    {
        $escape = !empty($options['escape']);

        $contact_ids = array();
        foreach ($items as $item) {
            if ($item['contact_id']) {
                $contact_ids[] = $item['contact_id'];
            }
        }
        $contact_ids = array_unique($contact_ids);
        $contacts = self::getAuthorInfo($contact_ids);

        foreach ($items as &$item) {
            $item['datetime_ts'] = strtotime($item['datetime']);
            $author = array(
                'name' =>  $item['name'],
                'email' => $item['email'],
                'site' =>  $item['site']
            );
            $item['author'] = array_merge(
                    $author,
                    isset($contacts[$item['contact_id']]) ? $contacts[$item['contact_id']] : array()
            );
            if ($escape) {
                $item['author']['name'] = htmlspecialchars($item['author']['name']);
                $item['text'] = nl2br(htmlspecialchars($item['text']));
                $item['title'] = htmlspecialchars($item['title']);
            }
            // recursive workuping
            if (!empty($item['comments'])) {
                $this->extendItems($item['comments'], $options);
            }
        }
        if (!empty($options['is_new'])) {
            $this->checkForNew($items);
        }
        unset($item);
    }

    public function deleteByProducts(array $product_ids)
    {
        $this->deleteByField('product_id', $product_ids);
    }

    public function unhighlightViewed()
    {
        $storage = wa()->getStorage();
        $viewed_reviews = $storage->get('shop_viewed_reviews');
        if ($viewed_reviews) {
            $viewed_reviews = array_fill_keys(array_keys($viewed_reviews), true);
            $storage->set('shop_viewed_reviews', $viewed_reviews);
            $storage->set('shop_outdated_reviews_count', count($viewed_reviews));
        }
    }

    private function checkForNew(&$items) {
        $config = wa('shop')->getConfig();
        $storage = wa()->getStorage();
        $datetime = $config->getLastDatetime();
        /**
         * Viewed reviews arrays, where key is review_id and value may be timestamp (when has been viewed) or true (mean that view is outdated)
         * @var array
         */
        $viewed_reviews = $storage->get('shop_viewed_reviews');
        /**
         * Count of outdated reviews
         * @var int
         */
        $outdated_reviews_count = (int)$storage->get('shop_outdated_reviews_count');

        $time = time();
        $contact_id = wa()->getUser()->getId();
        if (!$viewed_reviews) {
            $viewed_reviews = array();
            foreach ($items as &$item) {
                $item['is_new'] = false;
                $item['datetime_ts'] = isset($item['datetime_ts']) ? $item['datetime_ts'] : strtotime($item['datetime']);
                if ($item['datetime_ts'] > $datetime && $item['contact_id'] != $contact_id) {
                    $item['is_new'] = true;
                    $viewed_reviews[$item['id']] = $time;
                }
            }
            unset($item);
        } else {
            $review_highlight_time = $config->getOption('review_highlight_time');
            foreach ($items as &$item) {
                $item['is_new'] = false;
                $item['datetime_ts'] = isset($item['datetime_ts']) ? $item['datetime_ts'] : strtotime($item['datetime']);
                if ($item['datetime_ts'] > $datetime && $item['contact_id'] != $contact_id) {
                    if (!isset($viewed_reviews[$item['id']])) {
                        $item['is_new'] = true;
                        $viewed_reviews[$item['id']] = $time;
                    } else {
                        if ($viewed_reviews[$item['id']] !== true && ($viewed_reviews[$item['id']] + $review_highlight_time >= $time)) {
                            $item['is_new'] = true;
                        } else if ($viewed_reviews[$item['id']] !== true) {
                            $viewed_reviews[$item['id']] = true;
                            $outdated_reviews_count += 1;
                        }
                    }
                }
            }
            unset($item);
        }
        // save updated info
        $storage->set('shop_viewed_reviews', $viewed_reviews);
        $storage->set('shop_outdated_reviews_count', $outdated_reviews_count);
    }
}