<?php

class shopProductListAction extends waViewAction
{
    /**
     * @var array
     */
    protected $hash;

    /**
     * @var string
     */
    protected $sort;

    /**
     * @var string
     */
    protected $order;

    /**
     * @var string
     */
    protected $text;

    /**
     * @var string
     */
    protected $collection_param = '';

    /**
     * @var shopProductsCollection
     */
    protected $collection;

    /**
     * @var array
     */
    protected $sorts = array(
        'name' => 'name',
        'date' => 'create_datetime',
        'bestsellers' => 'total_sales',
        'rate' => 'rating',
        'price' => 'price',
        'stock' => 'count'
    );

    public function __construct($params = null) {
        parent::__construct($params);
        $this->hash = $this->getHash();
        $this->collection = $this->getCollection($this->hash ? implode('/', $this->hash) : '');
        $info = $this->collection->getInfo();

        if ($info['hash'] == 'category' && empty($info['id'])) {
            throw new waException("Unkown category", 404);
        }
        if ($info['hash'] == 'set' && empty($info['id'])) {
            throw new waException("Unknown list", 404);
        }
        /*
        $this->sort = $this->getSort($this->collection->getInfo());
        if ($this->sort) {
            $this->collection->orderBy($this->getOrder($this->sort));
        }
        */

        $sort = $this->getSort($info);
        list ($this->sort, $this->order) = $sort;
        $sort[0] = isset($this->sorts[$sort[0]]) ? $this->sorts[$sort[0]] : $sort[0];
        $this->collection->orderBy($sort);
    }

    protected function getCollection($hash)
    {
        if ($this->collection !== null) {
            return $this->collection;
        }
        return new shopProductsCollection($hash);
    }

    protected function getHash()
    {
        $hash = waRequest::request('hash', null, waRequest::TYPE_STRING_TRIM);
        if ($hash) {
            return explode('/', trim(ltrim($hash, '#'), '/'));
        }

        $text = waRequest::get('text', null, waRequest::TYPE_STRING_TRIM);
        if ($text) {
            $this->text = urldecode($text);
            $this->collection_param = 'text='.$this->text;
            return array('search', 'query='.$this->text);
        }
        $tag  = waRequest::get('tag', null, waRequest::TYPE_STRING_TRIM);
        if ($tag) {
            $tag = urldecode($tag);
            $this->collection_param = 'tag='.$tag;
            return array('tag', urldecode($tag));
        }
        $category_id = waRequest::get('category_id', null, waRequest::TYPE_INT);
        if ($category_id) {
            $this->collection_param = 'category_id='.$category_id;
            return array('category', $category_id);
        }
        $set_id = waRequest::get('set_id', null, waRequest::TYPE_STRING_TRIM);
        if ($set_id) {
            $this->collection_param = 'set_id='.$set_id;
            return array('set', $set_id);
        }
        $type_id = waRequest::get('type_id', null, waRequest::TYPE_INT);
        if ($type_id) {
            $this->collection_param = 'type_id='.$type_id;
            return array('type', $type_id);
        }
        return null;
    }

    /*
    protected function getOrder($sort)
    {
        $order = array($sort, waRequest::get('order', 'desc', waRequest::TYPE_STRING_TRIM));
        if ($sort && !empty($this->sorts[$sort])) {
            $order[0] = $this->sorts[$sort];
        }
        return $order;
    }
    */

    /*
    protected function getSort($info)
    {
        return waRequest::get(
            'sort',
            !$info['hash'] || $info['hash'] == 'all' ? 'date' : '',
            waRequest::TYPE_STRING_TRIM
        );
    }
    */

    protected function getSort($info)
    {
        $sort  = waRequest::get('sort', '', waRequest::TYPE_STRING_TRIM);
        $order = waRequest::get('order', 'desc', waRequest::TYPE_STRING_TRIM);

        if (!$info['hash'] || $info['hash'] == 'all') {

            // default sort method saved in contact_settings
            $contact_settings_model = new waContactSettingsModel();
            $contact_id = $this->getUser()->getId();

            if (!$sort) {
                $default = $contact_settings_model->getOne($contact_id, 'shop', 'all:sort');
                if ($default) {
                    $chunks = explode(' ', $default);
                    $sort   = $chunks[0];
                    $order  = isset($chunks[1]) ? $chunks[1] : $order;
                } else {
                    $sort = 'date';
                }
            }

            // save current sort as default for next usage
            $contact_settings_model->set($contact_id, 'shop', 'all:sort', $sort.' '.$order);

        } else if ($info['hash'] != 'set' && $info['hash'] != 'category') {
            if (!$sort) {
                $sort = 'date';
            }
        }

        return array($sort, $order);
    }

    protected function workupProducts(&$products)
    {
        $currency = $this->getConfig()->getCurrency();
        foreach ($products as &$p) {
            if ($p['min_price'] == $p['max_price']) {
                $p['price_range'] = wa_currency($p['min_price'], $currency);
            } else {
                $p['price_range'] = wa_currency($p['min_price'], $currency).'...'.wa_currency($p['max_price'], $currency);
            }
            if ($p['badge']) {
                $p['badge'] = shopHelper::getBadgeHtml($p['badge']);
            }
        }
        unset($p);

        if ($this->sort == 'stock') {
            foreach ($products as &$p) {
                $p['icon'] = shopHelper::getStockCountIcon($p['count']);
            }
        } else if ($this->sort == 'date') {
            foreach ($products as &$p) {
                $p['create_datetime_str'] = wa_date('humandatetime', $p['create_datetime']);
            }
        } else if ($this->sort == 'rate') {
            foreach ($products as &$p) {
                $p['rating_str'] = shopHelper::getRatingHtml($p['rating'], 10, true);
            }
        } else if ($this->sort == 'bestsellers') {
            $currency = wa('shop')->getConfig()->getCurrency();
            foreach ($products as &$p) {
                $p['total_sales_str'] = wa_currency($p['total_sales'], $currency);
            }
        }
        unset($p);
    }

    protected function assign($data)
    {
        $this->view->assign($data);
    }
}