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
    
    private $product_view;

    public function __construct($params = null) {
        parent::__construct($params);
        $this->hash = $this->getHash();

        if (!$this->hash) {
            if (waRequest::get('sort')) {
                $this->getUser()->setSettings('shop', 'all:sort', waRequest::get('sort').' '.waRequest::get('order', 'desc'));
            } else {
                $sort = $this->getUser()->getSettings('shop', 'all:sort');
                if ($sort) {
                    $sort = explode(' ', $sort);
                    $this->sort = $_GET['sort'] = $sort[0];
                    $this->order = $_GET['order'] = $sort[1];
                }
            }
        }

        $this->collection = $this->getCollection($this->hash ? implode('/', $this->hash) : '');
        $info = $this->collection->getInfo();

        list($this->sort, $this->order) = $this->collection->getOrderBy();

        if ($info['hash'] == 'category' && empty($info['id'])) {
            throw new waException("Unkown category", 404);
        }
        if ($info['hash'] == 'set' && empty($info['id'])) {
            throw new waException("Unknown list", 404);
        }
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
            $this->collection_param = 'hash='.$hash;
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
            
            unset(
                $p['meta_description'],
                $p['meta_keywords'],
                $p['meta_title'],
                $p['description'],
                $p['summary']
            );
            
        }
        unset($p);

        if ($this->sort == 'count') {
            foreach ($products as &$p) {
                $p['icon'] = shopHelper::getStockCountIcon($p['count']);
            }
        } else if ($this->sort == 'create_datetime') {
            foreach ($products as &$p) {
                $p['create_datetime_str'] = wa_date('humandatetime', $p['create_datetime']);
            }
        } else if ($this->sort == 'rating') {
            foreach ($products as &$p) {
                $p['rating_str'] = shopHelper::getRatingHtml($p['rating'], 10, true);
            }
        } else if ($this->sort == 'total_sales') {
            $currency = wa('shop')->getConfig()->getCurrency();
            foreach ($products as &$p) {
                $p['total_sales_str'] = wa_currency($p['total_sales'], $currency);
            }
        }
        unset($p);
        
        $info = $this->collection->getInfo();
        if ($info['hash'] == 'category') {
            $product_ids = array_keys($products);
            $category_products_model = new shopCategoryProductsModel();
            $ids = $category_products_model->filterByEnteringInCategories(
                    $product_ids, $info['id']
            );
            $ids = array_flip($ids);
            foreach ($products as $id => &$product) {
                $product['alien'] = $info['type'] == shopCategoryModel::TYPE_STATIC && !isset($ids[$id]);
            }
            unset($product);
        }
    }

    protected function preAssign($data)
    {
        $data['collection_hash'] = $this->hash;
        if (isset($data['collection_hash'][1])) {
            $data['collection_hash'][1] = urlencode($data['collection_hash'][1]);
        }
        $data['collection_param'] = explode('=', $this->collection_param);
        if (isset($data['collection_param'][1])) {
            $data['collection_param'][1] = urlencode($data['collection_param'][1]);
        }
        $data['collection_param'] = implode('=', $data['collection_param']);
        return $data;
    }


    protected function assign($data)
    {
        $data = $this->preAssign($data);
        $this->view->assign($data);
    }
    
    public function getProductView()
    {
        if (!$this->product_view) {
            $config = $this->getConfig();
            $default_view = $this->getUser()->getSettings('shop', 'products_default_view');
            if (!$default_view) {
                $default_view = $config->getOption('products_default_view');
            }
            $view = waRequest::get('view', $default_view, waRequest::TYPE_STRING_TRIM);
            $include_path = $config->getAppPath() . '/templates/actions/products/product_list_' . $view . '.html';
            if (!file_exists($include_path)) {
                $view = $default_view;
            }
            $this->product_view = $view;
            $this->getUser()->setSettings('shop', 'products_default_view', $view);
        }
        return $this->product_view;
    }
}